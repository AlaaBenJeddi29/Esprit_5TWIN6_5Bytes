@extends('front.layouts.app')

@section('title', 'AquaSecure — Réseau & infrastructures')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Réseau',
        'heading' => 'Infrastructures d\'eau potable',
        'lead' => 'Cartographie des ouvrages du réseau : réservoirs, stations de pompage, captages, canalisations et compteurs.',
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            @php
                $inputClass = 'w-full rounded-md border border-stroke bg-white px-4 py-2.5 text-sm text-dark outline-none focus:border-primary dark:border-dark-3 dark:bg-dark-2 dark:text-white';
            @endphp

            <form method="GET" class="mb-8 flex flex-wrap gap-3">
                <select name="zone_id" class="{{ $inputClass }} max-w-xs">
                    <option value="">Toutes les zones</option>
                    @foreach ($zones as $zone)
                        <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
                    @endforeach
                </select>
                <select name="type" class="{{ $inputClass }} max-w-xs">
                    <option value="">Tous les types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected($currentType === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                    @endforeach
                </select>
                <select name="statut" class="{{ $inputClass }} max-w-xs">
                    <option value="">Tous les statuts</option>
                    @foreach ($statuts as $statut)
                        <option value="{{ $statut }}" @selected($currentStatut === $statut)>{{ ucfirst(str_replace('_', ' ', $statut)) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-dark">Filtrer</button>
                <a href="{{ route('public.infrastructures.index') }}" class="rounded-md border border-stroke px-5 py-2.5 text-sm text-body-color hover:border-primary hover:text-primary dark:border-dark-3 dark:text-dark-6">Réinitialiser</a>
            </form>

            {{-- Carte schématique --}}
            <div class="mb-10">
                <h2 class="mb-4 text-xl font-bold text-dark dark:text-white">Carte du réseau</h2>

                @if ($mapPoints->isNotEmpty())
                    @php
                        $lats = $mapPoints->pluck('latitude');
                        $lngs = $mapPoints->pluck('longitude');
                        $minLat = $lats->min();
                        $maxLat = $lats->max();
                        $minLng = $lngs->min();
                        $maxLng = $lngs->max();
                        $latSpan = max(0.0001, $maxLat - $minLat);
                        $lngSpan = max(0.0001, $maxLng - $minLng);
                    @endphp

                    <div class="relative h-[420px] overflow-hidden rounded-md border border-stroke bg-blue-50 shadow-1 dark:border-dark-3 dark:bg-dark-2">
                        <div class="absolute inset-0 opacity-40" style="background-image: linear-gradient(rgba(55,88,249,.12) 1px, transparent 1px), linear-gradient(90deg, rgba(55,88,249,.12) 1px, transparent 1px); background-size: 48px 48px;"></div>

                        @foreach ($mapPoints as $point)
                            @php
                                $x = 6 + (($point['longitude'] - $minLng) / $lngSpan) * 88;
                                $y = 6 + (($maxLat - $point['latitude']) / $latSpan) * 88;
                            @endphp
                            <a href="{{ $point['url'] }}"
                               class="group absolute z-10 -translate-x-1/2 -translate-y-1/2"
                               style="left: {{ $x }}%; top: {{ $y }}%;"
                               title="{{ $point['nom'] }} — {{ $point['type'] }} ({{ $point['statut'] }})">
                                <span class="block h-4 w-4 rounded-full border-2 border-white bg-primary shadow-2 transition group-hover:scale-150"></span>
                                <span class="pointer-events-none absolute left-5 top-1/2 hidden -translate-y-1/2 whitespace-nowrap rounded bg-white px-2 py-1 text-xs text-dark shadow-2 group-hover:block dark:bg-dark-3 dark:text-white">
                                    {{ $point['nom'] }}
                                </span>
                            </a>
                        @endforeach

                        <p class="absolute bottom-3 left-4 text-xs text-body-color dark:text-dark-6">
                            Positionnement schématique à partir des coordonnées déclarées. Survolez un point pour l'identifier.
                        </p>
                    </div>
                @else
                    <div class="rounded-md bg-white p-8 text-center shadow-1 dark:bg-dark-2">
                        <p class="text-sm text-body-color dark:text-dark-6">Aucune infrastructure géolocalisée ne correspond aux filtres.</p>
                    </div>
                @endif
            </div>

            {{-- Liste --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xl font-bold text-dark dark:text-white">Ouvrages répertoriés</h2>
                <p class="text-sm text-body-color dark:text-dark-6">{{ $infrastructures->count() }} résultat(s)</p>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($infrastructures as $infrastructure)
                    @php
                        $statutTone = match ($infrastructure->statut) {
                            'actif' => 'bg-green-50 text-secondary',
                            'maintenance' => 'bg-yellow-50 text-yellow-600',
                            'hors_service' => 'bg-red-50 text-red-500',
                            default => 'bg-blue-50 text-primary',
                        };
                    @endphp
                    <a href="{{ route('public.infrastructures.show', $infrastructure) }}" class="rounded-md bg-white p-6 shadow-1 transition hover:-translate-y-1 dark:bg-dark-2">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-bold text-dark dark:text-white">{{ $infrastructure->nom }}</h3>
                                <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ $infrastructure->typeLabel() }} · {{ $infrastructure->zone?->nom ?? 'Zone non précisée' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statutTone }}">{{ $infrastructure->statutLabel() }}</span>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-stroke pt-4 text-xs dark:border-dark-3">
                            <div>
                                <dt class="text-body-color dark:text-dark-6">Capacité</dt>
                                <dd class="mt-1 font-medium text-dark dark:text-white">{{ $infrastructure->capacite !== null ? $infrastructure->capacite.' m³' : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-body-color dark:text-dark-6">Mise en service</dt>
                                <dd class="mt-1 font-medium text-dark dark:text-white">{{ $infrastructure->date_installation?->format('d/m/Y') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </a>
                @empty
                    <div class="md:col-span-2 xl:col-span-3">
                        <div class="rounded-md bg-white p-8 text-center shadow-1 dark:bg-dark-2">
                            <p class="text-sm text-body-color dark:text-dark-6">Aucune infrastructure ne correspond aux filtres.</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
