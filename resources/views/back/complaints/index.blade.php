@extends('back.layouts.app')

@section('title', 'AquaSecure — Réclamations')

@section('content')
    @php
        $statusTone = fn (string $status) => match ($status) {
            'en_attente' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            'en_examen' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
            'acceptee' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'rejetee' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            'cloturee' => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
        };
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    @endphp

    <x-back.page-header title="Réclamations et satisfaction" desc="Examen des réclamations citoyennes, décisions et suivi de la satisfaction.">
        <x-slot:action>
            <a href="{{ route('back.complaints.stats') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                Indicateurs de satisfaction
            </a>
        </x-slot:action>
    </x-back.page-header>

    <form method="GET" action="{{ route('back.complaints.index') }}" class="mb-5 flex flex-wrap gap-2">
        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Référence, titre ou citoyen…" class="w-full max-w-xs {{ $inputClass }}">

        <select name="status" class="{{ $inputClass }}">
            <option value="">Tous les statuts</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="rating" class="{{ $inputClass }}">
            <option value="">Toutes les notes</option>
            @for ($note = 1; $note <= 5; $note++)
                <option value="{{ $note }}" @selected((string) ($filters['rating'] ?? '') === (string) $note)>{{ $note }} étoile{{ $note > 1 ? 's' : '' }}</option>
            @endfor
        </select>

        <select name="zone_id" class="{{ $inputClass }}">
            <option value="">Toutes les zones</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected((string) ($filters['zone_id'] ?? '') === (string) $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>

        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $inputClass }}" title="Déposée du">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $inputClass }}" title="Déposée au">

        <select name="reopen" class="{{ $inputClass }}">
            <option value="">Toutes les demandes</option>
            <option value="1" @selected(! empty($filters['reopen']))>Réouverture demandée</option>
        </select>

        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Filtrer</button>
        <a href="{{ route('back.complaints.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Réinitialiser</a>
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($feedbacks->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Signalement</th>
                            <th class="px-5 py-3 font-medium">Citoyen</th>
                            <th class="px-5 py-3 font-medium">Zone</th>
                            <th class="px-5 py-3 font-medium">Note</th>
                            <th class="px-5 py-3 font-medium">Déposée le</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium">Réouverture</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($feedbacks as $feedback)
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3">
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $feedback->incident?->title ?? 'Incident supprimé' }}</p>
                                    <p class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $feedback->incident?->reference ?? '—' }}</p>
                                    <p class="mt-1 max-w-xs truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $feedback->complaint_description }}">
                                        {{ \Illuminate\Support\Str::limit($feedback->complaint_description, 80) }}
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $feedback->user?->name ?? 'Citoyen supprimé' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $feedback->incident?->zone?->nom ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <span class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $feedback->rating }}/5</span>
                                    <span class="ml-1 text-sm text-warning-400">{{ str_repeat('★', $feedback->rating) }}</span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $feedback->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusTone($feedback->complaint_status) }}">
                                        {{ $feedback->complaintStatusLabel() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($feedback->reopened_at)
                                        <span class="text-xs font-medium text-success-600 dark:text-success-400">Réouvert le {{ $feedback->reopened_at->format('d/m/Y') }}</span>
                                    @elseif ($feedback->reopen_requested)
                                        <span class="text-xs font-medium text-warning-600 dark:text-warning-400">Demandée</span>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('back.complaints.show', $feedback) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Examiner</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">{{ $feedbacks->links() }}</div>
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucune réclamation" message="Les réclamations déposées par les citoyens apparaîtront ici." />
            </div>
        @endif
    </div>
@endsection
