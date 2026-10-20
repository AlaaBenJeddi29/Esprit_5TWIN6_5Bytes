@extends('back.layouts.app')

@section('title', 'AquaSecure — Abonnements aux alertes')

@section('content')
    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $hasFilters = $search !== '' || $currentType !== '' || $currentActif !== '' || $currentZone !== null;
    @endphp

    <x-back.page-header title="Abonnements aux alertes" desc="Consulter les citoyens abonnés aux alertes par zone. Les abonnements sont gérés par les citoyens eux-mêmes." />

    <form novalidate method="GET" class="mb-5 flex flex-wrap gap-2">
        <input type="search" name="search" value="{{ $search }}" placeholder="Nom ou email du citoyen…" class="w-full max-w-xs {{ $inputClass }}">
        <select name="type_alerte" class="{{ $inputClass }}">
            <option value="">Tous les types</option>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected($currentType === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="actif" class="{{ $inputClass }}">
            <option value="">Tous les états</option>
            <option value="1" @selected($currentActif === '1')>Actifs</option>
            <option value="0" @selected($currentActif === '0')>Inactifs</option>
        </select>
        <select name="zone_id" class="{{ $inputClass }}">
            <option value="">Toutes les zones</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Filtrer</button>
        @if ($hasFilters)
            <a href="{{ route('back.alert-subscriptions.index') }}" class="inline-flex items-center justify-center rounded-lg px-3 py-2.5 text-sm font-medium text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                Réinitialiser
            </a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($subscriptions->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Citoyen</th>
                            <th class="px-5 py-3 font-medium">Zone</th>
                            <th class="px-5 py-3 font-medium">Type d'alerte</th>
                            <th class="px-5 py-3 font-medium">État</th>
                            <th class="px-5 py-3 font-medium">Depuis</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subscriptions as $subscription)
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3">
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $subscription->user?->name ?? '—' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $subscription->user?->email }}</p>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $subscription->zone?->nom ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $subscription->typeLabel() }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $subscription->actif ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400' : 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' }}">
                                        {{ $subscription->actif ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $subscription->created_at?->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-back.pagination :paginator="$subscriptions" label="abonnements" />
        @else
            <div class="p-5">
                <x-back.empty-state
                    title="Aucun abonnement"
                    message="{{ $hasFilters ? 'Aucun abonnement ne correspond aux filtres appliqués.' : 'Les citoyens peuvent s’abonner aux alertes depuis leur espace.' }}"
                />
            </div>
        @endif
    </div>
@endsection
