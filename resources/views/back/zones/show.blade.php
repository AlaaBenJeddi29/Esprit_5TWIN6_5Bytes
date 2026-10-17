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

@endsection
