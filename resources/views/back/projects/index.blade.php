@extends('back.layouts.app')

@section('title', 'AquaSecure — Projets')

@php use App\Services\ProjectDeadlineIndicators; @endphp

@section('content')
    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
        $hasFilters = $search !== '' || $type !== '' || $statut !== '' || $currentZone !== null
            || $currentResponsable !== null || $from !== null || $to !== null;
    @endphp

    <x-back.page-header title="Projets" desc="Projets d'investissement et de réhabilitation du réseau.">
        <x-slot:action>
            <a href="{{ route('back.projects.create') }}"
               class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                Nouveau projet
            </a>
        </x-slot:action>
    </x-back.page-header>

    <form novalidate method="GET" action="{{ route('back.projects.index') }}" class="mb-5 flex flex-wrap gap-2">
        <input type="search" name="search" value="{{ $search }}" placeholder="Titre ou description…" class="w-full max-w-xs {{ $inputClass }}">
        <select name="type" class="{{ $inputClass }}">
            <option value="">Tous les types</option>
            @foreach ($types as $option)
                <option value="{{ $option }}" @selected($type === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
            @endforeach
        </select>
        <select name="statut" class="{{ $inputClass }}">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $option)
                <option value="{{ $option }}" @selected($statut === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
            @endforeach
        </select>
        <select name="zone_id" class="{{ $inputClass }}">
            <option value="">Toutes les zones</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        <select name="responsable_id" class="{{ $inputClass }}">
            <option value="">Tous les responsables</option>
            @foreach ($responsables as $responsable)
                <option value="{{ $responsable->id }}" @selected($currentResponsable === $responsable->id)>{{ $responsable->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $from }}" aria-label="Débuts à partir du" title="Débuts à partir du" class="{{ $inputClass }}">
        <input type="date" name="to" value="{{ $to }}" aria-label="Débuts jusqu'au" title="Débuts jusqu'au" class="{{ $inputClass }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <button type="submit"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
            Filtrer
        </button>
        @if ($hasFilters)
            <a href="{{ route('back.projects.index') }}" class="inline-flex items-center justify-center rounded-lg px-3 py-2.5 text-sm font-medium text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                Réinitialiser
            </a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($projects->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Titre</th>
                            <th class="px-5 py-3 font-medium">Type</th>
                            <th class="px-5 py-3 font-medium">Zone</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium">Avancement</th>
                            <th class="px-5 py-3 font-medium">Budget</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                            @php($echeance = ProjectDeadlineIndicators::projectStatus($project))
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3">
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $project->titre }}</p>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @if ($echeance === ProjectDeadlineIndicators::ECHEANCE_DEPASSEE)
                                            <span class="inline-block rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600 dark:bg-red-500/10 dark:text-red-300"
                                                  title="{{ ProjectDeadlineIndicators::projectExplanation($project) }}">Échéance dépassée</span>
                                        @elseif ($echeance === ProjectDeadlineIndicators::ECHEANCE_PROCHE)
                                            <span class="inline-block rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-600 dark:bg-amber-500/10 dark:text-amber-300"
                                                  title="{{ ProjectDeadlineIndicators::projectExplanation($project) }}">Échéance proche</span>
                                        @endif
                                        @if (! ProjectDeadlineIndicators::projectIsClosed($project) && $project->overdue_phases_count > 0)
                                            <span class="inline-block rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600 dark:bg-red-500/10 dark:text-red-300">
                                                {{ $project->overdue_phases_count }} phase(s) en retard
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ ucfirst($project->type) }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $project->zone?->nom ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $project->statut)) }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $project->avancement_pourcentage }} %</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ number_format((float) $project->budget_prevu, 2, ',', ' ') }} €</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.projects.show', $project) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
                                        <a href="{{ route('back.projects.edit', $project) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-back.pagination :paginator="$projects" label="projets" />
        @else
            <div class="p-5">
                <x-back.empty-state
                    title="Aucun projet"
                    message="{{ $hasFilters ? 'Aucun projet ne correspond aux filtres appliqués.' : 'Créez un premier projet d’investissement.' }}"
                >
                    <x-slot:action>
                        <a href="{{ route('back.projects.create') }}"
                           class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Créer un projet</a>
                    </x-slot:action>
                </x-back.empty-state>
            </div>
        @endif
    </div>

@endsection
