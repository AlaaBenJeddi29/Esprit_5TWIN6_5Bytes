@extends('front.layouts.app')

@section('title', 'AquaSecure — Sécheresse & continuité du service')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Ressource en eau',
        'heading' => 'Sécheresse & continuité du service',
        'lead' => 'Indice de risque par zone, restrictions d\'usage en vigueur et coupures programmées.',
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            {{-- Risque par zone --}}
            <div class="mb-12">
                <h2 class="mb-6 text-2xl font-bold text-dark dark:text-white">Risque sécheresse par zone</h2>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($zoneRisks as $item)
                        @php
                            $zone = $item['zone'];
                            $risk = $item['risk'];
                            $tone = match ($risk['niveau']) {
                                'critique' => 'bg-red-50 text-red-500',
                                'eleve' => 'bg-orange-50 text-orange-500',
                                'modere' => 'bg-yellow-50 text-yellow-600',
                                default => 'bg-green-50 text-secondary',
                            };
                            $label = match ($risk['niveau']) {
                                'critique' => 'Critique',
                                'eleve' => 'Élevé',
                                'modere' => 'Modéré',
                                default => 'Faible',
                            };
                        @endphp

                        <a href="{{ route('public.drought.zone', $zone) }}" class="rounded-md bg-white p-6 shadow-1 transition hover:-translate-y-1 dark:bg-dark-2">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-lg font-bold text-dark dark:text-white">{{ $zone->nom }}</h3>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tone }}">{{ $label }}</span>
                            </div>

                            <div class="mt-5 flex items-end gap-3">
                                <span class="text-4xl font-extrabold text-primary">{{ $risk['score'] }}</span>
                                <span class="pb-1 text-sm text-body-color dark:text-dark-6">/ 100</span>
                            </div>
                            <div class="mt-3 h-2 w-full rounded-full bg-gray-1 dark:bg-dark-3">
                                <div class="h-2 rounded-full {{ $risk['score'] >= 75 ? 'bg-red-500' : ($risk['score'] >= 50 ? 'bg-orange-400' : ($risk['score'] >= 25 ? 'bg-yellow-400' : 'bg-secondary')) }}" style="width: {{ max(3, $risk['score']) }}%"></div>
                            </div>

                            <p class="mt-4 text-xs text-body-color dark:text-dark-6">{{ $risk['facteurs'][0] ?? 'Aucun facteur notable.' }}</p>
                        </a>
                    @empty
                        <div class="md:col-span-2 xl:col-span-3">
                            <div class="rounded-md bg-white p-8 text-center shadow-1 dark:bg-dark-2">
                                <p class="text-base text-body-color dark:text-dark-6">Aucune zone renseignée.</p>
                            </div>
                        </div>
                    @endforelse
                </div>
                <p class="mt-3 text-xs text-body-color dark:text-dark-6">
                    L'indice est calculé localement à partir des niveaux mesurés, des consommations, des restrictions et alertes actives. Il est indicatif.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Restrictions --}}
                <div>
                    <h2 class="mb-4 text-2xl font-bold text-dark dark:text-white">Restrictions en vigueur</h2>

                    @forelse ($restrictions as $restriction)
                        <div class="mb-3 rounded-md border-l-4 border-yellow-400 bg-white p-5 shadow-1 dark:bg-dark-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-base font-bold text-dark dark:text-white">{{ $restriction->titre }}</h3>
                                <span class="rounded-full bg-yellow-50 px-2.5 py-0.5 text-xs font-medium text-yellow-600">{{ $restriction->typeLabel() }}</span>
                            </div>
                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ $restriction->zone?->nom ?? 'Toutes les zones' }} · depuis le {{ $restriction->date_debut?->format('d/m/Y') }}</p>
                            @if ($restriction->description)
                                <p class="mt-2 text-sm text-body-color dark:text-dark-6">{{ $restriction->description }}</p>
                            @endif
                            @if ($restriction->usages_interdits)
                                <p class="mt-2 whitespace-pre-line text-sm text-dark dark:text-dark-7"><span class="font-medium">Usages interdits :</span> {{ $restriction->usages_interdits }}</p>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                            <p class="text-sm text-body-color dark:text-dark-6">Aucune restriction d'usage active.</p>
                        </div>
                    @endforelse
                </div>

                {{-- Coupures --}}
                <div>
                    <h2 class="mb-4 text-2xl font-bold text-dark dark:text-white">Coupures programmées</h2>

                    @forelse ($cuts as $cut)
                        <div class="mb-3 rounded-md border-l-4 border-primary bg-white p-5 shadow-1 dark:bg-dark-2">
                            <h3 class="text-base font-bold text-dark dark:text-white">{{ $cut->motif }}</h3>
                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">
                                {{ $cut->zone?->nom ?? 'Zone non précisée' }}
                                @if ($cut->infrastructure)
                                    · {{ $cut->infrastructure->nom }}
                                @endif
                            </p>
                            <p class="mt-2 text-sm text-body-color dark:text-dark-6">
                                Du {{ $cut->date_debut?->format('d/m/Y H:i') }} au {{ $cut->date_fin?->format('d/m/Y H:i') }}
                            </p>
                            <span class="mt-2 inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-primary">{{ $cut->statutLabel() }}</span>
                        </div>
                    @empty
                        <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                            <p class="text-sm text-body-color dark:text-dark-6">Aucune coupure programmée actuellement.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
