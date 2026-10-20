@extends('back.layouts.app')

@section('title', 'AquaSecure — Tableau de bord')

@section('content')
    @php
        $incidentTone = fn (string $status) => match ($status) {
            'nouveau' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            'en_cours' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
            'en_attente' => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
            'traite' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'rejete' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
        };

        $statusDot = [
            'nouveau' => 'bg-warning-500',
            'en_cours' => 'bg-brand-500',
            'en_attente' => 'bg-gray-400',
            'traite' => 'bg-success-500',
            'rejete' => 'bg-error-500',
        ];

        $statusChart = $chartData['status'];
        $severityChart = $chartData['severity'];
        $trendChart = $chartData['trend'];

        $icons = [
            'incidents' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 9v3.75m0 3h.008M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.29 2.25h17.78a1.5 1.5 0 0 0 1.29-2.25L13.71 3.86a1.5 1.5 0 0 0-2.58 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'critical' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'unassigned' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'qualityAlert' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'restriction' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'cut' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'sample' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M9 12.75 11.25 15 15 9.75M21 12c0 4.556-3.72 8.25-8.31 8.25-2.02 0-3.88-.7-5.34-1.87L3 19.5l1.12-4.24A8.07 8.07 0 0 1 3 12c0-4.556 3.72-8.25 8.31-8.25S21 7.444 21 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'report' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'users' => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ];
    @endphp

    <x-back.page-header
        title="Tableau de bord"
        desc="Vue d'ensemble calculée à partir des données présentes en base."
    >
        <x-slot:action>
            <a
                href="{{ route('back.incidents.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 4.5v15m7.5-7.5h-15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Nouvel incident
            </a>
        </x-slot:action>
    </x-back.page-header>

    {{-- Indicateurs opérationnels prioritaires --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-5 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] md:p-5">
            <div class="flex items-start justify-between gap-2">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
                    {!! $icons['incidents'] !!}
                </div>
                <a href="{{ route('back.incidents.index') }}" class="text-theme-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </div>
            <p class="mt-4 text-theme-sm text-gray-500 dark:text-gray-400">Incidents ouverts</p>
            <p class="mt-1 text-title-sm font-bold text-gray-800 dark:text-white/90">{{ $openIncidents }}</p>
            <p class="mt-1 text-theme-xs text-gray-400 dark:text-gray-500">{{ $totalIncidents }} incident(s) au total</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] md:p-5">
            <div class="flex items-start justify-between gap-2">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
                    {!! $icons['critical'] !!}
                </div>
                <a href="{{ route('back.incidents.index', ['severity' => 'critique', 'open' => 1]) }}" class="text-theme-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </div>
            <p class="mt-4 text-theme-sm text-gray-500 dark:text-gray-400">Incidents critiques</p>
            <p class="mt-1 text-title-sm font-bold text-gray-800 dark:text-white/90">{{ $criticalOpenIncidents }}</p>
            <p class="mt-1 text-theme-xs text-gray-400 dark:text-gray-500">Gravité critique, non clôturés</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] md:p-5">
            <div class="flex items-start justify-between gap-2">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
                    {!! $icons['unassigned'] !!}
                </div>
                <a href="{{ route('back.incidents.index', ['unassigned' => 1, 'open' => 1]) }}" class="text-theme-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </div>
            <p class="mt-4 text-theme-sm text-gray-500 dark:text-gray-400">Non affectés</p>
            <p class="mt-1 text-title-sm font-bold text-gray-800 dark:text-white/90">{{ $unassignedOpenIncidents }}</p>
            <p class="mt-1 text-theme-xs text-gray-400 dark:text-gray-500">Ouverts, sans responsable, hors doublons</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] md:p-5">
            <div class="flex items-start justify-between gap-2">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
                    {!! $icons['qualityAlert'] !!}
                </div>
                <a href="{{ route('back.quality-alerts.index', ['statut' => 'active']) }}" class="text-theme-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </div>
            <p class="mt-4 text-theme-sm text-gray-500 dark:text-gray-400">Alertes qualité actives</p>
            <p class="mt-1 text-title-sm font-bold text-gray-800 dark:text-white/90">{{ $activeQualityAlerts }}</p>
            <p class="mt-1 text-theme-xs text-gray-400 dark:text-gray-500">{{ $samplesThisMonth }} prélèvement(s) ce mois-ci</p>
        </div>
    </div>

    {{-- Indicateurs secondaires --}}
    <div class="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] md:mt-5">
        <div class="grid grid-cols-1 sm:grid-cols-3 xl:grid-cols-5">
            <a href="{{ route('back.restrictions.index', ['statut' => 'active']) }}" class="flex items-center gap-3 p-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.02] xl:border-l xl:border-gray-100 xl:first:border-l-0 xl:dark:border-gray-800">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
                    {!! $icons['restriction'] !!}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-theme-xs text-gray-500 dark:text-gray-400">Restrictions actives</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-800 dark:text-white/90">{{ $activeRestrictions }}</p>
                </div>
            </a>

            <a href="{{ route('back.scheduled-cuts.index') }}" class="flex items-center gap-3 p-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.02] xl:border-l xl:border-gray-100 xl:dark:border-gray-800">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
                    {!! $icons['cut'] !!}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-theme-xs text-gray-500 dark:text-gray-400">Coupures programmées</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-800 dark:text-white/90">{{ $upcomingCuts }}</p>
                </div>
            </a>

            <a href="{{ route('back.quality-samples.index') }}" class="flex items-center gap-3 p-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.02] xl:border-l xl:border-gray-100 xl:dark:border-gray-800">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
                    {!! $icons['sample'] !!}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-theme-xs text-gray-500 dark:text-gray-400">Prélèvements ce mois-ci</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-800 dark:text-white/90">{{ $samplesThisMonth }}</p>
                </div>
            </a>

            <a href="{{ route('back.reports.index') }}" class="flex items-center gap-3 p-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.02] xl:border-l xl:border-gray-100 xl:dark:border-gray-800">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
                    {!! $icons['report'] !!}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-theme-xs text-gray-500 dark:text-gray-400">Signalements citoyens</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-800 dark:text-white/90">{{ $totalReports }}</p>
                    <p class="text-theme-xs text-gray-400 dark:text-gray-500">{{ $pendingReports }} en attente</p>
                </div>
            </a>

            <div class="flex items-center gap-3 p-4 xl:border-l xl:border-gray-100 xl:dark:border-gray-800">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
                    {!! $icons['users'] !!}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-theme-xs text-gray-500 dark:text-gray-400">Utilisateurs inscrits</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-800 dark:text-white/90">{{ $totalUsers }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Graphiques calculés sur les incidents --}}
    <div id="dashboard-charts" data-dashboard-charts class="mt-4 grid grid-cols-12 gap-4 md:mt-5 md:gap-5">
        <script type="application/json" id="dashboard-chart-data">@json($chartData)</script>

        <div class="col-span-12 rounded-2xl border border-gray-200 bg-white px-5 pb-5 pt-5 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6 sm:pt-6 xl:col-span-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Incidents par statut</h2>
                    <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Tous les incidents enregistrés</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    {{ $statusChart['total'] }} au total
                </span>
            </div>

            @if ($statusChart['total'] > 0)
                <div class="mx-auto mt-5 h-[190px] w-[190px]">
                    <canvas id="incidentStatusChart" role="img" aria-label="Répartition des incidents par statut"></canvas>
                </div>

                <ul class="mt-5 space-y-2">
                    @foreach ($statusChart['labels'] as $key => $label)
                        @php
                            $count = $statusChart['counts'][$key] ?? 0;
                            $percent = (int) round(($count / $statusChart['total']) * 100);
                        @endphp
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-theme-sm text-gray-600 dark:text-gray-400">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $statusDot[$key] ?? 'bg-gray-300' }}"></span>
                                {{ $label }}
                            </span>
                            <span class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">
                                {{ $count }}
                                <span class="ml-1 font-normal text-gray-400 dark:text-gray-500">({{ $percent }} %)</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="mt-5 flex h-[280px] items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 text-center text-theme-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400">
                    Aucun incident enregistré pour le moment.
                </div>
            @endif
        </div>

        <div class="col-span-12 rounded-2xl border border-gray-200 bg-white px-5 pb-5 pt-5 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6 sm:pt-6 xl:col-span-8">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tendance des incidents</h2>
                    <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Signalements reçus sur les 12 derniers mois</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    {{ $trendChart['total'] }} sur la période
                </span>
            </div>

            @if ($trendChart['total'] > 0)
                <div class="mt-5 h-[320px] w-full">
                    <canvas id="incidentTrendChart" role="img" aria-label="Nombre d'incidents signalés par mois sur les 12 derniers mois"></canvas>
                </div>
            @else
                <div class="mt-5 flex h-[320px] items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 text-center text-theme-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400">
                    Aucun incident signalé sur les 12 derniers mois.
                </div>
            @endif
        </div>

        <div class="col-span-12 rounded-2xl border border-gray-200 bg-white px-5 pb-5 pt-5 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6 sm:pt-6 xl:col-span-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Gravité des incidents ouverts</h2>
                    <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Incidents non clôturés</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    {{ $severityChart['total'] }} ouvert(s)
                </span>
            </div>

            @if ($severityChart['total'] > 0)
                <div class="mt-5 h-[280px] w-full">
                    <canvas id="incidentSeverityChart" role="img" aria-label="Répartition des incidents ouverts par gravité"></canvas>
                </div>
            @else
                <div class="mt-5 flex h-[280px] items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 text-center text-theme-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400">
                    Aucun incident ouvert actuellement.
                </div>
            @endif
        </div>

        {{-- Derniers incidents --}}
        <div class="col-span-12 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] xl:col-span-8">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:px-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Derniers incidents</h2>
                <a href="{{ route('back.incidents.index') }}" class="text-theme-sm font-medium text-brand-500 hover:underline dark:text-brand-400">Tout voir</a>
            </div>

            @if ($latestIncidents->isNotEmpty())
                <div class="max-w-full overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400 md:px-6">Référence</th>
                                <th class="px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400 md:px-6">Titre</th>
                                <th class="hidden px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400 md:table-cell md:px-6">Zone</th>
                                <th class="px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400 md:px-6">Statut</th>
                                <th class="hidden px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400 sm:table-cell md:px-6">Signalé le</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($latestIncidents as $incident)
                                <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="whitespace-nowrap px-5 py-3.5 text-theme-sm font-medium text-gray-800 dark:text-white/90 md:px-6">
                                        <a href="{{ route('back.incidents.show', $incident) }}" class="hover:underline">{{ $incident->reference }}</a>
                                    </td>
                                    <td class="px-5 py-3.5 text-theme-sm text-gray-500 dark:text-gray-400 md:px-6">{{ $incident->title }}</td>
                                    <td class="hidden px-5 py-3.5 text-theme-sm text-gray-500 dark:text-gray-400 md:table-cell md:px-6">{{ $incident->zone?->nom ?? '—' }}</td>
                                    <td class="px-5 py-3.5 md:px-6">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-theme-xs font-medium {{ $incidentTone($incident->status) }}">
                                            {{ $incident->statusLabel() }}
                                        </span>
                                    </td>
                                    <td class="hidden whitespace-nowrap px-5 py-3.5 text-theme-sm text-gray-500 dark:text-gray-400 sm:table-cell md:px-6">{{ $incident->reported_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5 md:p-6">
                    <x-back.empty-state
                        title="Aucun incident enregistré"
                        message="Les nouveaux incidents apparaîtront ici."
                    >
                        <x-slot:action>
                            <a href="{{ route('back.incidents.create') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                                Créer un incident
                            </a>
                        </x-slot:action>
                    </x-back.empty-state>
                </div>
            @endif
        </div>
    </div>

    {{-- Note de périmètre --}}
    <div class="mt-4 rounded-xl border border-dashed border-gray-300 px-4 py-3 dark:border-gray-700 md:mt-5">
        <p class="text-theme-xs text-gray-500 dark:text-gray-400">
            <span class="font-medium text-gray-700 dark:text-gray-300">Périmètre actuel :</span>
            accès réservé aux rôles Gestionnaire et Administrateur (autorisation appliquée côté serveur).
            Les analyses (classification, conformité, prévision) utilisent des heuristiques locales, sans fournisseur d'IA externe.
            <span class="text-gray-400 dark:text-gray-500">Interface adaptée du template TailAdmin (Laravel).</span>
        </p>
    </div>
@endsection
