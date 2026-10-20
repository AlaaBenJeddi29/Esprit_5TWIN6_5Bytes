@extends('back.layouts.app')

@section('title', 'AquaSecure — Prévisions de consommation')

@section('content')
    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $riskTone = fn (string $niveau) => match ($niveau) {
            'critique' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            'eleve' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            'modere' => 'bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-400',
            default => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
        };
        $riskLabel = fn (string $niveau) => match ($niveau) {
            'critique' => 'Critique',
            'eleve' => 'Élevé',
            'modere' => 'Modéré',
            default => 'Faible',
        };
    @endphp

    <x-back.page-header title="Prévisions de consommation" desc="Projection interactive avec historique, prévisions et intervalles de confiance (±15%). Algorithme Holt-Winters.">
        <x-slot:action>
            <a href="{{ route('back.consumption-readings.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Relevés</a>
        </x-slot:action>
    </x-back.page-header>

    <form novalidate method="GET" class="mb-5 flex flex-wrap gap-2">
        <select name="zone_id" class="{{ $inputClass }}">
            <option value="">Toutes les zones</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Filtrer</button>
    </form>

    @if ($forecasts->isEmpty())
        <x-back.empty-state title="Aucune zone" message="Créez des zones et des relevés pour produire des prévisions." />
    @else
        <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            @foreach ($forecasts as $item)
                @php
                    $zone = $item['zone'];
                    $forecast = $item['forecast'];
                    $risk = $item['risk'];
                    $historicalReadings = $item['historicalReadings'];
                    $forecastPoints = $item['forecastPoints'];   // collect([['month','date','value','lower','upper'], ...])
                    $mae = $item['mae'];
                    $hasPredictions = $item['hasPredictions'];
                    
                    // Prepare chart data
                    $chartId = 'chart-zone-' . $zone->id;
                    $historicalData = $historicalReadings->map(fn ($r) => [
                        'month' => \Illuminate\Support\Carbon::parse($r->periode)->translatedFormat('M Y'),
                        'value' => (float) $r->consommation_m3,
                        'date' => $r->periode->format('Y-m-d'),
                    ])->values();
                    $forecastData = $forecastPoints->map(fn ($f) => [
                        'month' => $f['month'],
                        'value' => (float) $f['value'],
                        'lower' => (float) $f['lower'],
                        'upper' => (float) $f['upper'],
                        'date'  => $f['date'],
                    ])->values();
                    $allLabels = collect($historicalData)->pluck('month')->merge($forecastData->pluck('month'))->values();
                @endphp
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $zone->nom }}</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $zone->commune }} — {{ $zone->population }} hab.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $riskTone($risk['niveau']) }}">
                                Risque {{ $riskLabel($risk['niveau']) }} · {{ $risk['score'] }}/100
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-600 dark:bg-success-500/15 dark:text-success-400">
                                <svg class="h-2.5 w-2.5 fill-current" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                IA en direct
                            </span>
                        </div>
                    </div>

                    @if ($historicalData->count() > 0 || $forecastData->count() > 0)
                        <div class="mt-5 flex items-center justify-between gap-3">
                            <div>
                                <label for="window-{{ $chartId }}" class="block text-xs font-medium text-gray-600 dark:text-gray-400">Fenêtre (derniers mois)</label>
                                <select id="window-{{ $chartId }}" class="mt-1 rounded-lg border-gray-300 bg-white text-xs text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" onchange="updateChart{{ str_replace('-', '', $chartId) }}(this.value)">
                                    <option value="12">12 derniers mois</option>
                                    <option value="18">18 derniers mois</option>
                                    <option value="24">24 derniers mois</option>
                                    <option value="30">Tous (30+ mois)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-5 rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/50">
                            <div class="overflow-x-auto" style="max-width: 100%; height: 320px;">
                                <div style="min-width: max-content; padding: 10px;">
                                    <canvas id="{{ $chartId }}" class="max-h-72"></canvas>
                                </div>
                            </div>
                        </div>

                        {{-- Statistics Row --}}
                        <div class="mt-5 grid grid-cols-3 gap-3">
                            @php
                                $currentConsumption = $historicalData->last()['value'] ?? 0;
                                $avgHistorical = $historicalData->count() > 0 ? $historicalData->avg('value') : 0;
                                $avgForecast = $forecastData->count() > 0 ? $forecastData->avg('value') : 0;
                                $trend = $avgForecast > 0 && $avgHistorical > 0 ? round((($avgForecast - $avgHistorical) / $avgHistorical) * 100, 1) : 0;
                            @endphp
                            <div class="rounded-lg border border-gray-100 px-3 py-2 dark:border-gray-800">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Consommation actuelle</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90">{{ number_format($currentConsumption, 0, ',', ' ') }} m³</p>
                            </div>
                            <div class="rounded-lg border border-gray-100 px-3 py-2 dark:border-gray-800">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Moyenne (historique vs prévue)</p>
                                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90">{{ number_format($avgHistorical, 0, ',', ' ') }} vs {{ number_format($avgForecast, 0, ',', ' ') }} m³</p>
                            </div>
                            <div class="rounded-lg border border-gray-100 px-3 py-2 dark:border-gray-800">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Tendance / MAE</p>
                                <p class="mt-1 text-sm font-semibold {{ $trend > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-success-600 dark:text-success-400' }}">
                                    {{ $trend > 0 ? '+' : '' }}{{ $trend }}% / {{ number_format($mae, 2, ',', ' ') }} m³
                                </p>
                            </div>
                        </div>

                        {{-- Forecast details --}}
                        @if ($forecastData->count() > 0)
                            <p class="mt-5 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Projections détaillées (6 mois)</p>
                            <div class="mt-2 grid grid-cols-3 gap-3">
                                @foreach ($forecastData as $point)
                                    <div class="rounded-lg border border-dashed border-brand-300 bg-brand-50/50 px-2.5 py-2 text-center dark:border-brand-800 dark:bg-brand-500/10">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $point['month'] }}</p>
                                        <p class="mt-1 text-xs font-semibold text-brand-600 dark:text-brand-400">{{ number_format($point['value'], 0, ',', ' ') }} m³</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">[{{ number_format($point['lower'], 0, ',', ' ') }}–{{ number_format($point['upper'], 0, ',', ' ') }}]</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <p class="mt-5 rounded-lg border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Données insuffisantes pour afficher le graphique. Au moins 12 mois historiques sont requis.</p>
                    @endif

                    <div class="mt-5 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Facteurs de risque</p>
                        <ul class="mt-2 space-y-1 text-xs text-gray-600 dark:text-gray-300">
                            @foreach ($risk['facteurs'] as $facteur)
                                <li class="flex gap-2">
                                    <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-gray-400"></span>
                                    {{ $facteur }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    @if ($historicalData->count() > 0 || $forecastData->count() > 0)
                        <script>
                            let chart{{ str_replace('-', '', $chartId) }} = null;

                            function updateChart{{ str_replace('-', '', $chartId) }}(windowMonths) {
                                // Guard: Verify Chart.js library is loaded
                                if (typeof Chart === 'undefined') {
                                    console.error('Chart.js library not loaded. Cannot render forecast chart.');
                                    return;
                                }

                                const canvas = document.getElementById('{{ $chartId }}');
                                if (!canvas) return;

                                const ctx = canvas.getContext('2d');
                                const isDarkMode = document.documentElement.classList.contains('dark');
                                
                                const textColor = isDarkMode ? '#9CA3AF' : '#6B7280';
                                const gridColor = isDarkMode ? 'rgba(75, 85, 99, 0.2)' : 'rgba(209, 213, 219, 0.5)';
                                const brandColor = '#2563EB';
                                const successColor = '#10B981';

                                // Raw data
                                const allHistoricalValues = {!! json_encode($historicalData->pluck('value')) !!};
                                const allForecastValues = {!! json_encode($forecastData->pluck('value')) !!};
                                const allLowerBounds = {!! json_encode($forecastData->pluck('lower')) !!};
                                const allUpperBounds = {!! json_encode($forecastData->pluck('upper')) !!};
                                const allLabels = {!! json_encode($allLabels) !!};

                                // Guard: Empty data fallback
                                if (allHistoricalValues.length === 0 && allForecastValues.length === 0) {
                                    console.warn('No forecast or historical data available for chart.');
                                    return;
                                }

                                // Windowing logic with bounds safety
                                const windowSize = parseInt(windowMonths) || 12;
                                const totalPoints = Math.max(1, allLabels.length);
                                let startIdx = Math.max(0, totalPoints - windowSize);

                                // Apply window with safe bounds
                                const labels = allLabels.slice(startIdx);
                                const historicalEnd = Math.min(startIdx + allHistoricalValues.length, allHistoricalValues.length);
                                const historicalValues = allHistoricalValues.slice(startIdx, historicalEnd);
                                const forecastStart = allHistoricalValues.length;
                                
                                // Build windowed datasets with explicit safety checks
                                const historicalLineData = [
                                    ...historicalValues,
                                    ...Array(Math.max(0, allForecastValues.length - Math.max(0, totalPoints - startIdx - historicalValues.length))).fill(null)
                                ];
                                
                                const forecastLineData = Array(historicalValues.length).fill(null);
                                if (forecastStart <= totalPoints - startIdx && allForecastValues.length > 0) {
                                    const forecastSliceStart = Math.max(0, startIdx - forecastStart);
                                    forecastLineData.push(...allForecastValues.slice(forecastSliceStart, forecastSliceStart + Math.max(0, totalPoints - startIdx - historicalValues.length)));
                                }

                                const lowerBandData = Array(historicalValues.length).fill(null);
                                if (forecastStart <= totalPoints - startIdx && allLowerBounds.length > 0) {
                                    const lowerSliceStart = Math.max(0, startIdx - forecastStart);
                                    lowerBandData.push(...allLowerBounds.slice(lowerSliceStart, lowerSliceStart + Math.max(0, totalPoints - startIdx - historicalValues.length)));
                                }

                                const upperBandData = Array(historicalValues.length).fill(null);
                                if (forecastStart <= totalPoints - startIdx && allUpperBounds.length > 0) {
                                    const upperSliceStart = Math.max(0, startIdx - forecastStart);
                                    upperBandData.push(...allUpperBounds.slice(upperSliceStart, upperSliceStart + Math.max(0, totalPoints - startIdx - historicalValues.length)));
                                }

                                // Destroy previous chart if exists
                                if (chart{{ str_replace('-', '', $chartId) }}) {
                                    chart{{ str_replace('-', '', $chartId) }}.destroy();
                                }

                                chart{{ str_replace('-', '', $chartId) }} = new Chart(ctx, {
                                    type: 'line',
                                    data: {
                                        labels: labels,
                                        datasets: [
                                            {
                                                label: 'Historique',
                                                data: historicalLineData,
                                                borderColor: brandColor,
                                                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                                                fill: false,
                                                borderWidth: 2,
                                                pointRadius: 4,
                                                pointBackgroundColor: brandColor,
                                                pointBorderColor: '#fff',
                                                pointBorderWidth: 2,
                                                tension: 0.4,
                                                segment: { resizeScale: true }
                                            },
                                            {
                                                label: 'Prévision',
                                                data: forecastLineData,
                                                borderColor: successColor,
                                                borderDash: [5, 5],
                                                fill: false,
                                                borderWidth: 2,
                                                pointRadius: 4,
                                                pointBackgroundColor: successColor,
                                                pointBorderColor: '#fff',
                                                pointBorderWidth: 2,
                                                tension: 0.4,
                                                segment: { resizeScale: true }
                                            },
                                            {
                                                label: 'Intervalle inférieur',
                                                data: lowerBandData,
                                                borderColor: 'transparent',
                                                fill: false,
                                                pointRadius: 0,
                                                borderWidth: 0
                                            },
                                            {
                                                label: 'Intervalle supérieur',
                                                data: upperBandData,
                                                borderColor: 'transparent',
                                                fill: '-1',
                                                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                                                pointRadius: 0,
                                                borderWidth: 0
                                            }
                                        ]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: true,
                                        interaction: { intersect: false, mode: 'index' },
                                        plugins: {
                                            legend: {
                                                display: true,
                                                position: 'top',
                                                labels: {
                                                    color: textColor,
                                                    font: { size: 12, family: 'system-ui, -apple-system' },
                                                    padding: 15,
                                                    usePointStyle: true,
                                                    filter: function(item) {
                                                        return !item.text.includes('Intervalle');
                                                    }
                                                }
                                            },
                                            tooltip: {
                                                backgroundColor: isDarkMode ? 'rgba(17, 24, 39, 0.8)' : 'rgba(255, 255, 255, 0.8)',
                                                titleColor: isDarkMode ? '#F3F4F6' : '#1F2937',
                                                bodyColor: isDarkMode ? '#D1D5DB' : '#4B5563',
                                                borderColor: isDarkMode ? 'rgba(75, 85, 99, 0.5)' : '#E5E7EB',
                                                borderWidth: 1,
                                                padding: 10,
                                                titleFont: { size: 13, weight: 'bold' },
                                                bodyFont: { size: 12 },
                                                displayColors: true,
                                                callbacks: {
                                                    label: function(context) {
                                                        if (context.dataset.label === 'Historique' || context.dataset.label === 'Prévision') {
                                                            return context.dataset.label + ': ' + Math.round(context.parsed.y) + ' m³';
                                                        }
                                                        return null;
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            x: {
                                                grid: { color: gridColor, drawBorder: false },
                                                ticks: { color: textColor, font: { size: 11 }, maxRotation: 45, minRotation: 0 }
                                            },
                                            y: {
                                                beginAtZero: true,
                                                grid: { color: gridColor, drawBorder: false },
                                                ticks: {
                                                    color: textColor,
                                                    font: { size: 11 },
                                                    callback: function(value) {
                                                        return Math.round(value).toLocaleString('fr-FR') + ' m³';
                                                    }
                                                },
                                                title: {
                                                    display: true,
                                                    text: 'Consommation (m³)',
                                                    color: textColor,
                                                    font: { size: 12, weight: 'bold' }
                                                }
                                            }
                                        }
                                    }
                                });
                            }

                            // Initialize chart on DOMContentLoaded
                            document.addEventListener('DOMContentLoaded', function() {
                                const select = document.getElementById('window-{{ $chartId }}');
                                if (select) {
                                    updateChart{{ str_replace('-', '', $chartId) }}(select.value);
                                }
                            });
                        </script>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
