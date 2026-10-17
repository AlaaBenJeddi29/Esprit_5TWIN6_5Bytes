@extends('back.layouts.app')

@section('title', 'AquaSecure — Tableau de bord')

@section('content')
    @php
        $statusTone = fn (string $status) => match ($status) {
            'nouveau' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            'en_cours' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
            'traite' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'rejete' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
        };

        $incidentTone = fn (string $status) => match ($status) {
            'nouveau' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            'en_cours' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
            'en_attente' => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
            'traite' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'rejete' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
        };
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
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 4.5v15m7.5-7.5h-15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Nouvel incident
            </a>
        </x-slot:action>
    </x-back.page-header>

    {{-- Indicateurs opérationnels --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
        <x-back.stat-card label="Incidents ouverts" :value="$openIncidents" :hint="$totalIncidents.' incident(s) au total'">
            <x-slot:action>
                <a href="{{ route('back.incidents.index') }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </x-slot:action>
        </x-back.stat-card>

        <x-back.stat-card label="Alertes qualité actives" :value="$activeQualityAlerts">
            <x-slot:action>
                <a href="{{ route('back.quality-alerts.index', ['statut' => 'active']) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </x-slot:action>
        </x-back.stat-card>

        <x-back.stat-card label="Restrictions actives" :value="$activeRestrictions">
            <x-slot:action>
                <a href="{{ route('back.restrictions.index', ['statut' => 'active']) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </x-slot:action>
        </x-back.stat-card>

        <x-back.stat-card label="Coupures à venir / en cours" :value="$upcomingCuts">
            <x-slot:action>
                <a href="{{ route('back.scheduled-cuts.index') }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </x-slot:action>
        </x-back.stat-card>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3 md:gap-6">
        <x-back.stat-card label="Prélèvements ce mois-ci" :value="$samplesThisMonth">
            <x-slot:action>
                <a href="{{ route('back.quality-samples.index') }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </x-slot:action>
        </x-back.stat-card>

        <x-back.stat-card label="Signalements (total)" :value="$totalReports" :hint="$pendingReports.' en attente'">
            <x-slot:action>
                <a href="{{ route('back.reports.index') }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
            </x-slot:action>
        </x-back.stat-card>

        <x-back.stat-card label="Utilisateurs inscrits" :value="$totalUsers" hint="Comptes d'authentification" />
    </div>

    <div class="mt-6 grid grid-cols-12 gap-4 md:gap-6">
        {{-- Derniers incidents --}}
        <div class="col-span-12 xl:col-span-7">
            <div class="h-full rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:px-6">
                    <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Derniers incidents</h2>
                    <a href="{{ route('back.incidents.index') }}" class="text-sm font-medium text-brand-500 hover:underline dark:text-brand-400">Tout voir</a>
                </div>

                @if ($latestIncidents->isNotEmpty())
                    <div class="max-w-full overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 md:px-6">Référence</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 md:px-6">Titre</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 md:px-6">Zone</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 md:px-6">Statut</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400 md:px-6">Signalé le</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($latestIncidents as $incident)
                                    <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                        <td class="px-5 py-3.5 text-sm font-medium text-gray-800 dark:text-white/90 md:px-6">
                                            <a href="{{ route('back.incidents.show', $incident) }}" class="hover:underline">{{ $incident->reference }}</a>
                                        </td>
                                        <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400 md:px-6">{{ $incident->title }}</td>
                                        <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400 md:px-6">{{ $incident->zone?->nom ?? '—' }}</td>
                                        <td class="px-5 py-3.5 md:px-6">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $incidentTone($incident->status) }}">
                                                {{ $incident->statusLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400 md:px-6">{{ $incident->reported_at?->format('d/m/Y H:i') ?? '—' }}</td>
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

        {{-- Répartition des signalements par statut --}}
        <div class="col-span-12 xl:col-span-5">
            <div class="h-full rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:px-6">
                    <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Signalements par statut</h2>
                    <a href="{{ route('back.reports.index') }}" class="text-sm font-medium text-brand-500 hover:underline dark:text-brand-400">Tout voir</a>
                </div>

                <div class="grid grid-cols-2 gap-4 p-5 md:p-6">
                    @foreach ($statuses as $key => $label)
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusTone($key) }}">
                                {{ $label }}
                            </span>
                            <p class="mt-3 text-title-sm font-bold text-gray-800 dark:text-white/90">
                                {{ $statusCounts[$key] ?? 0 }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Note de périmètre --}}
    <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-white p-5 dark:border-gray-700 dark:bg-white/[0.02] md:p-6">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Périmètre actuel</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            L'accès au Back Office est réservé aux rôles Gestionnaire et Administrateur (autorisation
            appliquée côté serveur). Les gestions Zones &amp; Infrastructures, Signalements citoyens,
            Qualité de l'eau et Sécheresse &amp; Restrictions sont opérationnelles. Les analyses
            (classification, conformité, prévision) utilisent des heuristiques locales, sans fournisseur
            d'IA externe.
        </p>
    </div>
@endsection
