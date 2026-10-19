@extends('back.layouts.app')

@section('title', 'AquaSecure — Incidents')

@section('content')
    @php
        $statusTone = fn (string $status) => match ($status) {
            'nouveau' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            'en_cours' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
            'en_attente' => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
            'traite' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'rejete' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
        };
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    @endphp

    <x-back.page-header title="Incidents" desc="Traitement des incidents réseau signalés par les citoyens ou créés en interne.">
        <x-slot:action>
            <a href="{{ route('back.incidents.create') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                Nouvel incident
            </a>
        </x-slot:action>
    </x-back.page-header>

    <form method="GET" action="{{ route('back.incidents.index') }}" class="mb-5 flex flex-wrap gap-2">
        <input type="search" name="search" value="{{ $search }}" placeholder="Référence, titre ou adresse…" class="w-full max-w-xs {{ $inputClass }}">
        <select name="status" class="{{ $inputClass }}">
            <option value="">Tous les statuts</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @selected($currentStatus === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="type" class="{{ $inputClass }}">
            <option value="">Tous les types</option>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected($currentType === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="severity" class="{{ $inputClass }}">
            <option value="">Toutes les gravités</option>
            @foreach ($severities as $key => $label)
                <option value="{{ $key }}" @selected($currentSeverity === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="zone_id" class="{{ $inputClass }}">
            <option value="">Toutes les zones</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        <select name="unassigned" class="{{ $inputClass }}">
            <option value="">Toutes les affectations</option>
            <option value="1" @selected($currentUnassigned)>Non affectés (hors doublons)</option>
        </select>
        @if ($similaritiesAvailable)
            <select name="similar" class="{{ $inputClass }}">
                <option value="">Toutes les similarités</option>
                <option value="1" @selected($currentSimilar)>Avec incidents similaires</option>
            </select>
        @endif
        @if ($currentOpen)
            <input type="hidden" name="open" value="1">
            <span class="inline-flex items-center gap-2 rounded-lg bg-brand-50 px-3 py-2 text-xs font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                Incidents ouverts uniquement
                <a href="{{ route('back.incidents.index', request()->except('open')) }}" class="font-semibold hover:underline">Retirer</a>
            </span>
        @endif
        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Filtrer</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($incidents->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Référence</th>
                            <th class="px-5 py-3 font-medium">Incident</th>
                            <th class="px-5 py-3 font-medium">Zone</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium">Priorité</th>
                            <th class="px-5 py-3 font-medium">Assigné à</th>
                            <th class="px-5 py-3 font-medium">Signalé le</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($incidents as $incident)
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-xs font-mono text-gray-500 dark:text-gray-400">{{ $incident->reference }}</td>
                                <td class="px-5 py-3">
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $incident->title }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $incident->typeLabel() }}
                                        @if ($incident->is_duplicate)
                                            <span class="ml-1 rounded-full bg-warning-50 px-2 py-0.5 text-[10px] font-medium text-warning-600 dark:bg-warning-500/15 dark:text-warning-400">Doublon possible</span>
                                        @endif
                                        @if ($similaritiesAvailable && $incident->pending_similarities_count > 0)
                                            <span class="ml-1 rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                                                {{ $incident->pending_similarities_count }} similaire{{ $incident->pending_similarities_count > 1 ? 's' : '' }}
                                            </span>
                                        @endif
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $incident->zone?->nom ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusTone($incident->status) }}">{{ $incident->statusLabel() }}</span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $incident->priorityLabel() }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $incident->assignee?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $incident->reported_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.incidents.show', $incident) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
                                        <a href="{{ route('back.incidents.edit', $incident) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                        <x-back.confirm-delete :action="route('back.incidents.destroy', $incident)" message="L'incident {{ $incident->reference }} et son historique seront supprimés." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">{{ $incidents->links() }}</div>
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucun incident" message="Les incidents signalés apparaîtront ici." />
            </div>
        @endif
    </div>
@endsection
