@extends('back.layouts.app')

@section('title', 'AquaSecure — Signalements')

@section('content')
    @php
        $current = $currentStatus ?? '';
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $hasFilters = $search !== '' || $current !== '' || $currentType !== '' || $from !== null || $to !== null;
        $filtresSansStatut = request()->except(['page', 'status']);
        $statusTone = fn (string $status) => match ($status) {
            'nouveau' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            'en_cours' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
            'traite' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'rejete' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
        };
    @endphp

    <x-back.page-header
        title="Signalements"
        desc="Liste des incidents signalés sur le réseau d'eau."
    >
        <x-slot:action>
            <a href="{{ route('back.reports.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 4.5v15m7.5-7.5h-15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Nouveau signalement
            </a>
        </x-slot:action>
    </x-back.page-header>

    {{-- Filtre par statut (conserve la recherche et les autres filtres) --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('back.reports.index', $filtresSansStatut) }}"
           class="inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium transition {{ $current === '' ? 'border-brand-500 bg-brand-500 text-white' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]' }}">
            Tous
        </a>
        @foreach ($statuses as $key => $label)
            <a href="{{ route('back.reports.index', array_merge($filtresSansStatut, ['status' => $key])) }}"
               class="inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium transition {{ $current === $key ? 'border-brand-500 bg-brand-500 text-white' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form novalidate method="GET" action="{{ route('back.reports.index') }}" class="mb-5 flex flex-wrap gap-2">
        <input type="search" name="search" value="{{ $search }}" placeholder="Titre, description ou adresse…" class="w-full max-w-xs {{ $inputClass }}">
        <select name="type" class="{{ $inputClass }}">
            <option value="">Tous les types</option>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected($currentType === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $from }}" aria-label="Signalés à partir du" title="Signalés à partir du" class="{{ $inputClass }}">
        <input type="date" name="to" value="{{ $to }}" aria-label="Signalés jusqu'au" title="Signalés jusqu'au" class="{{ $inputClass }}">
        @if ($current !== '')
            <input type="hidden" name="status" value="{{ $current }}">
        @endif
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Filtrer</button>
        @if ($hasFilters)
            <a href="{{ route('back.reports.index') }}" class="inline-flex items-center justify-center rounded-lg px-3 py-2.5 text-sm font-medium text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                Réinitialiser
            </a>
        @endif
    </form>

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($reports->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Titre</th>
                            <th class="px-5 py-3 font-medium">Type</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium">Adresse</th>
                            <th class="px-5 py-3 font-medium">Date</th>
                            <th class="px-5 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reports as $report)
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3.5 text-sm font-medium text-gray-800 dark:text-white/90">{{ $report->title }}</td>
                                <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400">{{ $report->typeLabel() }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusTone($report->status) }}">
                                        {{ $report->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400">{{ $report->address ?: '—' }}</td>
                                <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400">{{ $report->created_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.reports.show', $report) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
                                        <a href="{{ route('back.reports.edit', $report) }}" class="text-xs font-medium text-gray-500 hover:underline dark:text-gray-400">Modifier</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-back.pagination :paginator="$reports" label="signalements" />
        @else
            <div class="p-5">
                <x-back.empty-state
                    title="Aucun signalement trouvé"
                    message="{{ $hasFilters ? 'Aucun signalement ne correspond aux filtres appliqués.' : 'Aucun signalement enregistré pour le moment.' }}"
                >
                    <x-slot:action>
                        <a href="{{ route('back.reports.create') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                            Créer un signalement
                        </a>
                    </x-slot:action>
                </x-back.empty-state>
            </div>
        @endif
    </div>
@endsection
