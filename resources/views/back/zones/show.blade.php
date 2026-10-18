@extends('back.layouts.app')

@section('title', 'AquaSecure — Zone '.$zone->nom)

@section('content')

    <x-back.page-header :title="$zone->nom" :desc="$zone->commune.' ('.$zone->code_postal.')'">
        <x-slot:action>
            <a href="{{ route('back.zones.edit', $zone) }}"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
                Modifier
            </a>
            <x-back.confirm-delete :action="route('back.zones.destroy', $zone)"
                message="La zone « {{ $zone->nom }} » et toutes ses infrastructures seront supprimées." />
        </x-slot:action>
    </x-back.page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-back.stat-card label="Population" :value="number_format($zone->population, 0, ',', ' ')" />
        <x-back.stat-card label="Infrastructures" :value="$zone->infrastructures->count()" />
        <x-back.stat-card label="Coordonnées" :value="$zone->latitude && $zone->longitude ? $zone->latitude.', '.$zone->longitude : '—'" />
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Infrastructures de la zone</h2>
            <a href="{{ route('back.infrastructures.create') }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Ajouter une infrastructure</a>
        </div>

        @if ($zone->infrastructures->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Nom</th>
                            <th class="px-5 py-3 font-medium">Type</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($zone->infrastructures as $infrastructure)
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3">
                                    <a href="{{ route('back.infrastructures.show', $infrastructure) }}" class="text-sm font-medium text-brand-500 hover:underline dark:text-brand-400">{{ $infrastructure->nom }}</a>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $infrastructure->typeLabel() }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $infrastructure->statutLabel() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucune infrastructure" message="Cette zone ne contient encore aucune infrastructure." />
            </div>
        @endif
    </div>

    {{-- =====================================================================
         BLOC : Prévision du coût de maintenance (3 prochains mois)
         Chart.js n'étant pas présent dans le projet, affichage en tableau
         mois / coût / type (réel ou prévu).
         ===================================================================== --}}
    <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Prévision du coût de maintenance</h2>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Prévision indicative basée sur la moyenne des 3 derniers mois.</p>
        </div>

        @if ($historiqueBrut === [])
            <div class="p-5">
                <x-back.empty-state title="Aucun historique" message="Aucune maintenance terminée sur les 6 derniers mois pour cette zone : prévision indisponible." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Mois</th>
                            <th class="px-5 py-3 font-medium text-right">Coût (DT)</th>
                            <th class="px-5 py-3 font-medium">Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            /* Étiquette de mois en français (ex. : « sept. 2026 ») */
                            $libelleMois = fn (string $mois) => \Carbon\Carbon::createFromFormat('Y-m', $mois)->locale('fr')->translatedFormat('M Y');
                        @endphp

                        {{-- 6 mois réels (trait plein dans un graphique, ici lignes "Réel") --}}
                        @foreach ($historiqueBrut as $mois => $cout)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="px-5 py-3 text-sm text-gray-800 dark:text-white/90">{{ $libelleMois($mois) }}</td>
                                <td class="px-5 py-3 text-sm text-right text-gray-500 dark:text-gray-400">{{ number_format($cout, 2, ',', ' ') }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">Réel</span>
                                </td>
                            </tr>
                        @endforeach

                        {{-- 3 mois prévus (trait pointillé dans un graphique, ici lignes "Prévu") --}}
                        @foreach ($moisPrevus as $mois => $cout)
                            <tr class="border-b border-gray-100 last:border-0 bg-brand-50/40 dark:border-gray-800 dark:bg-brand-500/5">
                                <td class="px-5 py-3 text-sm text-gray-800 dark:text-white/90">{{ $libelleMois($mois) }}</td>
                                <td class="px-5 py-3 text-sm text-right text-gray-500 dark:text-gray-400">
                                    {{ $cout !== null ? number_format($cout, 2, ',', ' ') : '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">Prévu</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Total prévu pour les 3 prochains mois --}}
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 px-5 py-4 dark:border-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400">Prévision indicative basée sur la moyenne des 3 derniers mois.</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-white/90">
                    Coût prévu (3 mois) :
                    {{ $prevision !== null ? number_format($prevision, 2, ',', ' ').' DT' : '—' }}
                </p>
            </div>
        @endif
    </div>

@endsection
