<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\IncidentStatsFilterRequest;
use App\Services\IncidentStatsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class IncidentDashboardController extends Controller
{
    public function __construct(
        private readonly IncidentStatsService $stats,
    ) {}

    public function index(IncidentStatsFilterRequest $request): View
    {
        return view('back.incidents.dashboard', [
            ...$this->dashboardData($request),
            'periods' => IncidentStatsFilterRequest::PERIODS,
        ]);
    }

    public function pdf(IncidentStatsFilterRequest $request): Response
    {
        $data = $this->dashboardData($request);

        $pdf = Pdf::loadView('back.incidents.pdf.report', [
            'summary' => $data['summary'],
            'periodLabel' => $data['periodLabel'],
            'from' => $data['from'],
            'to' => $data['to'],
            'generatedAt' => now(),
            'author' => $request->user()?->name ?? '—',
        ])->setPaper('a4');

        return $pdf->download('indicateurs-incidents-'.now()->format('Ymd').'.pdf');
    }

    /**
     * @return array{
     *     summary: array<string, mixed>,
     *     currentPeriod: string,
     *     from: string|null,
     *     to: string|null,
     *     periodLabel: string
     * }
     */
    private function dashboardData(IncidentStatsFilterRequest $request): array
    {
        $period = $request->periodKey();
        [$from, $to] = $this->resolveRange($request, $period);

        return [
            'summary' => $this->stats->summary($from, $to),
            'currentPeriod' => $period,
            'from' => $from,
            'to' => $to,
            'periodLabel' => $this->periodLabel($period, $from, $to),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRange(IncidentStatsFilterRequest $request, string $period): array
    {
        return match ($period) {
            '90d' => [now()->subDays(89)->toDateString(), now()->toDateString()],
            'year' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'custom' => [
                (string) $request->validated('from'),
                (string) $request->validated('to'),
            ],
            default => [now()->subDays(29)->toDateString(), now()->toDateString()],
        };
    }

    private function periodLabel(string $period, ?string $from, ?string $to): string
    {
        if ($period === 'custom') {
            return sprintf(
                'Du %s au %s',
                $from ? Carbon::parse($from)->format('d/m/Y') : '—',
                $to ? Carbon::parse($to)->format('d/m/Y') : '—',
            );
        }

        if ($period === 'year') {
            return 'Année '.now()->year;
        }

        return IncidentStatsFilterRequest::PERIODS[$period] ?? '30 derniers jours';
    }
}
