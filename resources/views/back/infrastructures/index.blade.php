@extends('back.layouts.app')

@section('title', 'AquaSecure — Infrastructures')

@section('content')
    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
        $hasFilters = $search !== '' || $currentType !== '' || $currentStatut !== '' || $currentZone !== null;
    @endphp

    <x-back.page-header title="Infrastructures" desc="Canalisations, réservoirs, stations et autres ouvrages du réseau.">
        <x-slot:action>
            <a href="{{ route('back.infrastructures.create') }}"
               class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                Nouvelle infrastructure
            </a>
        </x-slot:action>
    </x-back.page-header>

    <form method="GET" action="{{ route('back.infrastructures.index') }}" class="mb-5 flex flex-wrap gap-2">
        <input type="search" name="search" value="{{ $search }}" placeholder="Nom ou matériau…" class="w-full max-w-xs {{ $inputClass }}">
        <select name="type" class="{{ $inputClass }}">
            <option value="">Tous les types</option>
            @foreach ($types as $option)
                <option value="{{ $option }}" @selected($currentType === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
            @endforeach
        </select>
        <select name="statut" class="{{ $inputClass }}">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $option)
                <option value="{{ $option }}" @selected($currentStatut === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
            @endforeach
        </select>
        <select name="zone_id" class="{{ $inputClass }}">
            <option value="">Toutes les zones</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <button type="submit"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
            Filtrer
        </button>
        @if ($hasFilters)
            <a href="{{ route('back.infrastructures.index') }}" class="inline-flex items-center justify-center rounded-lg px-3 py-2.5 text-sm font-medium text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                Réinitialiser
            </a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($infrastructures->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Nom</th>
                            <th class="px-5 py-3 font-medium">Zone</th>
                            <th class="px-5 py-3 font-medium">Type</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium">Score de risque</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($infrastructures as $infrastructure)
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $infrastructure->nom }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $infrastructure->zone?->nom ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $infrastructure->typeLabel() }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $infrastructure->statutLabel() }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $infrastructure->score_risque }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.infrastructures.show', $infrastructure) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
                                        <a href="{{ route('back.infrastructures.edit', $infrastructure) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                        <x-back.confirm-delete :action="route('back.infrastructures.destroy', $infrastructure)"
                                            message="L'infrastructure « {{ $infrastructure->nom }} » et ses maintenances seront supprimées." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-back.pagination :paginator="$infrastructures" label="infrastructures" />
        @else
            <div class="p-5">
                <x-back.empty-state
                    title="Aucune infrastructure"
                    message="{{ $hasFilters ? 'Aucune infrastructure ne correspond aux filtres appliqués.' : 'Créez une première infrastructure.' }}"
                >
                    <x-slot:action>
                        <a href="{{ route('back.infrastructures.create') }}"
                           class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Créer une infrastructure</a>
                    </x-slot:action>
                </x-back.empty-state>
            </div>
        @endif
    </div>

@endsection
