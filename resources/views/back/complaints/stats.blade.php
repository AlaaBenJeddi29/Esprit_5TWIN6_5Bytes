@extends('back.layouts.app')

@section('title', 'AquaSecure — Indicateurs de satisfaction')

@section('content')
    @php
        $inputClass = 'w-full rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $formatNumber = fn ($value, int $decimals = 0) => $value === null
            ? '—'
            : number_format((float) $value, $decimals, ',', ' ');
        $average = $summary['average_rating'] === null
            ? '—'
            : number_format((float) $summary['average_rating'], 2, ',', ' ').' / 5';
        $acceptanceRate = $summary['acceptance_rate'] === null
            ? '—'
            : number_format((float) $summary['acceptance_rate'], 1, ',', ' ').' %';
        $totalEvaluations = max(1, (int) $summary['total_feedback']);
        $maxZoneTotal = max(1, (int) collect($summary['by_zone'])->max('total'));
    @endphp

    <x-back.page-header title="Indicateurs de satisfaction" desc="Évaluations citoyennes, réclamations et réouvertures sur la période sélectionnée.">
        <x-slot:action>
            <a href="{{ route('back.complaints.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                Voir les réclamations
            </a>
        </x-slot:action>
    </x-back.page-header>

    {{-- Période --}}
    <form
        method="GET"
        action="{{ route('back.complaints.stats') }}"
        x-data="{ period: '{{ $currentPeriod }}' }"
        class="mb-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="satisfaction-period" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Période</label>
                <select id="satisfaction-period" name="period" x-model="period" class="{{ $inputClass }}">
                    @foreach ($periods as $key => $label)
                        <option value="{{ $key }}" @selected($currentPeriod === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="period === 'custom'">
                <label for="satisfaction-from" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Du</label>
                <input type="date" id="satisfaction-from" name="from" value="{{ $from }}" class="{{ $inputClass }}">
            </div>

            <div x-show="period === 'custom'">
                <label for="satisfaction-to" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Au</label>
                <input type="date" id="satisfaction-to" name="to" value="{{ $to }}" class="{{ $inputClass }}">
            </div>

            <div class="flex items-end gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Actualiser</button>
                <a href="{{ route('back.complaints.stats') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Réinitialiser</a>
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Période analysée : <span class="font-medium text-gray-700 dark:text-gray-300">{{ $periodLabel }}</span>
            @if ($from && $to)
                <span class="ml-2 font-mono">{{ $from }} → {{ $to }}</span>
            @endif
        </p>
    </form>

    {{-- KPI --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-3">
        <x-back.stat-card
            label="Évaluations déposées"
            :value="$formatNumber($summary['total_feedback'])"
            hint="Signalements traités évalués sur la période"
        />
        <x-back.stat-card
            label="Note moyenne"
            :value="$average"
            hint="Somme des notes / nombre d'évaluations"
        />
        <x-back.stat-card
            label="Résolutions confirmées"
            :value="$summary['resolution_confirmation_rate'] === null ? '—' : $formatNumber($summary['resolution_confirmation_rate'], 1).' %'"
            :hint="$formatNumber($summary['resolution_confirmed']).' évaluation(s) avec problème résolu'"
        />
        <x-back.stat-card
            label="Réclamations déposées"
            :value="$formatNumber($summary['complaints_total'])"
            :hint="$formatNumber($summary['complaints_pending']).' en attente · '.$formatNumber($summary['complaints_in_review']).' en examen'"
        >
            <x-slot:action>
                <a href="{{ route('back.complaints.index', ['status' => 'en_attente']) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </x-slot:action>
        </x-back.stat-card>
        <x-back.stat-card
            label="Taux d'acceptation"
            :value="$acceptanceRate"
            :hint="$formatNumber($summary['complaints_accepted']).' acceptée(s) / '.$formatNumber($summary['complaints_accepted'] + $summary['complaints_rejected']).' décision(s)'"
        />
        <x-back.stat-card
            label="Réouvertures acceptées"
            :value="$formatNumber($summary['reopen_accepted'])"
            :hint="$formatNumber($summary['reopen_requested']).' demande(s) de réouverture'"
        />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        {{-- Répartition des notes --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:px-6">
                <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Répartition des notes</h2>
            </div>
            <div class="space-y-4 p-5 md:p-6">
                @for ($rating = 5; $rating >= 1; $rating--)
                    @php
                        $count = (int) ($summary['rating_distribution'][$rating] ?? 0);
                        $width = $summary['total_feedback'] > 0 ? round(($count / $totalEvaluations) * 100, 1) : 0;
                    @endphp
                    <div class="flex items-center gap-3">
                        <span class="w-16 shrink-0 text-sm text-gray-600 dark:text-gray-300">{{ $rating }} ★</span>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-2.5 rounded-full bg-warning-400" style="width: {{ $width }}%"></div>
                        </div>
                        <span class="w-16 shrink-0 text-right text-xs text-gray-500 dark:text-gray-400">{{ $count }} · {{ number_format($width, 1, ',', ' ') }} %</span>
                    </div>
                @endfor

                @if ($summary['total_feedback'] === 0)
                    <p class="text-sm text-gray-500 dark:text-gray-400">Aucune évaluation sur cette période.</p>
                @endif
            </div>
        </div>

        {{-- Évolution --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:px-6">
                <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Évolution de la satisfaction</h2>
            </div>
            @if (empty($summary['evolution']))
                <div class="p-5 md:p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Aucune évaluation sur cette période.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                <th class="px-5 py-3 font-medium md:px-6">Mois</th>
                                <th class="px-5 py-3 font-medium md:px-6">Évaluations</th>
                                <th class="px-5 py-3 font-medium md:px-6">Note moyenne</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['evolution'] as $row)
                                <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                    <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300 md:px-6">{{ $row['label'] }}</td>
                                    <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300 md:px-6">{{ $row['total'] }}</td>
                                    <td class="px-5 py-3.5 text-sm font-medium text-gray-800 dark:text-white/90 md:px-6">
                                        {{ $row['average_rating'] === null ? '—' : number_format((float) $row['average_rating'], 2, ',', ' ').' / 5' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Satisfaction par zone --}}
    <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:px-6">
            <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Satisfaction par zone</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $periodLabel }}</p>
        </div>

        @if (empty($summary['by_zone']))
            <div class="p-5 md:p-6">
                <x-back.empty-state title="Aucune zone enregistrée" message="Créez des zones pour suivre la satisfaction par secteur." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium md:px-6">Zone</th>
                            <th class="px-5 py-3 font-medium md:px-6">Évaluations</th>
                            <th class="px-5 py-3 font-medium md:px-6">Note moyenne</th>
                            <th class="px-5 py-3 font-medium md:px-6">Réclamations</th>
                            <th class="px-5 py-3 font-medium md:px-6">Répartition</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['by_zone'] as $row)
                            @php
                                $width = $row['total'] > 0 ? round(($row['total'] / $maxZoneTotal) * 100, 1) : 0;
                            @endphp
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="px-5 py-3.5 text-sm {{ $row['zone_id'] === null ? 'italic text-gray-500 dark:text-gray-400' : 'font-medium text-gray-800 dark:text-white/90' }} md:px-6">
                                    {{ $row['zone'] }}
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300 md:px-6">{{ $row['total'] }}</td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300 md:px-6">
                                    {{ $row['average_rating'] === null ? '—' : number_format((float) $row['average_rating'], 2, ',', ' ').' / 5' }}
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-300 md:px-6">{{ $row['complaints'] }}</td>
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

    <div class="mt-4 space-y-1 text-xs text-gray-500 dark:text-gray-400">
        <p>Note moyenne = somme des notes / nombre d'évaluations déposées sur la période (— si aucune évaluation).</p>
        <p>Taux d'acceptation = réclamations acceptées / (acceptées + refusées) × 100. Les dossiers en attente ou en examen sont exclus ; — si aucune décision n'a été rendue.</p>
        <p>Réouvertures acceptées = demandes ayant effectivement déclenché une réouverture de l'incident. La satisfaction et le taux d'acceptation sont deux indicateurs distincts.</p>
    </div>
@endsection
