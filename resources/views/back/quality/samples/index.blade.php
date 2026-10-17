@extends('back.layouts.app')

@section('title', 'AquaSecure — Prélèvements qualité')

@section('content')
    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $tone = fn (string $statut) => match ($statut) {
            'conforme' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'non_conforme' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
        };
    @endphp

    <x-back.page-header title="Prélèvements qualité" desc="Analyses de l'eau distribuée, conformité et alertes générées.">
        <x-slot:action>
            <a href="{{ route('back.quality-samples.create') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Nouveau prélèvement</a>
        </x-slot:action>
    </x-back.page-header>

    <form method="GET" class="mb-5 flex flex-wrap gap-2">
        <input type="search" name="search" value="{{ $search }}" placeholder="Référence…" class="w-full max-w-xs {{ $inputClass }}">
        <select name="statut" class="{{ $inputClass }}">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $key => $label)
                <option value="{{ $key }}" @selected($currentStatut === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="zone_id" class="{{ $inputClass }}">
            <option value="">Toutes les zones</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Filtrer</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($samples->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Référence</th>
                            <th class="px-5 py-3 font-medium">Zone</th>
                            <th class="px-5 py-3 font-medium">Prélevé le</th>
                            <th class="px-5 py-3 font-medium">Score</th>
                            <th class="px-5 py-3 font-medium">Conformité</th>
                            <th class="px-5 py-3 font-medium">Préleveur</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($samples as $sample)
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-xs font-mono text-gray-500 dark:text-gray-400">{{ $sample->reference }}</td>
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $sample->zone?->nom ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $sample->date_prelevement?->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $sample->score !== null ? $sample->score.' %' : '—' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tone($sample->statut) }}">{{ $sample->statutLabel() }}</span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $sample->preleveur?->name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.quality-samples.show', $sample) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
                                        <a href="{{ route('back.quality-samples.edit', $sample) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                        <x-back.confirm-delete :action="route('back.quality-samples.destroy', $sample)" message="Ce prélèvement, ses paramètres et ses alertes seront supprimés." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">{{ $samples->links() }}</div>
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucun prélèvement" message="Créez un premier prélèvement pour analyser la qualité de l'eau." />
            </div>
        @endif
    </div>
@endsection
