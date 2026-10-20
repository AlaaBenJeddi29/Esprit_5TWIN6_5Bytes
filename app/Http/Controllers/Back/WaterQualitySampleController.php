<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreWaterQualitySampleRequest;
use App\Http\Requests\Quality\UpdateWaterQualitySampleRequest;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityPlanification;
use App\Models\Quality\QualityThreshold;
use App\Models\Quality\WaterQualityAlert;
use App\Models\Quality\WaterQualitySample;
use App\Services\QualityAnalyzer;
use App\Services\QualityPlanningService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WaterQualitySampleController extends Controller
{
    use ResolvesListingFilters;

    private const EVOLUTION_LIMIT = 20;

    /** Fenêtre utilisée par QualityAnalyzer::zoneSummary() pour la tendance. */
    private const TREND_WINDOW = 6;

    public function __construct(
        private readonly QualityAnalyzer $analyzer,
        private readonly QualityPlanningService $planning,
    ) {
    }

    public function index(Request $request): View|Response|RedirectResponse
    {
        if ($request->boolean('pdf')) {
            return $this->downloadReport($request);
        }

        $search = $request->string('search')->toString();
        $statut = $request->string('statut')->toString();
        $zoneId = $request->integer('zone_id');
        $infrastructureId = $request->integer('infrastructure_id');
        $dates = $this->listingDateRange($request);
        $perPage = $this->listingPerPage($request);

        $zones = Zone::orderBy('nom')->get();

        $samples = WaterQualitySample::query()
            ->with(['zone', 'preleveur'])
            ->when($search !== '', fn ($query) => $query->where('reference', 'like', "%{$search}%"))
            ->when(array_key_exists($statut, WaterQualitySample::STATUTS), fn ($query) => $query->where('statut', $statut))
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when($infrastructureId, fn ($query) => $query->where('infrastructure_id', $infrastructureId))
            ->when($dates['from'], fn ($query) => $query->whereDate('date_prelevement', '>=', $dates['from']))
            ->when($dates['to'], fn ($query) => $query->whereDate('date_prelevement', '<=', $dates['to']))
            ->latest('date_prelevement')
            ->paginate($perPage)
            ->withQueryString();

        $perimetreId = $request->integer('perimetre');
        $perimetre = $perimetreId ? $zones->firstWhere('id', $perimetreId) : null;

        $perimetreTrends = [];
        $perimetreHistory = collect();
        $perimetreSummary = null;
        $trendDisponible = false;

        if ($perimetre) {
            $perimetreTrends = $this->analyzer->parameterTrends($perimetre, self::EVOLUTION_LIMIT);
            $perimetreHistory = $this->analyzer->zoneHistory($perimetre, self::EVOLUTION_LIMIT);
            $perimetreSummary = $this->analyzer->zoneSummary($perimetre);
            $trendDisponible = $perimetreHistory->take(-self::TREND_WINDOW)->whereNotNull('score')->count() >= 3;
        }

        return view('back.quality.samples.index', [
            'samples' => $samples,
            'statuts' => WaterQualitySample::STATUTS,
            'zones' => $zones,
            'infrastructures' => Infrastructure::orderBy('nom')->get(),
            'search' => $search,
            'currentStatut' => $statut,
            'currentZone' => $zoneId ?: null,
            'currentInfrastructure' => $infrastructureId ?: null,
            'from' => $dates['from'],
            'to' => $dates['to'],
            'perPage' => $perPage,
            'stats' => $this->analyzer->statistics(),
            'perimetre' => $perimetre,
            'perimetreTrends' => $perimetreTrends,
            'perimetreHistory' => $perimetreHistory,
            'perimetreSummary' => $perimetreSummary,
            'trendDisponible' => $trendDisponible,
            'evolutionLimit' => self::EVOLUTION_LIMIT,
            'planningReminders' => $this->planning->reminders($request->user()),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $planification = null;

        if ($request->filled('planification')) {
            $planification = QualityPlanification::with('zone')->find($request->integer('planification'));
            abort_if($planification === null, 404);

            $this->planning->authorizePlanification($request->user(), $planification);

            if ($planification->estEffectue()) {
                return redirect()
                    ->route('back.quality-planifications.index')
                    ->with('error', 'Ce contrôle a déjà été effectué : aucun prélèvement supplémentaire ne peut y être associé.');
            }
        }

        $sample = new WaterQualitySample([
            'date_prelevement' => $planification
                ? $planification->date_prevue->copy()->setTime(9, 0)
                : now(),
            'zone_id' => $planification?->zone_id,
        ]);

        return view('back.quality.samples.create', $this->formData($sample) + [
            'planification' => $planification,
        ]);
    }

    public function store(StoreWaterQualitySampleRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['parametres', 'planification_id']);
        $data['user_id'] = $request->user()->id;

        $planification = null;

        if ($request->filled('planification_id')) {
            $planification = QualityPlanification::find($request->integer('planification_id'));

            if (! $planification) {
                return back()->withInput()->withErrors([
                    'planification_id' => 'Le contrôle planifié est introuvable.',
                ]);
            }

            $this->planning->authorizePlanification($request->user(), $planification);

            if ($planification->estEffectue()) {
                return back()->withInput()->withErrors([
                    'planification_id' => 'Ce contrôle a déjà été effectué.',
                ]);
            }

            if ((int) $data['zone_id'] !== (int) $planification->zone_id) {
                return back()->withInput()->withErrors([
                    'zone_id' => 'Le prélèvement doit concerner la zone du contrôle planifié.',
                ]);
            }
        }

        $sample = WaterQualitySample::create($data);

        $analysis = $this->analyzer->analyze($sample, $request->parameterRows());

        $next = null;

        if ($planification) {
            DB::transaction(function () use ($planification, $sample, $request, &$next) {
                $locked = QualityPlanification::query()
                    ->whereKey($planification->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked && ! $locked->estEffectue()) {
                    $locked->update(['water_quality_sample_id' => $sample->id]);
                    $next = $locked->createNextOccurrence($request->user()->id);
                }
            });
        }

        $message = "Le prélèvement {$sample->reference} a été enregistré ({$analysis['statut']}).";

        if ($planification) {
            $message .= $next
                ? " Contrôle planifié marqué effectué ; prochain contrôle prévu le {$next->date_prevue->format('d/m/Y')}."
                : ' Contrôle planifié marqué effectué.';
        }

        return redirect()
            ->route('back.quality-samples.show', $sample)
            ->with('success', $message);
    }

    public function show(WaterQualitySample $sample): View
    {
        $sample->load(['zone', 'infrastructure', 'preleveur', 'parameters', 'alerts']);

        return view('back.quality.samples.show', [
            'sample' => $sample,
            'aiSummary' => $this->analyzer->cachedSummary($sample),
        ]);
    }

    public function edit(WaterQualitySample $sample): View
    {
        $sample->load('parameters');

        return view('back.quality.samples.edit', $this->formData($sample));
    }

    public function update(UpdateWaterQualitySampleRequest $request, WaterQualitySample $sample): RedirectResponse
    {
        $data = $request->safe()->except(['parametres', 'planification_id']);

        if ($sample->planification()->exists() && (int) $data['zone_id'] !== (int) $sample->zone_id) {
            return back()->withInput()->withErrors([
                'zone_id' => "La zone d'un prélèvement lié à un contrôle planifié ne peut pas être modifiée.",
            ]);
        }

        $sample->update($data);

        $this->analyzer->analyze($sample, $request->parameterRows());

        return redirect()
            ->route('back.quality-samples.show', $sample)
            ->with('success', 'Le prélèvement a été mis à jour et réanalysé.');
    }

    public function destroy(WaterQualitySample $sample): RedirectResponse
    {
        if ($sample->planification()->exists()) {
            return redirect()
                ->route('back.quality-samples.show', $sample)
                ->with('error', 'Ce prélèvement est lié à un contrôle planifié : son historique doit être conservé.');
        }

        $reference = $sample->reference;
        $sample->delete();

        return redirect()
            ->route('back.quality-samples.index')
            ->with('success', "Le prélèvement {$reference} a été supprimé.");
    }

    /**
     * Rapport PDF d'une seule zone, généré depuis la route d'index existante
     * (paramètre `pdf=1`) afin de ne pas modifier les routes partagées.
     */
    private function downloadReport(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'perimetre' => ['required', 'integer', 'exists:zones,id'],
        ], [
            'perimetre.required' => 'Sélectionnez une zone avant de générer le rapport PDF.',
            'perimetre.integer' => 'La zone sélectionnée est invalide.',
            'perimetre.exists' => 'La zone sélectionnée est introuvable.',
        ]);

        $zone = Zone::findOrFail((int) $validated['perimetre']);
        $report = $this->analyzer->zoneReport($zone);

        if ($report['history']->isEmpty()) {
            return redirect()
                ->route('back.quality-samples.index', ['perimetre' => $zone->id])
                ->with('error', "Aucun prélèvement n'a été enregistré pour la zone « {$zone->nom} » : le rapport PDF ne peut pas être généré.");
        }

        $pdf = Pdf::loadView('back.quality.reports.pdf', [
            ...$report,
            'generatedAt' => now(),
            'author' => $request->user()?->name ?? '—',
        ])->setPaper('a4');

        $slug = Str::slug($zone->nom) ?: 'zone-'.$zone->id;

        return $pdf->download("rapport-qualite-{$slug}-".now()->format('Ymd').'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(WaterQualitySample $sample): array
    {
        $existing = $sample->exists
            ? $sample->parameters->keyBy('parametre')
            : collect();

        return [
            'sample' => $sample,
            'zones' => Zone::orderBy('nom')->get(),
            'infrastructures' => Infrastructure::orderBy('nom')->get(),
            'parametres' => QualityThreshold::PARAMETRES,
            'unites' => QualityThreshold::UNITES,
            'existing' => $existing,
            'alertsCount' => $sample->exists
                ? WaterQualityAlert::where('sample_id', $sample->id)->count()
                : 0,
        ];
    }
}
