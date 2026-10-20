@extends('back.layouts.app')

@section('title', 'AquaSecure — '.$infrastructure->nom)

@section('content')

    <x-back.page-header :title="$infrastructure->nom"
        :desc="$infrastructure->typeLabel().' — Zone : '.($infrastructure->zone?->nom ?? '—')">
        <x-slot:action>
            <a href="{{ route('back.infrastructures.edit', $infrastructure) }}"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
                Modifier
            </a>
            <x-back.confirm-delete :action="route('back.infrastructures.destroy', $infrastructure)"
                message="L'infrastructure « {{ $infrastructure->nom }} » et ses maintenances seront supprimées." />
        </x-slot:action>
    </x-back.page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-back.stat-card label="Statut" :value="$infrastructure->statutLabel()" />
        <x-back.stat-card label="Matériau" :value="$infrastructure->materiau ?? '—'" />
        <x-back.stat-card label="Capacité" :value="$infrastructure->capacite ?? '—'" />
        <x-back.stat-card label="Score de risque" :value="$infrastructure->score_risque" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-back.stat-card label="Date d'installation" :value="$infrastructure->date_installation?->format('d/m/Y') ?? '—'" />
        <x-back.stat-card label="Latitude" :value="$infrastructure->latitude ?? '—'" />
        <x-back.stat-card label="Longitude" :value="$infrastructure->longitude ?? '—'" />
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Maintenances</h2>
            <a href="{{ route('back.maintenances.create', ['infrastructure_id' => $infrastructure->id]) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Planifier une maintenance</a>
        </div>

        @if ($maintenances->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Type</th>
                            <th class="px-5 py-3 font-medium">Date</th>
                            <th class="px-5 py-3 font-medium">Coût</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($maintenances as $maintenance)
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3">
                                    <a href="{{ route('back.maintenances.show', $maintenance) }}" class="text-sm font-medium text-brand-500 hover:underline dark:text-brand-400">{{ $maintenance->type }}</a>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $maintenance->date_intervention?->format('d/m/Y') }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $maintenance->cout !== null ? number_format($maintenance->cout, 2, ',', ' ').' €' : '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $maintenance->statut }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-back.pagination :paginator="$maintenances" label="maintenances" :showPerPage="false" />
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucune maintenance" message="Aucune intervention n'est planifiée pour cette infrastructure." />
            </div>
        @endif
    </div>

@endsection
