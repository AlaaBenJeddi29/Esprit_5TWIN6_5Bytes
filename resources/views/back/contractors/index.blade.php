@extends('back.layouts.app')

@section('title', 'AquaSecure — Entreprises')

@section('content')

    <x-back.page-header title="Entreprises" desc="Prestataires et sous-traitants intervenant sur les projets.">
        <x-slot:action>
            <a href="{{ route('back.contractors.create') }}"
               class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                Nouvelle entreprise
            </a>
        </x-slot:action>
    </x-back.page-header>

    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
        $hasFilters = $search !== '' || $currentSpecialite !== '';
    @endphp

    <form method="GET" action="{{ route('back.contractors.index') }}" class="mb-5 flex flex-wrap gap-2">
        <input type="search" name="search" value="{{ $search }}" placeholder="Nom, spécialité ou email…" class="w-full max-w-sm {{ $inputClass }}">
        <select name="specialite" class="{{ $inputClass }}">
            <option value="">Toutes les spécialités</option>
            @foreach ($specialites as $option)
                <option value="{{ $option }}" @selected($currentSpecialite === $option)>{{ $option }}</option>
            @endforeach
        </select>
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <button type="submit"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
            Rechercher
        </button>
        @if ($hasFilters)
            <a href="{{ route('back.contractors.index') }}" class="inline-flex items-center justify-center rounded-lg px-3 py-2.5 text-sm font-medium text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                Réinitialiser
            </a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($contractors->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Nom</th>
                            <th class="px-5 py-3 font-medium">Spécialité</th>
                            <th class="px-5 py-3 font-medium">Contact</th>
                            <th class="px-5 py-3 font-medium">Phases</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contractors as $contractor)
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $contractor->nom }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $contractor->specialite }}</td>
                                <td class="px-5 py-3">
                                    <span class="block text-sm text-gray-500 dark:text-gray-400">{{ $contractor->email }}</span>
                                    <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $contractor->telephone }}</span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $contractor->project_phases_count }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.contractors.show', $contractor) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
                                        <a href="{{ route('back.contractors.edit', $contractor) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                        <x-back.confirm-delete :action="route('back.contractors.destroy', $contractor)"
                                            message="L'entreprise « {{ $contractor->nom }} » sera supprimée." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-back.pagination :paginator="$contractors" label="entreprises" />
        @else
            <div class="p-5">
                <x-back.empty-state
                    title="Aucune entreprise"
                    message="{{ $hasFilters ? 'Aucune entreprise ne correspond aux filtres appliqués.' : 'Enregistrez un premier prestataire.' }}"
                >
                    <x-slot:action>
                        <a href="{{ route('back.contractors.create') }}"
                           class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Créer une entreprise</a>
                    </x-slot:action>
                </x-back.empty-state>
            </div>
        @endif
    </div>

@endsection
