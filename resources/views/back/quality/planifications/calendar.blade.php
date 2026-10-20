@extends('back.layouts.app')

@section('title', 'AquaSecure — Calendrier des contrôles')

@section('content')
    @php
        $isAdmin = auth()->user()?->isAdmin();
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $moisFr = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];
        $monthLabel = $moisFr[$month->month].' '.$month->year;
        $statutTone = fn (string $statut) => match ($statut) {
            'effectue' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'en_retard' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            default => 'bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-400',
        };
        $filters = array_filter([
            'zone_id' => $currentZone,
            'statut' => $currentStatut,
        ], fn ($value) => $value !== null && $value !== '');
    @endphp

    <x-back.page-header title="Calendrier des contrôles" desc="Contrôles prévus par jour, avec filtres par zone, date et statut.">
        <x-slot:action>
            <a href="{{ route('back.quality-planifications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Liste des planifications</a>
            @if ($isAdmin)
                <a href="{{ route('back.quality-planifications.create') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Nouvelle planification</a>
            @endif
        </x-slot:action>
    </x-back.page-header>

    <form novalidate method="GET" action="{{ route('back.quality-planifications.calendar') }}" class="mb-5 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
        <div>
            <label for="calendar-month" class="mb-1 block text-sm text-gray-500 dark:text-gray-400">Mois</label>
            <input type="month" id="calendar-month" name="month" value="{{ $month->format('Y-m') }}" class="{{ $inputClass }}">
        </div>

        <div>
            <label for="calendar-zone" class="mb-1 block text-sm text-gray-500 dark:text-gray-400">Zone</label>
            <select id="calendar-zone" name="zone_id" class="{{ $inputClass }}">
                <option value="">Toutes les zones</option>
                @foreach ($zones as $zone)
                    <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="calendar-statut" class="mb-1 block text-sm text-gray-500 dark:text-gray-400">Statut</label>
            <select id="calendar-statut" name="statut" class="{{ $inputClass }}">
                <option value="">Tous les statuts</option>
                @foreach ($statuts as $value => $label)
                    <option value="{{ $value }}" @selected($currentStatut === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Afficher</button>
        <a href="{{ route('back.quality-planifications.calendar') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Réinitialiser</a>
    </form>

    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('back.quality-planifications.calendar', array_merge($filters, ['month' => $previousMonth])) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Mois précédent</a>
        <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $monthLabel }}</h2>
        <a href="{{ route('back.quality-planifications.calendar', array_merge($filters, ['month' => $nextMonth])) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Mois suivant</a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
            @foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $jour)
                <div class="px-2 py-2 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $jour }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7">
            @foreach ($days as $day)
                @php
                    $dayControls = $controlsByDay->get($day->toDateString(), collect());
                    $horsMois = $day->month !== $month->month;
                @endphp

                <div class="min-h-[120px] border-b border-r border-gray-100 p-1.5 dark:border-gray-800 {{ $horsMois ? 'bg-gray-50 dark:bg-white/[0.01]' : '' }} {{ $day->isToday() ? 'ring-2 ring-inset ring-brand-500' : '' }}">
                    <div class="mb-1 flex items-center justify-between px-0.5">
                        <span class="text-xs font-medium {{ $horsMois ? 'text-gray-400 dark:text-gray-600' : 'text-gray-700 dark:text-gray-300' }}">{{ $day->day }}</span>
                        @if ($dayControls->isNotEmpty())
                            <span class="rounded-full bg-gray-100 px-1.5 text-[10px] text-gray-500 dark:bg-white/10 dark:text-gray-400">{{ $dayControls->count() }}</span>
                        @endif
                    </div>

                    <div class="space-y-1">
                        @foreach ($dayControls as $control)
                            @php
                                $href = $control->estEffectue()
                                    ? route('back.quality-samples.show', $control->sample)
                                    : route('back.quality-samples.create', ['planification' => $control->id]);
                            @endphp
                            <a href="{{ $href }}"
                               class="block truncate rounded px-1.5 py-1 text-[11px] font-medium {{ $statutTone($control->statut) }}"
                               title="{{ $control->zone?->nom }} — {{ $control->frequenceLabel() }} — {{ $control->statutLabel() }}">
                                {{ $control->zone?->nom }} · {{ $control->statutLabel() }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
        @foreach ($statuts as $statut => $label)
            <span class="inline-flex items-center gap-2">
                <span class="inline-block h-2.5 w-2.5 rounded-full {{ $statut === 'effectue' ? 'bg-success-500' : ($statut === 'en_retard' ? 'bg-error-500' : 'bg-blue-light-500') }}"></span>
                {{ $label }}
            </span>
        @endforeach
        <span class="text-gray-400 dark:text-gray-500">Cliquez sur un contrôle pour enregistrer son prélèvement ou consulter celui déjà réalisé.</span>
    </div>
@endsection
