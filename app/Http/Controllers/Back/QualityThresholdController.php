<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreQualityThresholdRequest;
use App\Http\Requests\Quality\UpdateQualityThresholdRequest;
use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityThreshold;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QualityThresholdController extends Controller
{
    use ResolvesListingFilters;

    public function index(Request $request): View
    {
        $zoneId = $request->integer('zone_id');
        $parametre = $request->string('parametre')->toString();
        $actif = $request->string('actif')->toString();
        $perPage = $this->listingPerPage($request);

        $thresholds = QualityThreshold::query()
            ->with('zone')
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when(array_key_exists($parametre, QualityThreshold::PARAMETRES), fn ($query) => $query->where('parametre', $parametre))
            ->when(in_array($actif, ['1', '0'], true), fn ($query) => $query->where('actif', $actif === '1'))
            ->orderBy('parametre')
            ->paginate($perPage)
            ->withQueryString();

        return view('back.quality.thresholds.index', [
            'thresholds' => $thresholds,
            'zones' => Zone::orderBy('nom')->get(),
            'parametres' => QualityThreshold::PARAMETRES,
            'currentZone' => $zoneId ?: null,
            'currentParametre' => $parametre,
            'currentActif' => $actif,
            'perPage' => $perPage,
        ]);
    }

    public function create(): View
    {
        return view('back.quality.thresholds.create', $this->formData(new QualityThreshold(['actif' => true])));
    }

    public function store(StoreQualityThresholdRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['actif'] = $request->boolean('actif', true);

        QualityThreshold::create($data);

        return redirect()
            ->route('back.quality-thresholds.index')
            ->with('success', 'Le seuil de qualité a été créé.');
    }

    public function edit(QualityThreshold $threshold): View
    {
        return view('back.quality.thresholds.edit', $this->formData($threshold));
    }

    public function update(UpdateQualityThresholdRequest $request, QualityThreshold $threshold): RedirectResponse
    {
        $data = $request->validated();
        $data['actif'] = $request->boolean('actif');

        $threshold->update($data);

        return redirect()
            ->route('back.quality-thresholds.index')
            ->with('success', 'Le seuil de qualité a été mis à jour.');
    }

    public function destroy(QualityThreshold $threshold): RedirectResponse
    {
        $threshold->delete();

        return redirect()
            ->route('back.quality-thresholds.index')
            ->with('success', 'Le seuil de qualité a été supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(QualityThreshold $threshold): array
    {
        return [
            'threshold' => $threshold,
            'zones' => Zone::orderBy('nom')->get(),
            'parametres' => QualityThreshold::PARAMETRES,
            'unites' => QualityThreshold::UNITES,
        ];
    }
}
