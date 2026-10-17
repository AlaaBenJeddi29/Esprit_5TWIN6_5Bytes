@extends('front.layouts.app')

@section('title', 'AquaSecure — Qualité de l\'eau')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Transparence',
        'heading' => 'Qualité de l\'eau distribuée',
        'lead' => 'Résultats des prélèvements, conformité réglementaire et alertes actives, zone par zone.',
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            {{-- Synthèse par zone --}}
            <div class="mb-12">
                <h2 class="mb-6 text-2xl font-bold text-dark dark:text-white">Synthèse par zone</h2>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($summaries as $item)
                        @php
                            $zone = $item['zone'];
                            $summary = $item['summary'];
                            $latest = $summary['latest'];
                            $statut = $latest?->statut;
                            $tone = match ($statut) {
                                'conforme' => 'text-secondary',
                                'non_conforme' => 'text-red-500',
                                default => 'text-body-color dark:text-dark-6',
                            };
                            $tendance = match ($summary['tendance']) {
                                'amelioration' => ['label' => 'En amélioration', 'class' => 'bg-green-50 text-secondary'],
                                'degradation' => ['label' => 'En dégradation', 'class' => 'bg-red-50 text-red-500'],
                                default => ['label' => 'Stable', 'class' => 'bg-gray-1 text-body-color dark:bg-dark-3 dark:text-dark-7'],
                            };
                        @endphp

                        <a href="{{ route('public.quality.zone', $zone) }}" class="rounded-md bg-white p-6 shadow-1 transition hover:-translate-y-1 dark:bg-dark-2">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-bold text-dark dark:text-white">{{ $zone->nom }}</h3>
                                    <p class="mt-1 text-xs text-body-color dark:text-dark-6">
                                        {{ $latest ? 'Dernier prélèvement : '.$latest->date_prelevement?->format('d/m/Y') : 'Aucun prélèvement publié' }}
                                    </p>
                                </div>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tendance['class'] }}">{{ $tendance['label'] }}</span>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-4 border-t border-stroke pt-4 dark:border-dark-3">
                                <div>
                                    <p class="text-xs text-body-color dark:text-dark-6">Score moyen</p>
                                    <p class="mt-1 text-2xl font-extrabold {{ $tone }}">{{ $summary['score_moyen'] !== null ? $summary['score_moyen'].' %' : '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-body-color dark:text-dark-6">Conformité 90 jours</p>
                                    <p class="mt-1 text-2xl font-extrabold text-dark dark:text-white">{{ $summary['conformite_90j'] !== null ? $summary['conformite_90j'].' %' : '—' }}</p>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between text-xs">
                                <span class="{{ $tone }} font-medium">{{ $latest?->statutLabel() ?? 'Aucune analyse' }}</span>
                                @if ($summary['alertes_actives'] > 0)
                                    <span class="rounded-full bg-red-50 px-2.5 py-0.5 font-medium text-red-500">{{ $summary['alertes_actives'] }} alerte(s) active(s)</span>
                                @else
                                    <span class="text-body-color dark:text-dark-6">Aucune alerte active</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="md:col-span-2 xl:col-span-3">
                            <div class="rounded-md bg-white p-8 text-center shadow-1 dark:bg-dark-2">
                                <p class="text-base text-body-color dark:text-dark-6">Aucune zone de distribution renseignée.</p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Alertes actives --}}
            <div class="mb-12">
                <h2 class="mb-6 text-2xl font-bold text-dark dark:text-white">Alertes qualité actives</h2>

                @if ($activeAlerts->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($activeAlerts as $alert)
                            @php
                                $tone = match ($alert->niveau) {
                                    'critique' => 'border-red-300 bg-red-50',
                                    'avertissement' => 'border-yellow-300 bg-yellow-50',
                                    default => 'border-blue-200 bg-blue-50',
                                };
                            @endphp
                            <div class="rounded-md border-l-4 p-5 {{ $tone }}">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-dark">{{ $alert->message }}</p>
                                    <span class="text-xs font-semibold uppercase text-body-color">{{ $alert->niveauLabel() }}</span>
                                </div>
                                <p class="mt-1 text-xs text-body-color">
                                    {{ $alert->zone?->nom ?? 'Zone non précisée' }} · {{ $alert->created_at?->format('d/m/Y H:i') }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-md border-l-4 border-secondary bg-white p-5 shadow-1 dark:bg-dark-2">
                        <p class="text-sm text-body-color dark:text-dark-6">Aucune alerte qualité active sur le réseau.</p>
                    </div>
                @endif
            </div>

            {{-- Derniers prélèvements --}}
            <div>
                <h2 class="mb-6 text-2xl font-bold text-dark dark:text-white">Derniers prélèvements publiés</h2>

                <div class="overflow-hidden rounded-md bg-white shadow-1 dark:bg-dark-2">
                    @if ($latestSamples->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-stroke text-left text-xs uppercase text-body-color dark:border-dark-3 dark:text-dark-6">
                                        <th class="px-6 py-4 font-medium">Référence</th>
                                        <th class="px-6 py-4 font-medium">Zone</th>
                                        <th class="px-6 py-4 font-medium">Date</th>
                                        <th class="px-6 py-4 font-medium">Paramètres</th>
                                        <th class="px-6 py-4 font-medium">Score</th>
                                        <th class="px-6 py-4 font-medium">Conformité</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($latestSamples as $sample)
                                        <tr class="border-b border-stroke last:border-0 dark:border-dark-3">
                                            <td class="px-6 py-4 font-mono text-xs text-body-color dark:text-dark-6">{{ $sample->reference }}</td>
                                            <td class="px-6 py-4 text-sm font-medium text-dark dark:text-white">{{ $sample->zone?->nom ?? '—' }}</td>
                                            <td class="px-6 py-4 text-sm text-body-color dark:text-dark-6">{{ $sample->date_prelevement?->format('d/m/Y') }}</td>
                                            <td class="px-6 py-4 text-sm text-body-color dark:text-dark-6">{{ $sample->parameters->count() }}</td>
                                            <td class="px-6 py-4 text-sm text-body-color dark:text-dark-6">{{ $sample->score !== null ? $sample->score.' %' : '—' }}</td>
                                            <td class="px-6 py-4">
                                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $sample->statut === 'conforme' ? 'bg-green-50 text-secondary' : 'bg-red-50 text-red-500' }}">{{ $sample->statutLabel() }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="px-6 py-12 text-center text-sm text-body-color dark:text-dark-6">Aucun prélèvement publié pour le moment.</p>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
