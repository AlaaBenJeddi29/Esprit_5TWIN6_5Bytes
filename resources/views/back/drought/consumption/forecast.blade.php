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

    <x-back.page-header title="Prévisions de consommation" desc="Projection à trois mois par régression linéaire et indice de risque sécheresse. Heuristiques locales, sans service externe.">
        <x-slot:action>
            <a href="{{ route('back.consumption-readings.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Relevés</a>
        </x-slot:action>
    </x-back.page-header>

    <form method="GET" class="mb-5 flex flex-wrap gap-2">
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
                    $max = collect($forecast['history'])->merge($forecast['forecast'])->max('valeur') ?: 1;
                @endphp
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $zone->nom }}</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $forecast['message'] }}</p>
                        </div>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $riskTone($risk['niveau']) }}">
                            Risque {{ $riskLabel($risk['niveau']) }} · {{ $risk['score'] }}/100
                        </span>
                    </div>

                    @if ($forecast['forecast'] !== [])
                        <div class="mt-5 space-y-3">
                            @foreach ($forecast['history'] as $point)
                                <div class="flex items-center gap-3">
                                    <span class="w-16 shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $point['label'] }}</span>
                                    <div class="h-2.5 flex-1 rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div class="h-2.5 rounded-full bg-brand-500" style="width: {{ max(2, ($point['valeur'] / $max) * 100) }}%"></div>
                                    </div>
                                    <span class="w-24 shrink-0 text-right text-xs text-gray-600 dark:text-gray-300">{{ number_format($point['valeur'], 0, ',', ' ') }} m³</span>
                                </div>
                            @endforeach
                        </div>

                        <p class="mt-5 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Projection</p>
                        <div class="mt-2 grid grid-cols-3 gap-3">
                            @foreach ($forecast['forecast'] as $point)
                                <div class="rounded-lg border border-dashed border-brand-300 bg-brand-50/50 px-3 py-2 text-center dark:border-brand-800 dark:bg-brand-500/10">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $point['label'] }}</p>
                                    <p class="mt-1 text-sm font-semibold text-brand-600 dark:text-brand-400">{{ number_format($point['valeur'], 0, ',', ' ') }} m³</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-5 rounded-lg border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">{{ $forecast['message'] }}</p>
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
                </div>
            @endforeach
        </div>
    @endif
@endsection
