<?php

namespace App\Http\Controllers\Back;

use App\Exports\Drought\ConsumptionReadingExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drought\StoreConsumptionReadingRequest;
use App\Http\Requests\Drought\UpdateConsumptionReadingRequest;
use App\Models\Drought\ConsumptionReading;
use App\Models\Infrastructure\Zone;
use App\Services\Drought\DroughtExportService;
use App\Services\DroughtAnalyzer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConsumptionReadingController extends Controller
{
    public function __construct(private readonly DroughtAnalyzer $analyzer)
    {
    }

    public function index(Request $request): View
    {
        $zoneId = $request->integer('zone_id');

        $readings = ConsumptionReading::query()
            ->with('zone')
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->orderByDesc('periode')
            ->paginate(20)
            ->withQueryString();

        return view('back.drought.consumption.index', [
            'readings' => $readings,
            'zones' => Zone::orderBy('nom')->get(),
            'currentZone' => $zoneId ?: null,
        ]);
    }

    public function forecast(Request $request): View
    {
        $zoneId = $request->integer('zone_id');
        $zones = Zone::orderBy('nom')->when($zoneId, fn ($query) => $query->whereKey($zoneId))->get();

        $forecasts = $zones->map(fn (Zone $zone) => [
            'zone' => $zone,
            'forecast' => $this->analyzer->forecast($zone),
            'risk' => $this->analyzer->riskScore($zone),
        ]);

        return view('back.drought.consumption.forecast', [
            'forecasts' => $forecasts,
            'zones' => Zone::orderBy('nom')->get(),
            'currentZone' => $zoneId ?: null,
        ]);
    }

    public function create(): View
    {
        return view('back.drought.consumption.create', $this->formData(new ConsumptionReading([
            'periode' => now()->startOfMonth(),
        ])));
    }

    public function store(StoreConsumptionReadingRequest $request): RedirectResponse
    {
        ConsumptionReading::create($request->validated());

        return redirect()
            ->route('back.consumption-readings.index')
            ->with('success', 'Le relevé de consommation a été enregistré.');
    }

    public function edit(ConsumptionReading $reading): View
    {
        return view('back.drought.consumption.edit', $this->formData($reading));
    }

    public function update(UpdateConsumptionReadingRequest $request, ConsumptionReading $reading): RedirectResponse
    {
        $reading->update($request->validated());

        return redirect()
            ->route('back.consumption-readings.index')
            ->with('success', 'Le relevé de consommation a été mis à jour.');
    }

    public function destroy(ConsumptionReading $reading): RedirectResponse
    {
        $reading->delete();

        return redirect()
            ->route('back.consumption-readings.index')
            ->with('success', 'Le relevé de consommation a été supprimé.');
    }

    /**
     * Export consumption readings in PDF or Excel format.
     */
    public function export(Request $request): Response|BinaryFileResponse
    {
        $zoneId = $request->integer('zone_id') ?: null;
        $format = $request->string('format')->toString() ?: 'pdf';

        $readings = DroughtExportService::getConsumptionReadingsForExport($zoneId);

        if ($format === 'excel') {
            return Excel::download(
                new ConsumptionReadingExport($readings),
                'drought-consumption-readings-' . now()->format('Ymd-His') . '.xlsx'
            );
        }

        return Pdf::loadView('back.drought.exports.consumption-pdf', ['readings' => $readings])
            ->setPaper('a4', 'landscape')
            ->download('drought-consumption-readings-' . now()->format('Ymd-His') . '.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(ConsumptionReading $reading): array
    {
        return [
            'reading' => $reading,
            'zones' => Zone::orderBy('nom')->get(),
        ];
    }
}
