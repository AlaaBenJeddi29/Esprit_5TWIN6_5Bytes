@extends('back.layouts.app')

@section('title', 'AquaSecure — Alertes qualité')

@section('content')
    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $tone = fn (string $niveau) => match ($niveau) {
            'critique' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            'avertissement' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            default => 'bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-400',
        };
    @endphp

    <x-back.page-header title="Alertes qualité" desc="Alertes générées automatiquement lors de l'analyse des prélèvements non conformes." />

    <form method="GET" class="mb-5 flex flex-wrap gap-2">
        <select name="niveau" class="{{ $inputClass }}">
            <option value="">Tous les niveaux</option>
            @foreach ($niveaux as $key => $label)
                <option value="{{ $key }}" @selected($currentNiveau === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="statut" class="{{ $inputClass }}">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $key => $label)
                <option value="{{ $key }}" @selected($currentStatut === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="zone_id" class="{{ $inputClass }}">
            <option value="">Toutes les zones</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Filtrer</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($alerts->isNotEmpty())
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($alerts as $alert)
                    <div class="flex flex-wrap items-start justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tone($alert->niveau) }}">{{ $alert->niveauLabel() }}</span>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $alert->isActive() ? 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400' : 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400' }}">{{ $alert->statutLabel() }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $alert->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                            <p class="mt-2 text-sm font-medium text-gray-800 dark:text-white/90">{{ $alert->message }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Zone : {{ $alert->zone?->nom ?? '—' }}
                                @if ($alert->sample)
                                    · Prélèvement : <a href="{{ route('back.quality-samples.show', $alert->sample) }}" class="font-mono text-brand-500 hover:underline dark:text-brand-400">{{ $alert->sample->reference }}</a>
                                @endif
                                @if ($alert->resolue_at)
                                    · Résolue le {{ $alert->resolue_at->format('d/m/Y H:i') }}
                                @endif
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('back.quality-alerts.update', $alert) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <select name="statut" class="{{ $inputClass }} !py-1.5 text-xs @error('statut') border-error-400 @enderror">
                                    @foreach ($statuts as $key => $label)
                                        <option value="{{ $key }}" @selected(old('statut', $alert->statut) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Mettre à jour</button>
                                @error('statut')<span class="text-xs text-error-600">{{ $message }}</span>@enderror
                            </form>
                            <x-back.confirm-delete :action="route('back.quality-alerts.destroy', $alert)" message="Cette alerte sera définitivement supprimée." />
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">{{ $alerts->links() }}</div>
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucune alerte qualité" message="Les alertes apparaissent ici lorsqu'un prélèvement dépasse un seuil configuré." />
            </div>
        @endif
    </div>
@endsection
