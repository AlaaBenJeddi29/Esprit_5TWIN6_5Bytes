@extends('back.layouts.app')

@section('title', 'AquaSecure — Carte des incidents')

@section('content')
    @php
        $inputClass = 'w-full rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $legend = [
            'critique' => ['label' => 'Critique', 'color' => '#991b1b'],
            'haute' => ['label' => 'Haute', 'color' => '#f97316'],
            'moyenne' => ['label' => 'Moyenne', 'color' => '#eab308'],
            'faible' => ['label' => 'Faible', 'color' => '#16a34a'],
        ];
    @endphp

    <x-back.page-header title="Carte des incidents" desc="Repérez les signalements géolocalisés et les concentrations d'incidents critiques.">
        <x-slot:action>
            <a href="{{ route('back.incidents.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                Voir tous les incidents
            </a>
        </x-slot:action>
    </x-back.page-header>

    <div
        data-incident-map
        data-endpoint="{{ route('back.incidents.map.data') }}"
        data-default-radius="{{ $defaultRadius }}"
    >
        {{-- Compteurs --}}
        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Incidents affichés</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white" data-count="displayed">0</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Incidents critiques</p>
                <p class="mt-1 text-2xl font-semibold text-error-600 dark:text-error-400" data-count="critical">0</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Non résolus</p>
                <p class="mt-1 text-2xl font-semibold text-warning-600 dark:text-warning-400" data-count="unresolved">0</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Groupes critiques</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white" data-count="clusters">0</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Sans coordonnées</p>
                <p class="mt-1 text-2xl font-semibold text-gray-500 dark:text-gray-400" data-count="unplaced">0</p>
            </div>
        </div>

        {{-- Filtres --}}
        <form novalidate
            method="GET"
            action="{{ route('back.incidents.map') }}"
            data-incident-map-filters
            class="mb-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="map-severity" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Gravité</label>
                    <select id="map-severity" name="severity" class="{{ $inputClass }}">
                        <option value="">Toutes les gravités</option>
                        @foreach ($severities as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="map-status" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Statut</label>
                    <select id="map-status" name="status" class="{{ $inputClass }}">
                        <option value="">Tous les statuts</option>
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="map-zone" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Zone</label>
                    <select id="map-zone" name="zone_id" class="{{ $inputClass }}">
                        <option value="">Toutes les zones</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->nom }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="map-infrastructure" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Infrastructure</label>
                    <select id="map-infrastructure" name="infrastructure_id" class="{{ $inputClass }}">
                        <option value="">Toutes les infrastructures</option>
                        @foreach ($infrastructures as $infrastructure)
                            <option value="{{ $infrastructure->id }}">
                                {{ $infrastructure->nom }}@if ($infrastructure->zone) — {{ $infrastructure->zone->nom }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="map-date-from" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Signalé du</label>
                    <input type="date" id="map-date-from" name="date_from" class="{{ $inputClass }}">
                </div>

                <div>
                    <label for="map-date-to" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Signalé au</label>
                    <input type="date" id="map-date-to" name="date_to" class="{{ $inputClass }}">
                </div>

                <div>
                    <label for="map-radius" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Rayon de vigilance (m)</label>
                    <input
                        type="number"
                        id="map-radius"
                        name="radius"
                        value="{{ $defaultRadius }}"
                        min="{{ $minRadius }}"
                        max="{{ $maxRadius }}"
                        step="50"
                        class="{{ $inputClass }}"
                    >
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Entre {{ $minRadius }} et {{ $maxRadius }} mètres.</p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                    Appliquer les filtres
                </button>
                <button type="button" data-reset-filters class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    Réinitialiser
                </button>
                <p data-map-status aria-live="polite" class="text-xs text-gray-500 dark:text-gray-400">Chargement des incidents…</p>
            </div>
        </form>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
            {{-- Carte --}}
            <div class="xl:col-span-2">
                <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div
                        data-incident-map-canvas
                        class="h-[60vh] min-h-[420px] w-full"
                        aria-label="Carte interactive des incidents"
                    ></div>

                    <div data-map-loading class="absolute inset-0 z-[1000] flex items-center justify-center bg-white/70 dark:bg-gray-900/70">
                        <div class="flex items-center gap-3 rounded-lg bg-white px-4 py-3 shadow-theme-md dark:bg-gray-800">
                            <span class="h-5 w-5 animate-spin rounded-full border-2 border-brand-500 border-t-transparent" aria-hidden="true"></span>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Chargement de la carte…</span>
                        </div>
                    </div>

                    <div data-map-empty class="pointer-events-none absolute inset-0 z-[900] hidden">
                        <div class="flex h-full items-center justify-center">
                            <div class="max-w-sm rounded-lg bg-white/95 p-5 text-center shadow-theme-md dark:bg-gray-800/95">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Aucun incident géolocalisé</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Modifiez les filtres ou vérifiez que les signalements possèdent des coordonnées GPS.</p>
                            </div>
                        </div>
                    </div>

                    <div data-map-error class="absolute inset-x-4 bottom-4 z-[1100] hidden rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                        <p data-map-error-message>Impossible de charger les incidents. Réessayez.</p>
                    </div>
                </div>

                {{-- Légende --}}
                <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-xs text-gray-600 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
                    <span class="font-semibold text-gray-700 dark:text-gray-300">Légende :</span>
                    @foreach ($legend as $item)
                        <span class="inline-flex items-center gap-1.5">
                            <span class="inline-block h-3 w-3 rounded-full border border-white shadow" style="background-color: {{ $item['color'] }}"></span>
                            {{ $item['label'] }}
                        </span>
                    @endforeach
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border-2 border-[#7f1d1d] bg-[#b91c1c] text-[10px] font-bold text-white">2</span>
                        Concentration critique
                    </span>
                    <span>La couleur indique la gravité ; le statut est affiché dans chaque popup.</span>
                </div>
            </div>

            {{-- Zones de vigilance --}}
            <aside class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Zones de vigilance</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Groupes d'au moins deux incidents critiques situés à moins de {{ $defaultRadius }} mètres les uns des autres.
                </p>

                <div data-cluster-list class="mt-4 max-h-[58vh] space-y-3 overflow-y-auto">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Chargement des concentrations…</p>
                </div>
            </aside>
        </div>

        <noscript>
            <div class="mt-4 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                La carte interactive nécessite JavaScript. Vous pouvez consulter la liste complète des incidents depuis le menu « Incidents ».
            </div>
        </noscript>
    </div>
@endsection
