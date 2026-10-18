@extends('front.layouts.app')

@section('title', 'AquaSecure — Qualité de l\'eau · '.$zone->nom)

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Qualité de l\'eau',
        'heading' => $zone->nom,
        'lead' => 'Détail des prélèvements, paramètres analysés et alertes actives pour cette zone.',
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            <a href="{{ route('public.quality.index') }}" class="mb-6 inline-block text-sm font-medium text-primary hover:underline">← Toutes les zones</a>

            {{-- Indicateurs --}}
            <div class="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    <p class="text-sm text-body-color dark:text-dark-6">Score moyen (6 derniers prélèvements)</p>
                    <p class="mt-2 text-3xl font-extrabold text-primary">{{ $summary['score_moyen'] !== null ? $summary['score_moyen'].' %' : '—' }}</p>
                </div>
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    <p class="text-sm text-body-color dark:text-dark-6">Conformité sur 90 jours</p>
                    <p class="mt-2 text-3xl font-extrabold text-primary">{{ $summary['conformite_90j'] !== null ? $summary['conformite_90j'].' %' : '—' }}</p>
                </div>
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    <p class="text-sm text-body-color dark:text-dark-6">Alertes actives</p>
                    <p class="mt-2 text-3xl font-extrabold {{ $summary['alertes_actives'] > 0 ? 'text-red-500' : 'text-secondary' }}">{{ $summary['alertes_actives'] }}</p>
                </div>
            </div>

            {{-- Alertes --}}
            @if ($alerts->isNotEmpty())
                <div class="mb-8">
                    <h2 class="mb-4 text-xl font-bold text-dark dark:text-white">Alertes en cours</h2>
                    <div class="space-y-3">
                        @foreach ($alerts as $alert)
                            <div class="rounded-md border-l-4 p-5 {{ $alert->niveau === 'critique' ? 'border-red-300 bg-red-50' : ($alert->niveau === 'avertissement' ? 'border-yellow-300 bg-yellow-50' : 'border-blue-200 bg-blue-50') }}">
                                <p class="text-sm font-medium text-dark">{{ $alert->message }}</p>
                                <p class="mt-1 text-xs text-body-color">{{ $alert->niveauLabel() }} · {{ $alert->created_at?->format('d/m/Y H:i') }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Prélèvements --}}
            <h2 class="mb-4 text-xl font-bold text-dark dark:text-white">Prélèvements</h2>

            @if ($samples->isNotEmpty())
                <div class="space-y-3">
                    @foreach ($samples as $sample)
                        <details class="rounded-md bg-white shadow-1 dark:bg-dark-2" {{ $loop->first ? 'open' : '' }}>
                            <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-3 px-6 py-4">
                                <div>
                                    <p class="font-mono text-xs text-body-color dark:text-dark-6">{{ $sample->reference }}</p>
                                    <p class="mt-1 text-sm font-medium text-dark dark:text-white">{{ $sample->date_prelevement?->format('d/m/Y H:i') }} · {{ $sample->source ?? 'Source non précisée' }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold text-dark dark:text-white">{{ $sample->score !== null ? $sample->score.' %' : '—' }}</span>
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $sample->statut === 'conforme' ? 'bg-green-50 text-secondary' : 'bg-red-50 text-red-500' }}">{{ $sample->statutLabel() }}</span>
                                </div>
                            </summary>

                            <div class="border-t border-stroke px-6 py-4 dark:border-dark-3">
                                @php $aiSummary = $aiSummaries[$sample->id] ?? null; @endphp

                                @if ($aiSummary)
                                    <div class="mb-4 rounded-md border border-blue-100 bg-blue-50/60 p-4 dark:border-dark-3 dark:bg-dark-3/40">
                                        <p class="text-xs font-semibold uppercase text-primary">Analyse intelligente de la qualité de l'eau</p>
                                        <p class="mt-2 text-sm text-body-color dark:text-dark-6">{{ $aiSummary['resume'] }}</p>

                                        @if (! empty($aiSummary['anomalies']))
                                            <ul class="mt-3 space-y-1 text-xs text-body-color dark:text-dark-6">
                                                @foreach ($aiSummary['anomalies'] as $anomaly)
                                                    <li>• {{ $anomaly }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="mt-2 text-xs text-body-color dark:text-dark-6">Aucune anomalie détectée par l'analyse intelligente.</p>
                                        @endif
                                    </div>
                                @endif

                                @if ($sample->parameters->isNotEmpty())
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm">
                                            <thead>
                                                <tr class="text-left text-xs uppercase text-body-color dark:text-dark-6">
                                                    <th class="py-2 pr-4 font-medium">Paramètre</th>
                                                    <th class="py-2 pr-4 font-medium">Valeur</th>
                                                    <th class="py-2 pr-4 font-medium">Seuils</th>
                                                    <th class="py-2 font-medium">Conformité</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($sample->parameters as $parameter)
                                                    <tr class="border-t border-stroke dark:border-dark-3">
                                                        <td class="py-2 pr-4 text-dark dark:text-white">{{ $parameter->parametreLabel() }}</td>
                                                        <td class="py-2 pr-4 text-body-color dark:text-dark-6">{{ $parameter->valeur }} {{ $parameter->unite }}</td>
                                                        <td class="py-2 pr-4 text-body-color dark:text-dark-6">
                                                            {{ $parameter->seuil_min !== null ? $parameter->seuil_min : '—' }}
                                                            –
                                                            {{ $parameter->seuil_max !== null ? $parameter->seuil_max : '—' }}
                                                        </td>
                                                        <td class="py-2">
                                                            <span class="font-medium {{ $parameter->est_conforme ? 'text-secondary' : 'text-red-500' }}">{{ $parameter->est_conforme ? 'Conforme' : 'Non conforme' }}</span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-sm text-body-color dark:text-dark-6">Aucun paramètre détaillé pour ce prélèvement.</p>
                                @endif

                                @if ($sample->notes)
                                    <p class="mt-4 text-xs text-body-color dark:text-dark-6">{{ $sample->notes }}</p>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>
            @else
                <div class="rounded-md bg-white p-8 text-center shadow-1 dark:bg-dark-2">
                    <p class="text-sm text-body-color dark:text-dark-6">Aucun prélèvement publié pour cette zone.</p>
                </div>
            @endif
        </div>
    </section>
@endsection
