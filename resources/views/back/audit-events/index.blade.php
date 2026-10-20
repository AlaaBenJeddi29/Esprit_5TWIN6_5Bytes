@extends('back.layouts.app')

@section('title', 'AquaSecure — Journal d’audit')

@section('content')
    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
        $hasFilters = $search !== '' || $subject !== '' || $action !== '' || $projectId > 0
            || $from !== null || $to !== null;
    @endphp

    <x-back.page-header title="Journal d’audit"
        desc="Historique en insertion seule des créations, modifications et suppressions de la Gestion 5." />

    <form novalidate method="GET" action="{{ route('back.audit-events.index') }}" class="mb-5 flex flex-wrap gap-2">
        @if ($project)
            <input type="hidden" name="project_id" value="{{ $project->id }}">
        @endif
        <input type="search" name="search" value="{{ $search }}" placeholder="Acteur, action ou résumé…" class="w-full max-w-xs {{ $inputClass }}">
        <select name="subject" class="{{ $inputClass }}">
            <option value="">Toutes les entités</option>
            @foreach ($subjects as $value => $label)
                <option value="{{ $value }}" @selected($subject === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="action" class="{{ $inputClass }}">
            <option value="">Toutes les actions</option>
            @foreach ($actions as $option)
                <option value="{{ $option }}" @selected($action === $option)>{{ $option }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $from }}" aria-label="Événements à partir du" title="Événements à partir du" class="{{ $inputClass }}">
        <input type="date" name="to" value="{{ $to }}" aria-label="Événements jusqu'au" title="Événements jusqu'au" class="{{ $inputClass }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <button type="submit"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
            Filtrer
        </button>
        @if ($hasFilters)
            <a href="{{ route('back.audit-events.index') }}" class="inline-flex items-center justify-center rounded-lg px-3 py-2.5 text-sm font-medium text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                Réinitialiser
            </a>
        @endif
        @if ($project)
            <a href="{{ route('back.projects.show', $project) }}"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
                Projet : {{ $project->titre }}
            </a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($events->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Date</th>
                            <th class="px-5 py-3 font-medium">Acteur</th>
                            <th class="px-5 py-3 font-medium">Action</th>
                            <th class="px-5 py-3 font-medium">Projet</th>
                            <th class="px-5 py-3 font-medium">Résumé</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            @php
                                $verb = str_contains($event->action, '.created')
                                    ? 'created'
                                    : (str_contains($event->action, '.updated') ? 'updated' : 'deleted');
                                $badge = [
                                    'created' => 'bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-300',
                                    'updated' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300',
                                    'deleted' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300',
                                ][$verb];
                            @endphp
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $event->created_at?->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-800 dark:text-white/90">
                                    {{ $event->user?->name ?? 'Utilisateur supprimé' }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">
                                        {{ $subjects[$event->subject_type] ?? $event->subject_type }} · {{ $verb }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $event->project?->titre ?? '—' }}
                                </td>
                                <td class="max-w-md px-5 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="line-clamp-2" title="{{ $event->summary }}">{{ $event->summary }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-back.pagination :paginator="$events" label="événements" />
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucun événement"
                    message="{{ $hasFilters ? 'Aucun événement ne correspond aux filtres appliqués.' : 'Les événements enregistrés apparaîtront ici dès qu’une action est journalisée.' }}" />
            </div>
        @endif
    </div>

@endsection
