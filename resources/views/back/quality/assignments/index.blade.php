@extends('back.layouts.app')

@section('title', 'AquaSecure — Affectations des zones qualité')

@section('content')
    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    @endphp

    <x-back.page-header title="Affectations des zones qualité" desc="Définissez les zones que chaque gestionnaire peut consulter et planifier. Les administrateurs conservent l'accès à toutes les zones." />

    <form novalidate method="GET" class="mb-5 flex flex-wrap gap-2">
        <input type="search" name="search" value="{{ $search }}" placeholder="Nom ou e-mail du gestionnaire…" class="w-full max-w-xs {{ $inputClass }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Filtrer</button>
        @if ($search !== '')
            <a href="{{ route('back.quality-assignments.index') }}" class="inline-flex items-center px-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Réinitialiser</a>
        @endif
    </form>

    @if ($gestionnaires->isEmpty())
        <x-back.empty-state title="Aucun gestionnaire" :message="$search !== '' ? 'Aucun gestionnaire ne correspond à la recherche.' : 'Créez un compte gestionnaire depuis le menu Gestionnaires pour lui affecter des zones qualité.'" />
    @else
        <div class="space-y-5">
            @foreach ($gestionnaires as $gestionnaire)
                @php
                    $assigned = $assignments->get($gestionnaire->id, []);
                    $oldGestionnaireId = old('gestionnaire_id');
                    $isOldTarget = is_scalar($oldGestionnaireId) && (int) $oldGestionnaireId === $gestionnaire->id;

                    if ($isOldTarget) {
                        $assigned = array_map('intval', array_filter((array) old('zone_ids', []), 'is_scalar'));
                    }
                @endphp

                <form novalidate method="POST" action="{{ route('back.quality-assignments.update') }}" class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="gestionnaire_id" value="{{ $gestionnaire->id }}">

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $gestionnaire->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $gestionnaire->email }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ count($assigned) }} zone(s) affectée(s)</span>
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Enregistrer</button>
                        </div>
                    </div>

                    @if ($zones->isEmpty())
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Aucune zone n'existe encore dans le module Infrastructures.</p>
                    @else
                        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($zones as $zone)
                                <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="zone_ids[]" value="{{ $zone->id }}" @checked(in_array($zone->id, $assigned, true)) class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                                    <span>{{ $zone->nom }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </form>
            @endforeach
        </div>

        <div class="mt-5 rounded-2xl border border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <x-back.pagination :paginator="$gestionnaires" label="gestionnaires" class="!border-0 !p-0" />
        </div>
    @endif
@endsection
