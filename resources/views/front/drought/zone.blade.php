@extends('front.layouts.app')

@section('title', 'AquaSecure — Sécheresse · '.$zone->nom)

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Sécheresse',
        'heading' => $zone->nom,
        'lead' => 'Niveaux des ressources, prévision de consommation, restrictions et coupures pour cette zone.',
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            <a href="{{ route('public.drought.index') }}" class="mb-6 inline-block text-sm font-medium text-primary hover:underline">← Toutes les zones</a>

            @php
                $riskTone = match ($risk['niveau']) {
                    'critique' => 'bg-red-50 text-red-500',
                    'eleve' => 'bg-orange-50 text-orange-500',
                    'modere' => 'bg-yellow-50 text-yellow-600',
                    default => 'bg-green-50 text-secondary',
                };
                $riskLabel = match ($risk['niveau']) {
                    'critique' => 'Critique',
                    'eleve' => 'Élevé',
                    'modere' => 'Modéré',
                    default => 'Faible',
                };
                $maxValue = collect($forecast['history'])->merge($forecast['forecast'])->max('valeur') ?: 1;
            @endphp

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {{-- Risque --}}
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-dark dark:text-white">Indice de risque</h2>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $riskTone }}">{{ $riskLabel }}</span>
                    </div>

                    <p class="mt-5 text-5xl font-extrabold text-primary">{{ $risk['score'] }}<span class="text-xl text-body-color dark:text-dark-6"> / 100</span></p>

                    <div class="mt-4 h-2 w-full rounded-full bg-gray-1 dark:bg-dark-3">
                        <div class="h-2 rounded-full {{ $risk['score'] >= 75 ? 'bg-red-500' : ($risk['score'] >= 50 ? 'bg-orange-400' : ($risk['score'] >= 25 ? 'bg-yellow-400' : 'bg-secondary')) }}" style="width: {{ max(3, $risk['score']) }}%"></div>
                    </div>

                    <ul class="mt-5 space-y-2 text-xs text-body-color dark:text-dark-6">
                        @foreach ($risk['facteurs'] as $facteur)
                            <li class="flex gap-2">
                                <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-body-color"></span>
                                {{ $facteur }}
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-5 text-xs text-body-color dark:text-dark-6">Calcul local indicatif, sans service externe.</p>
                </div>

                {{-- Prévision --}}
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2 lg:col-span-2">
                    <h2 class="text-lg font-bold text-dark dark:text-white">Prévision de consommation</h2>
                    <p class="mt-1 text-sm text-body-color dark:text-dark-6">{{ $forecast['message'] }}</p>

                    @if ($forecast['forecast'] !== [])
                        <div class="mt-5 space-y-3">
                            @foreach ($forecast['history'] as $point)
                                <div class="flex items-center gap-3">
                                    <span class="w-16 shrink-0 text-xs text-body-color dark:text-dark-6">{{ $point['label'] }}</span>
                                    <div class="h-2.5 flex-1 rounded-full bg-gray-1 dark:bg-dark-3">
                                        <div class="h-2.5 rounded-full bg-primary" style="width: {{ max(2, ($point['valeur'] / $maxValue) * 100) }}%"></div>
                                    </div>
                                    <span class="w-24 shrink-0 text-right text-xs text-dark dark:text-dark-7">{{ number_format($point['valeur'], 0, ',', ' ') }} m³</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 grid grid-cols-3 gap-3">
                            @foreach ($forecast['forecast'] as $point)
                                <div class="rounded-md border border-dashed border-primary/40 bg-blue-50 px-3 py-3 text-center">
                                    <p class="text-xs text-body-color">{{ $point['label'] }}</p>
                                    <p class="mt-1 text-sm font-semibold text-primary">{{ number_format($point['valeur'], 0, ',', ' ') }} m³</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-5 rounded-md border border-dashed border-stroke p-5 text-sm text-body-color dark:border-dark-3 dark:text-dark-6">
                            {{ $forecast['message'] }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Niveaux --}}
            <div class="mt-8">
                <h2 class="mb-4 text-xl font-bold text-dark dark:text-white">Niveaux des ressources</h2>

                <div class="overflow-hidden rounded-md bg-white shadow-1 dark:bg-dark-2">
                    @if ($levels->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-stroke text-left text-xs uppercase text-body-color dark:border-dark-3 dark:text-dark-6">
                                        <th class="px-6 py-4 font-medium">Source</th>
                                        <th class="px-6 py-4 font-medium">Type</th>
                                        <th class="px-6 py-4 font-medium">Niveau</th>
                                        <th class="px-6 py-4 font-medium">État</th>
                                        <th class="px-6 py-4 font-medium">Mesuré le</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($levels as $level)
                                        @php
                                            $tone = match ($level->niveauStatut()) {
                                                'critique' => 'bg-red-50 text-red-500',
                                                'alerte' => 'bg-yellow-50 text-yellow-600',
                                                default => 'bg-green-50 text-secondary',
                                            };
                                        @endphp
                                        <tr class="border-b border-stroke last:border-0 dark:border-dark-3">
                                            <td class="px-6 py-4 text-sm font-medium text-dark dark:text-white">{{ $level->nom_source }}</td>
                                            <td class="px-6 py-4 text-sm text-body-color dark:text-dark-6">{{ $level->typeLabel() }}</td>
                                            <td class="px-6 py-4 text-sm font-semibold text-dark dark:text-white">{{ $level->niveau }} %</td>
                                            <td class="px-6 py-4"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tone }}">{{ $level->niveauStatutLabel() }}</span></td>
                                            <td class="px-6 py-4 text-sm text-body-color dark:text-dark-6">{{ $level->date_mesure?->format('d/m/Y H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="px-6 py-10 text-center text-sm text-body-color dark:text-dark-6">Aucune mesure de niveau pour cette zone.</p>
                    @endif
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Restrictions --}}
                <div>
                    <h2 class="mb-4 text-xl font-bold text-dark dark:text-white">Restrictions</h2>

                    @forelse ($restrictions as $restriction)
                        <div class="mb-3 rounded-md border-l-4 {{ $restriction->isActive() ? 'border-yellow-400' : 'border-blue-300' }} bg-white p-5 shadow-1 dark:bg-dark-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-base font-bold text-dark dark:text-white">{{ $restriction->titre }}</h3>
                                <span class="rounded-full bg-gray-1 px-2.5 py-0.5 text-xs font-medium text-body-color dark:bg-dark-3 dark:text-dark-7">{{ $restriction->statutLabel() }}</span>
                            </div>
                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ $restriction->typeLabel() }} · {{ $restriction->zone?->nom ?? 'Toutes les zones' }}</p>
                            @if ($restriction->usages_interdits)
                                <p class="mt-2 whitespace-pre-line text-sm text-body-color dark:text-dark-6">{{ $restriction->usages_interdits }}</p>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                            <p class="text-sm text-body-color dark:text-dark-6">Aucune restriction pour cette zone.</p>
                        </div>
                    @endforelse
                </div>

                {{-- Coupures --}}
                <div>
                    <h2 class="mb-4 text-xl font-bold text-dark dark:text-white">Coupures programmées</h2>

                    @forelse ($cuts as $cut)
                        <div class="mb-3 rounded-md border-l-4 border-primary bg-white p-5 shadow-1 dark:bg-dark-2">
                            <h3 class="text-base font-bold text-dark dark:text-white">{{ $cut->motif }}</h3>
                            <p class="mt-1 text-sm text-body-color dark:text-dark-6">
                                Du {{ $cut->date_debut?->format('d/m/Y H:i') }} au {{ $cut->date_fin?->format('d/m/Y H:i') }}
                            </p>
                            <span class="mt-2 inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-primary">{{ $cut->statutLabel() }}</span>
                        </div>
                    @empty
                        <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                            <p class="text-sm text-body-color dark:text-dark-6">Aucune coupure programmée pour cette zone.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
