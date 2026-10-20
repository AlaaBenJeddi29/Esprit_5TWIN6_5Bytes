@extends('back.layouts.app')

@section('title', 'AquaSecure — Maintenances')

@section('content')
    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
        $hasFilters = $search !== '' || $currentStatut !== '' || $currentInfrastructure !== null
            || $currentTechnicien !== null || $from !== null || $to !== null;
    @endphp

    <x-back.page-header title="Maintenances" desc="Interventions planifiées ou réalisées sur les infrastructures.">
        <x-slot:action>
            <a href="{{ route('back.maintenances.create') }}"
               class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                Nouvelle maintenance
            </a>
        </x-slot:action>
    </x-back.page-header>

    <form novalidate method="GET" action="{{ route('back.maintenances.index') }}" class="mb-5 flex flex-wrap gap-2">
        <input type="search" name="search" value="{{ $search }}" placeholder="Type, description ou technicien…" class="w-full max-w-xs {{ $inputClass }}">
        <select name="statut" class="{{ $inputClass }}">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $option)
                <option value="{{ $option }}" @selected($currentStatut === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
            @endforeach
        </select>
        <select name="infrastructure_id" class="{{ $inputClass }}">
            <option value="">Toutes les infrastructures</option>
            @foreach ($infrastructures as $infrastructure)
                <option value="{{ $infrastructure->id }}" @selected($currentInfrastructure === $infrastructure->id)>{{ $infrastructure->nom }}</option>
            @endforeach
        </select>
        <select name="technicien_id" class="{{ $inputClass }}">
            <option value="">Tous les techniciens</option>
            @foreach ($techniciens as $technicien)
                <option value="{{ $technicien->id }}" @selected($currentTechnicien === $technicien->id)>{{ $technicien->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $from }}" aria-label="Interventions à partir du" title="Interventions à partir du" class="{{ $inputClass }}">
        <input type="date" name="to" value="{{ $to }}" aria-label="Interventions jusqu'au" title="Interventions jusqu'au" class="{{ $inputClass }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <button type="submit"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
            Filtrer
        </button>
        @if ($hasFilters)
            <a href="{{ route('back.maintenances.index') }}" class="inline-flex items-center justify-center rounded-lg px-3 py-2.5 text-sm font-medium text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                Réinitialiser
            </a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($maintenances->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Infrastructure</th>
                            <th class="px-5 py-3 font-medium">Type</th>
                            <th class="px-5 py-3 font-medium">Date</th>
                            <th class="px-5 py-3 font-medium">Coût</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($maintenances as $maintenance)
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $maintenance->infrastructure?->nom ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $maintenance->type }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $maintenance->date_intervention?->format('d/m/Y') }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $maintenance->cout !== null ? number_format($maintenance->cout, 2, ',', ' ').' €' : '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $maintenance->statut }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.maintenances.show', $maintenance) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
                                        <a href="{{ route('back.maintenances.edit', $maintenance) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                        <x-back.confirm-delete :action="route('back.maintenances.destroy', $maintenance)"
                                            message="Cette maintenance sera définitivement supprimée." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-back.pagination :paginator="$maintenances" label="maintenances" />
        @else
            <div class="p-5">
                <x-back.empty-state
                    title="Aucune maintenance"
                    message="{{ $hasFilters ? 'Aucune maintenance ne correspond aux filtres appliqués.' : 'Planifiez une première intervention.' }}"
                >
                    <x-slot:action>
                        <a href="{{ route('back.maintenances.create') }}"
                           class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Créer une maintenance</a>
                    </x-slot:action>
                </x-back.empty-state>
            </div>
        @endif
    </div>

@endsection
