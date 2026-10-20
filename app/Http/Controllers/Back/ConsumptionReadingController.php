<?php

namespace App\Http\Controllers\Back;

use App\Exports\Drought\ConsumptionReadingExport;
use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drought\StoreConsumptionReadingRequest;
use App\Http\Requests\Drought\UpdateConsumptionReadingRequest;
use App\Models\Drought\ConsumptionReading;
use App\Models\Infrastructure\Zone;
use App\Services\Drought\DroughtExportService;
use App\Services\DroughtAnalyzer;
use App\Services\ConsumptionForecastService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConsumptionReadingController extends Controller
{
    use ResolvesListingFilters;

    public function __construct(
        private readonly DroughtAnalyzer $analyzer,
        private readonly ConsumptionForecastService $forecastService,
    ) {}


    public function index(Request $request): View
    {
        $zoneId = $request->integer('zone_id');
        ['from' => $from, 'to' => $to] = $this->listingDateRange($request);
        $perPage = $this->listingPerPage($request);

        $readings = ConsumptionReading::query()
            ->with('zone')
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when($from !== null, fn ($query) => $query->whereDate('periode', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('periode', '<=', $to))
            ->orderByDesc('periode')
            ->paginate($perPage)
            ->withQueryString();

        return view('back.drought.consumption.index', [
            'readings' => $readings,
            'zones' => Zone::orderBy('nom')->get(),
            'currentZone' => $zoneId ?: null,
            'from' => $from,
            'to' => $to,
            'perPage' => $perPage,
        ]);
    }

    public function forecast(Request $request): View
    {
        $zoneId = $request->integer('zone_id');
        $zones = Zone::orderBy('nom')->when($zoneId, fn ($query) => $query->whereKey($zoneId))->get();

        $forecasts = $zones->map(function (Zone $zone) {
            $cacheKey = "forecast_zone_{$zone->id}";

            $liveData = Cache::remember($cacheKey, now()->addHour(), function () use ($zone) {
                $result = $this->forecastService->predictNextMonths($zone, 6);

                // Build forecast points with Carbon month labels starting from next month
                $forecastPoints = [];
                $startMonth = Carbon::now()->startOfMonth()->addMonth();
                foreach ($result['predictions'] as $i => $prediction) {
                    $forecastPoints[] = [
                        'month'  => $startMonth->copy()->addMonths($i)->translatedFormat('M Y'),
                        'date'   => $startMonth->copy()->addMonths($i)->format('Y-m-d'),
                        'value'  => $prediction,
                        'lower'  => $result['lower_bounds'][$i] ?? 0,
                        'upper'  => $result['upper_bounds'][$i] ?? 0,
                    ];
                }

                return [
                    'forecastPoints' => $forecastPoints,
                    'mae'            => $result['mae'],
                    'model'          => $result['model'],
                    'hasPredictions' => count($result['predictions']) > 0,
                ];
            });

            return [
                'zone'             => $zone,
                'forecast'         => $this->analyzer->forecast($zone),
                'risk'             => $this->analyzer->riskScore($zone),
                'forecastPoints'   => collect($liveData['forecastPoints']),
                'mae'              => $liveData['mae'],
                'hasPredictions'   => $liveData['hasPredictions'],
                'historicalReadings' => $zone->consumptionReadings()
                    ->orderBy('periode')
                    ->get()
                    ->take(-12),
            ];
        });

        return view('back.drought.consumption.forecast', [
            'forecasts'   => $forecasts,
            'zones'       => Zone::orderBy('nom')->get(),
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
        $reading = ConsumptionReading::create($request->validated());
        Cache::forget("forecast_zone_{$reading->zone_id}");

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
        Cache::forget("forecast_zone_{$reading->zone_id}");

        return redirect()
            ->route('back.consumption-readings.index')
            ->with('success', 'Le relevé de consommation a été mis à jour.');
    }

    public function destroy(ConsumptionReading $reading): RedirectResponse
    {
        $zoneId = $reading->zone_id;
        $reading->delete();
        Cache::forget("forecast_zone_{$zoneId}");

        return redirect()
            ->route('back.consumption-readings.index')
            ->with('success', 'Le relevé de consommation a été supprimé.');
    }

    /**
     * Export consumption readings in PDF or CSV format.
     */
    public function export(Request $request, string $format): Response|BinaryFileResponse
    {
        $zoneId = $request->integer('zone_id') ?: null;

        $readings = DroughtExportService::getConsumptionReadingsForExport($zoneId);

        if ($format === 'excel' || $format === 'csv') {
            $export = new ConsumptionReadingExport($readings);
            $content = $export->toHtml();
            $filename = 'drought-consumption-readings-' . now()->format('Ymd-His') . '.xls';
            
            return response($content, 200)
                ->header('Content-Type', 'text/html; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
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
