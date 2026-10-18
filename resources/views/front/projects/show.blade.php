@extends('front.layouts.app')

@section('title', 'AquaSecure — '.$project->titre)

@php use App\Services\ProjectDeadlineIndicators; @endphp

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Projet · '.ucfirst(str_replace('_', ' ', $project->type)),
        'heading' => $project->titre,
        'lead' => ($project->zone?->nom ?? 'Zone non précisée').' · Statut : '.$project->statutLabel(),
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            <a href="{{ route('public.projects.index') }}" class="mb-6 inline-block text-sm font-medium text-primary hover:underline">← Tous les projets</a>

            @php
                $inputClass = 'w-full rounded-md border border-stroke bg-transparent px-5 py-3 text-sm text-dark outline-none focus:border-primary dark:border-dark-3 dark:text-white';
                $rate = $metrics->fundingRate;
            @endphp

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {{-- Détail --}}
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2 md:p-8">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-gray-1 px-3 py-1 text-xs font-medium text-body-color dark:bg-dark-3 dark:text-dark-7">{{ ucfirst(str_replace('_', ' ', $project->type)) }}</span>
                            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-primary">{{ $project->statutLabel() }}</span>
                            <span class="rounded-full bg-gray-1 px-3 py-1 text-xs font-medium text-body-color dark:bg-dark-3 dark:text-dark-7">Avancement {{ $project->avancement_pourcentage }} %</span>
                        </div>

                        <h2 class="mt-5 text-lg font-bold text-dark dark:text-white">Présentation</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-body-color dark:text-dark-6">{{ $project->description ?: 'Aucune description renseignée.' }}</p>

                        <div class="mt-5 h-2.5 w-full rounded-full bg-gray-1 dark:bg-dark-3">
                            <div class="h-2.5 rounded-full bg-secondary" style="width: {{ max(2, $project->avancement_pourcentage) }}%"></div>
                        </div>

                        <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-stroke pt-5 text-sm dark:border-dark-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-body-color dark:text-dark-6">Zone</dt>
                                <dd class="mt-1 font-medium text-dark dark:text-white">{{ $project->zone?->nom ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-body-color dark:text-dark-6">Infrastructure</dt>
                                <dd class="mt-1 font-medium text-dark dark:text-white">{{ $project->infrastructure?->nom ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-body-color dark:text-dark-6">Début</dt>
                                <dd class="mt-1 font-medium text-dark dark:text-white">{{ $project->date_debut?->format('d/m/Y') ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-body-color dark:text-dark-6">Fin prévue</dt>
                                <dd class="mt-1 font-medium text-dark dark:text-white">
                                    {{ $project->date_fin_prevue?->format('d/m/Y') ?? '—' }}
                                    @php($echeanceProjet = ProjectDeadlineIndicators::projectStatus($project))
                                    @if (! ProjectDeadlineIndicators::projectIsClosed($project) && $echeanceProjet === ProjectDeadlineIndicators::ECHEANCE_DEPASSEE)
                                        <span class="ml-1 inline-block rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600"
                                              title="{{ ProjectDeadlineIndicators::projectExplanation($project) }}">Échéance dépassée</span>
                                    @elseif (! ProjectDeadlineIndicators::projectIsClosed($project) && $echeanceProjet === ProjectDeadlineIndicators::ECHEANCE_PROCHE)
                                        <span class="ml-1 inline-block rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-600"
                                              title="{{ ProjectDeadlineIndicators::projectExplanation($project) }}">Échéance proche</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Phases --}}
                    <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                        <h2 class="text-lg font-bold text-dark dark:text-white">Phases du projet</h2>

                        @forelse ($project->projectPhases as $phase)
                            <div class="mt-4 border-b border-stroke pb-4 last:border-0 last:pb-0 dark:border-dark-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-dark dark:text-white">{{ $phase->nom }}</p>
                                    <span class="text-xs text-body-color dark:text-dark-6">{{ $phase->avancement }} %</span>
                                </div>
                                <p class="mt-1 text-xs text-body-color dark:text-dark-6">
                                    {{ $phase->contractor?->nom ?? 'Entreprise non affectée' }}
                                    @if ($phase->date_debut || $phase->date_fin)
                                        · {{ $phase->date_debut?->format('d/m/Y') ?? '—' }} → {{ $phase->date_fin?->format('d/m/Y') ?? '—' }}
                                    @endif
                                    @php($echeancePhase = ProjectDeadlineIndicators::phaseStatus($phase))
                                    @if (! ProjectDeadlineIndicators::projectIsClosed($project) && $echeancePhase === ProjectDeadlineIndicators::ECHEANCE_DEPASSEE)
                                        <span class="ml-1 inline-block rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600"
                                              title="{{ ProjectDeadlineIndicators::phaseExplanation($phase) }}">En retard</span>
                                    @elseif (! ProjectDeadlineIndicators::projectIsClosed($project) && $echeancePhase === ProjectDeadlineIndicators::ECHEANCE_PROCHE)
                                        <span class="ml-1 inline-block rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-600"
                                              title="{{ ProjectDeadlineIndicators::phaseExplanation($phase) }}">Échéance proche</span>
                                    @endif
                                </p>
                                <div class="mt-2 h-1.5 w-full rounded-full bg-gray-1 dark:bg-dark-3">
                                    <div class="h-1.5 rounded-full {{ $phase->isCompleted() ? 'bg-secondary' : 'bg-primary' }}" style="width: {{ max(1, min(100, $phase->avancement)) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="mt-3 text-sm text-body-color dark:text-dark-6">Aucune phase renseignée.</p>
                        @endforelse
                    </div>

                    {{-- Documents publics --}}
                    <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                        <h2 class="text-lg font-bold text-dark dark:text-white">Documents publics</h2>

                        @forelse ($project->publicDocuments as $document)
                            <div class="flex items-center justify-between gap-3 border-b border-stroke py-3 last:border-0 dark:border-dark-3">
                                <div>
                                    <p class="text-sm font-medium text-dark dark:text-white">{{ $document->titre }}</p>
                                    <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ $document->typeLabel() }}</p>
                                </div>
                                <a href="{{ route('public.projects.documents.download', [$project, $document]) }}" class="text-sm font-medium text-primary hover:underline">Télécharger</a>
                            </div>
                        @empty
                            <p class="mt-3 text-sm text-body-color dark:text-dark-6">Aucun document public pour ce projet.</p>
                        @endforelse
                    </div>

                    {{-- Assistant --}}
                    <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                        <h2 class="text-lg font-bold text-dark dark:text-white">Assistant projet</h2>
                        <p class="mt-1 text-xs text-body-color dark:text-dark-6">
                            Réponses générées localement à partir des données du projet, sans fournisseur d'IA externe.
                        </p>

                        @if ($assistantAnswer)
                            <div class="mt-4 rounded-md bg-gray-1 p-4 dark:bg-dark-3">
                                <p class="text-xs font-semibold text-body-color dark:text-dark-6">Votre question : {{ $assistantQuestion }}</p>
                                <p class="mt-2 text-sm text-dark dark:text-white">{{ $assistantAnswer }}</p>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('public.projects.assistant', $project) }}" class="mt-4">
                            @csrf
                            <label for="question" class="mb-2 block text-sm font-medium text-dark dark:text-white">Posez une question</label>
                            <textarea id="question" name="question" rows="2"
                                      class="{{ $inputClass }} @error('question') border-red-500 @enderror"
                                      placeholder="Ex. : Quel est le budget et son financement ?">{{ old('question') }}</textarea>
                            @error('question')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror

                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($assistantSuggestions as $suggestion)
                                    <span class="rounded-full border border-stroke px-3 py-1 text-xs text-body-color dark:border-dark-3 dark:text-dark-6">{{ $suggestion }}</span>
                                @endforeach
                            </div>

                            <button type="submit" class="mt-4 rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-dark">Interroger l'assistant</button>
                        </form>
                    </div>
                </div>

                {{-- Financement --}}
                <div class="space-y-6">
                    <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                        <h2 class="text-lg font-bold text-dark dark:text-white">Financement</h2>

                        <p class="mt-4 text-3xl font-extrabold text-primary">{{ number_format($rate, 1, ',', ' ') }} %</p>
                        <p class="text-xs text-body-color dark:text-dark-6">du budget prévu est financé</p>

                        <div class="mt-3 h-2 w-full rounded-full bg-gray-1 dark:bg-dark-3">
                            <div class="h-2 rounded-full bg-secondary" style="width: {{ max(2, min(100, $rate)) }}%"></div>
                        </div>

                        <dl class="mt-5 space-y-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-body-color dark:text-dark-6">Budget prévu</dt>
                                <dd class="font-semibold text-dark dark:text-white">{{ number_format($metrics->budgetPrevu, 2, ',', ' ') }} €</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-body-color dark:text-dark-6">Financements enregistrés</dt>
                                <dd class="font-semibold text-dark dark:text-white">{{ number_format($metrics->fundingTotal, 2, ',', ' ') }} €</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-body-color dark:text-dark-6">Reste à financer</dt>
                                <dd class="font-semibold text-dark dark:text-white">{{ number_format($metrics->fundingGap, 2, ',', ' ') }} €</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-body-color dark:text-dark-6">Dont dons simulés</dt>
                                <dd class="font-semibold text-dark dark:text-white">{{ number_format($donationsTotal, 2, ',', ' ') }} € ({{ $donationsCount }})</dd>
                            </div>
                        </dl>

                        <h3 class="mt-6 text-sm font-semibold uppercase tracking-wide text-body-color dark:text-dark-6">Sources de financement</h3>
                        @forelse ($project->fundings as $funding)
                            <div class="flex items-center justify-between border-b border-stroke py-2 text-sm last:border-0 dark:border-dark-3">
                                <span class="text-body-color dark:text-dark-6">{{ $funding->isDonation() ? 'Don citoyen (simulé)' : $funding->sourceLabel() }}</span>
                                <span class="font-medium text-dark dark:text-white">{{ number_format((float) $funding->montant, 2, ',', ' ') }} €</span>
                            </div>
                        @empty
                            <p class="mt-2 text-sm text-body-color dark:text-dark-6">Aucun financement enregistré.</p>
                        @endforelse
                    </div>

                    {{-- Don simulé --}}
                    @if ($project->statut !== 'annulé')
                        <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                            <h2 class="text-lg font-bold text-dark dark:text-white">Soutenir ce projet</h2>
                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">
                                Don entièrement simulé : aucun paiement réel n'est effectué et aucune coordonnée bancaire n'est demandée.
                            </p>

                            @if (auth()->check() && auth()->user()->isCitoyen())
                                <form method="POST" action="{{ route('citizen.projects.donate', $project) }}" class="mt-4">
                                    @csrf
                                    <label for="montant" class="mb-2 block text-sm font-medium text-dark dark:text-white">Montant (€)</label>
                                    <input type="text" id="montant" name="montant" value="{{ old('montant') }}"
                                           class="{{ $inputClass }} @error('montant') border-red-500 @enderror" placeholder="25.00">
                                    @error('montant')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                                    <button type="submit" class="mt-3 w-full rounded-md bg-secondary px-5 py-2.5 text-sm font-medium text-white hover:bg-[#0BB489]">Faire un don simulé</button>
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="mt-4 inline-flex w-full items-center justify-center rounded-md border border-primary px-5 py-2.5 text-sm font-medium text-primary hover:bg-primary hover:text-white">
                                    Se connecter en tant que citoyen pour participer
                                </a>
                            @endif
                        </div>
                    @endif

                    <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                        <h2 class="text-lg font-bold text-dark dark:text-white">Responsable</h2>
                        <p class="mt-2 text-sm text-dark dark:text-white">{{ $project->responsable?->name ?? 'Non désigné' }}</p>
                        @if ($project->responsable)
                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">Contact via les services techniques de la collectivité.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
