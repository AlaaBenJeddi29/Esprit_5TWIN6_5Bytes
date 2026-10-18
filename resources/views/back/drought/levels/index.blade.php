@extends('back.layouts.app')

@section('title', 'AquaSecure — Niveaux des ressources')

@section('content')
    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $tone = fn (string $statut) => match ($statut) {
            'critique' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            'alerte' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            default => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
        };
    @endphp

    <x-back.page-header title="Niveaux des ressources" desc="Mesures de remplissage des barrages, nappes et réservoirs.">
        <x-slot:action>
            <a href="{{ route('back.water-levels.create') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Nouvelle mesure</a>
        </x-slot:action>
    </x-back.page-header>

    <form method="GET" class="mb-5 flex flex-wrap gap-2">
        <select name="type_source" class="{{ $inputClass }}">
            <option value="">Tous les types</option>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected($currentType === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="zone_id" class="{{ $inputClass }}">
            <option value="">Toutes les zones</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Filtrer</button>
        <a href="{{ route('back.water-levels.export', array_merge(request()->only(['zone_id', 'type_source']), ['format' => 'pdf'])) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">📄 Exporter PDF</a>
        <a href="{{ route('back.water-levels.export', array_merge(request()->only(['zone_id', 'type_source']), ['format' => 'excel'])) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">📊 Exporter Excel</a>
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($levels->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Source</th>
                            <th class="px-5 py-3 font-medium">Type</th>
                            <th class="px-5 py-3 font-medium">Zone</th>
                            <th class="px-5 py-3 font-medium">Niveau</th>
                            <th class="px-5 py-3 font-medium">Seuils</th>
                            <th class="px-5 py-3 font-medium">Mesuré le</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($levels as $level)
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $level->nom_source }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $level->typeLabel() }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $level->zone?->nom ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $level->niveau }} %</span>
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tone($level->niveauStatut()) }}">{{ $level->niveauStatutLabel() }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400">Alerte : {{ $level->seuil_alerte }} % · Critique : {{ $level->seuil_critique }} %</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $level->date_mesure?->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.water-levels.edit', $level) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                        <x-back.confirm-delete :action="route('back.water-levels.destroy', $level)" message="Cette mesure de niveau sera définitivement supprimée." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">{{ $levels->links() }}</div>
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucune mesure de niveau" message="Ajoutez des mesures pour alimenter l'analyse du risque sécheresse." />
            </div>
        @endif
    </div>
@endsection
