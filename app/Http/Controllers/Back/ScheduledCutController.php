<?php

namespace App\Http\Controllers\Back;

use App\Exports\Drought\ScheduledCutExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drought\StoreScheduledCutRequest;
use App\Http\Requests\Drought\UpdateScheduledCutRequest;
use App\Models\Drought\ScheduledCut;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Services\Drought\DroughtExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScheduledCutController extends Controller
{
    public function index(Request $request): View
    {
        $zoneId = $request->integer('zone_id');
        $statut = $request->string('statut')->toString();

        $cuts = ScheduledCut::query()
            ->with(['zone', 'infrastructure'])
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when(array_key_exists($statut, ScheduledCut::STATUTS), fn ($query) => $query->where('statut', $statut))
            ->orderByDesc('date_debut')
            ->paginate(15)
            ->withQueryString();

        return view('back.drought.cuts.index', [
            'cuts' => $cuts,
            'zones' => Zone::orderBy('nom')->get(),
            'statuts' => ScheduledCut::STATUTS,
            'currentZone' => $zoneId ?: null,
            'currentStatut' => $statut,
        ]);
    }

    public function create(): View
    {
        return view('back.drought.cuts.create', $this->formData(new ScheduledCut([
            'statut' => 'planifiee',
            'date_debut' => now()->addDay(),
            'date_fin' => now()->addDay()->addHours(6),
        ])));
    }

    public function store(StoreScheduledCutRequest $request): RedirectResponse
    {
        ScheduledCut::create($request->validated());

        return redirect()
            ->route('back.scheduled-cuts.index')
            ->with('success', 'La coupure programmée a été créée.');
    }

    public function edit(ScheduledCut $cut): View
    {
        return view('back.drought.cuts.edit', $this->formData($cut));
    }

    public function update(UpdateScheduledCutRequest $request, ScheduledCut $cut): RedirectResponse
    {
        $cut->update($request->validated());

        return redirect()
            ->route('back.scheduled-cuts.index')
            ->with('success', 'La coupure programmée a été mise à jour.');
    }

    public function destroy(ScheduledCut $cut): RedirectResponse
    {
        $cut->delete();

        return redirect()
            ->route('back.scheduled-cuts.index')
            ->with('success', 'La coupure programmée a été supprimée.');
    }

    /**
     * Export scheduled cuts in PDF or Excel format.
     */
    public function export(Request $request): Response|BinaryFileResponse
    {
        $zoneId = $request->integer('zone_id') ?: null;
        $statut = $request->string('statut')->toString() ?: null;
        $format = $request->string('format')->toString() ?: 'pdf';

        $cuts = DroughtExportService::getScheduledCutsForExport($zoneId, $statut);

        if ($format === 'excel') {
            return Excel::download(
                new ScheduledCutExport($cuts),
                'drought-scheduled-cuts-' . now()->format('Ymd-His') . '.xlsx'
            );
        }

        return Pdf::loadView('back.drought.exports.scheduled-cuts-pdf', ['cuts' => $cuts])
            ->setPaper('a4', 'landscape')
            ->download('drought-scheduled-cuts-' . now()->format('Ymd-His') . '.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(ScheduledCut $cut): array
    {
        return [
            'cut' => $cut,
            'zones' => Zone::orderBy('nom')->get(),
            'infrastructures' => Infrastructure::orderBy('nom')->get(),
            'statuts' => ScheduledCut::STATUTS,
        ];
    }
}
