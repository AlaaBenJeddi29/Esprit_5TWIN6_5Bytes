@extends('back.layouts.app')

@section('title', 'AquaSecure — Planification des prélèvements')

@section('content')
    @php
        $isAdmin = auth()->user()?->isAdmin();
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $statutTone = fn (string $statut) => match ($statut) {
            'effectue' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'en_retard' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            default => 'bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-400',
        };
    @endphp

    <x-back.page-header title="Planification des prélèvements" desc="Contrôles prévus, retards et historique des prélèvements réalisés.">
        <x-slot:action>
            <a href="{{ route('back.quality-planifications.calendar') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Calendrier</a>
            @if ($isAdmin)
                <a href="{{ route('back.quality-assignments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Affectations des zones</a>
                <a href="{{ route('back.quality-planifications.create') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Nouvelle planification</a>
            @endif
        </x-slot:action>
    </x-back.page-header>

    {{-- Rappels --}}
    <div class="mb-8">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Rappels</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Prochains contrôles et nombre de contrôles en retard.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-back.stat-card label="Contrôles à venir" :value="$reminders['a_venir']" />
            <x-back.stat-card label="Contrôles en retard" :value="$reminders['en_retard']" />
            <x-back.stat-card label="Contrôles effectués" :value="$reminders['effectues']" />
        </div>

        <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Prochains contrôles</h3>

            @if ($reminders['prochains']->isEmpty())
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Aucun contrôle à venir.</p>
            @else
                <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($reminders['prochains'] as $prochain)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                            <span class="text-gray-700 dark:text-gray-300">
                                {{ $prochain->date_prevue->format('d/m/Y') }} — {{ $prochain->zone?->nom }}
                                <span class="text-gray-400">({{ $prochain->frequenceLabel() }})</span>
                            </span>
                            <a href="{{ route('back.quality-samples.create', ['planification' => $prochain->id]) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Enregistrer le prélèvement</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Filtres --}}
    <form novalidate method="GET" action="{{ route('back.quality-planifications.index') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
        <div>
            <label for="filter-zone" class="mb-1 block text-sm text-gray-500 dark:text-gray-400">Zone</label>
            <select id="filter-zone" name="zone_id" class="{{ $inputClass }}">
                <option value="">Toutes les zones</option>
                @foreach ($zones as $zone)
                    <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="filter-statut" class="mb-1 block text-sm text-gray-500 dark:text-gray-400">Statut</label>
            <select id="filter-statut" name="statut" class="{{ $inputClass }}">
                <option value="">Tous les statuts</option>
                @foreach ($statuts as $value => $label)
                    <option value="{{ $value }}" @selected($currentStatut === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="filter-date" class="mb-1 block text-sm text-gray-500 dark:text-gray-400">Date</label>
            <input type="date" id="filter-date" name="date" value="{{ $currentDate }}" class="{{ $inputClass }}">
        </div>

        <div>
            <label for="filter-frequence" class="mb-1 block text-sm text-gray-500 dark:text-gray-400">Fréquence</label>
            <select id="filter-frequence" name="frequence" class="{{ $inputClass }}">
                <option value="">Toutes les fréquences</option>
                @foreach ($frequences as $value => $label)
                    <option value="{{ $value }}" @selected($currentFrequence === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Filtrer</button>
        <a href="{{ route('back.quality-planifications.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Réinitialiser</a>
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($planifications->isEmpty())
            <div class="p-6">
                <x-back.empty-state title="Aucun contrôle planifié" message="Aucune planification ne correspond aux filtres sélectionnés." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Date prévue</th>
                            <th class="px-5 py-3 font-medium">Zone</th>
                            <th class="px-5 py-3 font-medium">Fréquence</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium">Prélèvement</th>
                            @if ($isAdmin)
                                <th class="px-5 py-3 font-medium">Créé par</th>
                            @endif
                            <th class="px-5 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($planifications as $planification)
                            <tr class="border-b border-gray-100 align-middle last:border-0 dark:border-gray-800">
                                <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $planification->date_prevue->format('d/m/Y') }}</td>
                                <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $planification->zone?->nom ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $planification->frequenceLabel() }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statutTone($planification->statut) }}">
                                        {{ $planification->statutLabel() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-sm">
                                    @if ($planification->sample)
                                        <a href="{{ route('back.quality-samples.show', $planification->sample) }}" class="font-mono text-xs text-brand-500 hover:underline dark:text-brand-400">{{ $planification->sample->reference }}</a>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                                @if ($isAdmin)
                                    <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $planification->creator?->name ?? '—' }}</td>
                                @endif
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap items-center justify-end gap-3">
                                        @if ($planification->estEffectue())
                                            <a href="{{ route('back.quality-samples.show', $planification->sample) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Voir le prélèvement</a>
                                        @else
                                            <a href="{{ route('back.quality-samples.create', ['planification' => $planification->id]) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Enregistrer le prélèvement</a>
                                            @if ($isAdmin)
                                                <a href="{{ route('back.quality-planifications.edit', $planification) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                                <x-back.confirm-delete :action="route('back.quality-planifications.destroy', $planification)" message="Ce contrôle planifié sera supprimé. Les contrôles effectués et leurs prélèvements sont conservés." />
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-back.pagination :paginator="$planifications" label="contrôles planifiés" />
        @endif
    </div>
@endsection
