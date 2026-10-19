<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreQualityPlanificationRequest;
use App\Http\Requests\Quality\UpdateQualityPlanificationRequest;
use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityPlanification;
use App\Services\QualityPlanningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

class QualityPlanificationController extends Controller
{
    public function __construct(private readonly QualityPlanningService $planning)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $zones = $this->planning->zonesFor($user);
        $zoneId = $request->integer('zone_id');

        if ($zoneId) {
            $this->planning->authorizeZone($user, $zoneId);
        }

        $statut = $request->string('statut')->toString();
        $statut = array_key_exists($statut, QualityPlanification::STATUTS) ? $statut : '';
        $date = $this->parseDate($request->string('date')->toString());

        $planifications = QualityPlanification::query()
            ->accessibleTo($user)
            ->with(['zone', 'sample', 'creator'])
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when($statut !== '', fn ($query) => $query->withStatut($statut))
            ->when($date, fn ($query) => $query->whereDate('date_prevue', $date))
            ->orderBy('date_prevue')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('back.quality.planifications.index', [
            'planifications' => $planifications,
            'zones' => $zones,
            'statuts' => QualityPlanification::STATUTS,
            'frequences' => QualityPlanification::FREQUENCES,
            'currentZone' => $zoneId ?: null,
            'currentStatut' => $statut,
            'currentDate' => $date,
            'reminders' => $this->planning->reminders($user),
        ]);
    }

    public function create(Request $request): View
    {
        $this->ensureAdmin($request);

        return view('back.quality.planifications.create', [
            'planification' => new QualityPlanification([
                'date_prevue' => today(),
                'frequence' => 'mensuelle',
            ]),
            'zones' => Zone::query()->orderBy('nom')->get(),
            'frequences' => QualityPlanification::FREQUENCES,
        ]);
    }

    public function store(StoreQualityPlanificationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $planification = QualityPlanification::create($data);

        return redirect()
            ->route('back.quality-planifications.index')
            ->with('success', "Le contrôle du {$planification->date_prevue->format('d/m/Y')} pour la zone « {$planification->zone->nom} » a été planifié.");
    }

    public function edit(Request $request, QualityPlanification $planification): View
    {
        $this->ensureAdmin($request);

        return view('back.quality.planifications.edit', [
            'planification' => $planification,
            'zones' => Zone::query()->orderBy('nom')->get(),
            'frequences' => QualityPlanification::FREQUENCES,
        ]);
    }

    public function update(UpdateQualityPlanificationRequest $request, QualityPlanification $planification): RedirectResponse
    {
        if ($planification->estEffectue()) {
            return back()->with('error', "Ce contrôle a déjà été effectué : son historique ne peut pas être modifié.");
        }

        $planification->update($request->validated());

        return redirect()
            ->route('back.quality-planifications.index')
            ->with('success', "Le contrôle du {$planification->date_prevue->format('d/m/Y')} a été mis à jour.");
    }

    public function destroy(Request $request, QualityPlanification $planification): RedirectResponse
    {
        $this->ensureAdmin($request);

        if ($planification->estEffectue()) {
            return back()->with('error', 'Un contrôle effectué est conservé dans l’historique et ne peut pas être supprimé.');
        }

        $planification->delete();

        return redirect()
            ->route('back.quality-planifications.index')
            ->with('success', 'Le contrôle planifié a été supprimé.');
    }

    public function calendar(Request $request): View
    {
        $user = $request->user();
        $zones = $this->planning->zonesFor($user);
        $zoneId = $request->integer('zone_id');

        if ($zoneId) {
            $this->planning->authorizeZone($user, $zoneId);
        }

        $statut = $request->string('statut')->toString();
        $statut = array_key_exists($statut, QualityPlanification::STATUTS) ? $statut : '';

        $month = $this->parseMonth($request->string('month')->toString());
        $start = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $controls = QualityPlanification::query()
            ->accessibleTo($user)
            ->with(['zone', 'sample'])
            ->whereDate('date_prevue', '>=', $start->toDateString())
            ->whereDate('date_prevue', '<=', $end->toDateString())
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when($statut !== '', fn ($query) => $query->withStatut($statut))
            ->orderBy('date_prevue')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (QualityPlanification $planification) => $planification->date_prevue->toDateString());

        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $days[] = $day->copy();
        }

        return view('back.quality.planifications.calendar', [
            'month' => $month,
            'days' => $days,
            'controlsByDay' => $controls,
            'zones' => $zones,
            'statuts' => QualityPlanification::STATUTS,
            'currentZone' => $zoneId ?: null,
            'currentStatut' => $statut,
            'previousMonth' => $month->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonthNoOverflow()->format('Y-m'),
            'reminders' => $this->planning->reminders($user),
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Seul un administrateur peut gérer les planifications.');
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function parseMonth(string $value): Carbon
    {
        if ($value === '') {
            return today()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('!Y-m', $value)->startOfMonth();
        } catch (Throwable) {
            return today()->startOfMonth();
        }
    }
}
