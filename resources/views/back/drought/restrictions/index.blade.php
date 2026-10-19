@extends('back.layouts.app')

@section('title', 'AquaSecure — Restrictions d\'usage')

@section('content')
    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $statutTone = fn (string $statut) => match ($statut) {
            'active' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            'planifiee' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
        };
    @endphp

    <x-back.page-header title="Restrictions d'usage" desc="Mesures de limitation de l'eau potable, publiées sur le site citoyen.">
        <x-slot:action>
            <a href="{{ route('back.restrictions.create') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Nouvelle restriction</a>
        </x-slot:action>
    </x-back.page-header>

    <form method="GET" class="mb-5 flex flex-wrap gap-2">
        <select name="type" class="{{ $inputClass }}">
            <option value="">Tous les niveaux</option>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected($currentType === $key)>{{ $label }}</option>
            @endforeach
        </select>
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
        <a href="{{ route('back.restrictions.export', array_merge(request()->only(['zone_id', 'type', 'statut']), ['format' => 'pdf'])) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">📄 Exporter PDF</a>
        <a href="{{ route('back.restrictions.export', array_merge(request()->only(['zone_id', 'type', 'statut']), ['format' => 'excel'])) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">📊 Exporter Excel</a>
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($restrictions->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Titre</th>
                            <th class="px-5 py-3 font-medium">Niveau</th>
                            <th class="px-5 py-3 font-medium">Zone</th>
                            <th class="px-5 py-3 font-medium">Période</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($restrictions as $restriction)
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $restriction->titre }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $restriction->typeLabel() }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $restriction->zone?->nom ?? 'Toutes' }}</td>
                                <td class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $restriction->date_debut?->format('d/m/Y H:i') }}
                                    @if ($restriction->date_fin)
                                        → {{ $restriction->date_fin->format('d/m/Y H:i') }}
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statutTone($restriction->statut) }}">{{ $restriction->statutLabel() }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.restrictions.edit', $restriction) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                        <x-back.confirm-delete :action="route('back.restrictions.destroy', $restriction)" message="Cette restriction sera retirée du site citoyen et supprimée." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">{{ $restrictions->links() }}</div>
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucune restriction" message="Les restrictions actives sont visibles publiquement." />
            </div>
        @endif
    </div>
@endsection
