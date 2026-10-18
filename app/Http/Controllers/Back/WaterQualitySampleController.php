<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreWaterQualitySampleRequest;
use App\Http\Requests\Quality\UpdateWaterQualitySampleRequest;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityThreshold;
use App\Models\Quality\WaterQualityAlert;
use App\Models\Quality\WaterQualitySample;
use App\Services\QualityAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WaterQualitySampleController extends Controller
{
    public function __construct(private readonly QualityAnalyzer $analyzer)
    {
    }

    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $statut = $request->string('statut')->toString();
        $zoneId = $request->integer('zone_id');

        $samples = WaterQualitySample::query()
            ->with(['zone', 'preleveur'])
            ->when($search !== '', fn ($query) => $query->where('reference', 'like', "%{$search}%"))
            ->when(array_key_exists($statut, WaterQualitySample::STATUTS), fn ($query) => $query->where('statut', $statut))
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->latest('date_prelevement')
            ->paginate(15)
            ->withQueryString();

        return view('back.quality.samples.index', [
            'samples' => $samples,
            'statuts' => WaterQualitySample::STATUTS,
            'zones' => Zone::orderBy('nom')->get(),
            'search' => $search,
            'currentStatut' => $statut,
            'currentZone' => $zoneId ?: null,
        ]);
    }

    public function create(): View
    {
        return view('back.quality.samples.create', $this->formData(new WaterQualitySample([
            'date_prelevement' => now(),
        ])));
    }

    public function store(StoreWaterQualitySampleRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('parametres');
        $data['user_id'] = $request->user()->id;

        $sample = WaterQualitySample::create($data);

        $analysis = $this->analyzer->analyze($sample, $request->parameterRows());

        return redirect()
            ->route('back.quality-samples.show', $sample)
            ->with('success', "Le prélèvement {$sample->reference} a été enregistré ({$analysis['statut']}).");
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
        $sample->update($request->safe()->except('parametres'));

        $this->analyzer->analyze($sample, $request->parameterRows());

        return redirect()
            ->route('back.quality-samples.show', $sample)
            ->with('success', 'Le prélèvement a été mis à jour et réanalysé.');
    }

    public function destroy(WaterQualitySample $sample): RedirectResponse
    {
        $reference = $sample->reference;
        $sample->delete();

        return redirect()
            ->route('back.quality-samples.index')
            ->with('success', "Le prélèvement {$reference} a été supprimé.");
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
