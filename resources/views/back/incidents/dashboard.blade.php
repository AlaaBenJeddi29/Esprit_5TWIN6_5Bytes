@extends('back.layouts.app')

@section('title', 'AquaSecure — Indicateurs incidents')

@section('content')
    @php
        $inputClass = 'w-full rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $formatNumber = fn ($value, int $decimals = 0) => $value === null
            ? '—'
            : number_format((float) $value, $decimals, ',', ' ');
        $avgResolution = $summary['avg_resolution_h'] === null
            ? '—'
            : number_format((float) $summary['avg_resolution_h'], 1, ',', ' ').' h';
        $resolutionRate = $summary['resolution_rate'] === null
            ? '—'
            : number_format((float) $summary['resolution_rate'], 1, ',', ' ').' %';
        $maxTotal = max(1, (int) collect($summary['by_zone'])->max('total'));
        $grandTotal = (int) collect($summary['by_zone'])->sum('total');
        $pdfParams = array_filter([
            'period' => $currentPeriod,
            'from' => $currentPeriod === 'custom' ? $from : null,
            'to' => $currentPeriod === 'custom' ? $to : null,
        ], fn ($value) => $value !== null && $value !== '');
    @endphp

    <x-back.page-header title="Indicateurs incidents" desc="Suivi des incidents critiques, de la charge non affectée et de la qualité de traitement.">
        <x-slot:action>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('back.incidents.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    Voir les incidents
                </a>
                <a href="{{ route('back.incidents.dashboard.pdf', $pdfParams) }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                    Exporter en PDF
                </a>
            </div>
        </x-slot:action>
    </x-back.page-header>

    {{-- Sélecteur de période --}}
    <form
        method="GET"
        action="{{ route('back.incidents.dashboard') }}"
        x-data="{ period: '{{ $currentPeriod }}' }"
        class="mb-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="stats-period" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Période</label>
                <select id="stats-period" name="period" x-model="period" class="{{ $inputClass }}">
                    @foreach ($periods as $key => $label)
                        <option value="{{ $key }}" @selected($currentPeriod === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="period === 'custom'">
                <label for="stats-from" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Du</label>
                <input type="date" id="stats-from" name="from" value="{{ $from }}" class="{{ $inputClass }}">
            </div>

            <div x-show="period === 'custom'">
                <label for="stats-to" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Au</label>
                <input type="date" id="stats-to" name="to" value="{{ $to }}" class="{{ $inputClass }}">
            </div>

            <div class="flex items-end gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                    Actualiser
                </button>
                <a href="{{ route('back.incidents.dashboard') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    Réinitialiser
                </a>
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Période analysée : <span class="font-medium text-gray-700 dark:text-gray-300">{{ $periodLabel }}</span>
            @if ($from && $to)
                <span class="ml-2 font-mono">{{ $from }} → {{ $to }}</span>
            @endif
        </p>
    </form>

    {{-- Cartes KPI --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
        <x-back.stat-card
            label="Incidents critiques ouverts"
            :value="$formatNumber($summary['critical_open'])"
            hint="Critiques non clôturés sur la période"
        >
            <x-slot:action>
                <a href="{{ route('back.incidents.index', ['severity' => 'critique', 'open' => 1]) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </x-slot:action>
        </x-back.stat-card>

        <x-back.stat-card
            label="Incidents non affectés"
            :value="$formatNumber($summary['unassigned_open'])"
            hint="Ouverts, sans responsable, hors doublons"
        >
            <x-slot:action>
                <a href="{{ route('back.incidents.index', ['unassigned' => 1, 'open' => 1]) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </x-slot:action>
        </x-back.stat-card>

        <x-back.stat-card
            label="Délai moyen de résolution"
            :value="$avgResolution"
            hint="Incidents traités sur la période"
        >
            <x-slot:action>
                <a href="{{ route('back.incidents.index', ['status' => 'traite']) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </x-slot:action>
        </x-back.stat-card>

        <x-back.stat-card
            label="Taux de résolution"
            :value="$resolutionRate"
            hint="Traités / (total − rejetés − doublons)"
        >
            <x-slot:action>
                <a href="{{ route('back.incidents.index', ['status' => 'traite']) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </x-slot:action>
        </x-back.stat-card>
    </div>

    {{-- Tableau par zone --}}
    <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:px-6">
            <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Incidents par zone</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $periodLabel }}</p>
        </div>

        @if (empty($summary['by_zone']))
            <div class="p-5 md:p-6">
                <x-back.empty-state
                    title="Aucune zone enregistrée"
                    message="Créez des zones pour suivre la répartition des incidents."
                >
                    <x-slot:action>
                        <a href="{{ route('back.zones.create') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                            Créer une zone
                        </a>
                    </x-slot:action>
                </x-back.empty-state>
            </div>
        @else
            @if ($grandTotal === 0)
                <div class="border-b border-gray-100 px-5 py-3 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400 md:px-6">
                    Aucun incident signalé sur cette période.
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium md:px-6">Zone</th>
                            <th class="px-5 py-3 font-medium md:px-6">Total</th>
                            <th class="px-5 py-3 font-medium md:px-6">Ouverts</th>
                            <th class="px-5 py-3 font-medium md:px-6">Critiques</th>
                            <th class="px-5 py-3 font-medium md:px-6">Répartition</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['by_zone'] as $row)
                            @php
                                $width = $row['total'] > 0 ? round(($row['total'] / $maxTotal) * 100, 1) : 0;
                            @endphp
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="px-5 py-3.5 text-sm {{ $row['zone_id'] === null ? 'italic text-gray-500 dark:text-gray-400' : 'font-medium text-gray-800 dark:text-white/90' }} md:px-6">
                                    {{ $row['zone'] }}
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300 md:px-6">{{ $row['total'] }}</td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300 md:px-6">{{ $row['open'] }}</td>
                                <td class="px-5 py-3.5 text-sm {{ $row['critical'] > 0 ? 'font-semibold text-error-600 dark:text-error-400' : 'text-gray-600 dark:text-gray-300' }} md:px-6">
                                    {{ $row['critical'] }}
                                </td>
                                <td class="px-5 py-3.5 md:px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="h-2 w-full max-w-[220px] overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                            <div class="h-2 rounded-full bg-brand-500" style="width: {{ $width }}%"></div>
                                        </div>
                                        <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $row['total'] }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
        Le taux de résolution exclut les incidents rejetés et les doublons signalés du dénominateur.
        Le délai moyen est calculé entre la date de signalement et la date de résolution des incidents traités.
    </p>
@endsection
