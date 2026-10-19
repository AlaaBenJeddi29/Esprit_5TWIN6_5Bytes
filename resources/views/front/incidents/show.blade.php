@extends('front.layouts.app')

@section('title', 'AquaSecure — Signalement '.$incident->reference)

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Signalement '.$incident->reference,
        'heading' => $incident->title,
        'lead' => 'Déposé le '.$incident->reported_at?->format('d/m/Y à H:i').' · '.$incident->typeLabel().' · '.($incident->zone?->nom ?? 'Zone non précisée'),
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            <div class="mb-6 flex flex-wrap items-center gap-4">
                <a href="{{ route('citizen.incidents.index') }}" class="text-sm font-medium text-primary hover:underline">← Mes signalements</a>
                <a href="{{ route('front.incidents.track.form') }}" class="text-sm text-body-color hover:text-primary dark:text-dark-6">Suivre par référence</a>
            </div>

            @php
                $inputClass = 'w-full rounded-md border border-stroke bg-transparent px-5 py-3 text-sm text-dark outline-none focus:border-primary dark:border-dark-3 dark:text-white';
                $tone = fn (string $statut) => match ($statut) {
                    'nouveau' => 'bg-blue-50 text-primary',
                    'en_cours' => 'bg-yellow-50 text-yellow-600',
                    'en_attente' => 'bg-gray-1 text-body-color dark:bg-dark-3 dark:text-dark-7',
                    'traite' => 'bg-green-50 text-secondary',
                    'rejete' => 'bg-red-50 text-red-500',
                    default => 'bg-gray-1 text-body-color',
                };
                $stages = [
                    'nouveau' => 'Signalé',
                    'en_cours' => 'En cours',
                    'en_attente' => 'En attente',
                    'traite' => 'Traité',
                ];
                $reachedStatuses = $incident->statusHistories->pluck('nouveau_statut')->push($incident->status)->filter()->unique();
                $stageRank = array_flip(array_keys($stages));
                $currentRank = $stageRank[$incident->status] ?? -1;
                $feedback = $incident->feedbacks->first();
                $complaintTone = fn (string $status) => match ($status) {
                    'en_attente' => 'bg-yellow-50 text-yellow-600',
                    'en_examen' => 'bg-blue-50 text-primary',
                    'acceptee' => 'bg-green-50 text-secondary',
                    'rejetee' => 'bg-red-50 text-red-500',
                    'cloturee' => 'bg-gray-1 text-body-color dark:bg-dark-3 dark:text-dark-7',
                    default => 'bg-gray-1 text-body-color',
                };
            @endphp

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {{-- Colonne principale --}}
                <div class="space-y-6 lg:col-span-2">
                {{-- Progression du signalement --}}
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2 md:p-8">
                    <h2 class="text-lg font-bold text-dark dark:text-white">Progression</h2>
                    <ol class="mt-5 grid grid-cols-4 gap-2">
                        @foreach ($stages as $key => $label)
                            @php $done = $currentRank >= $loop->index || $reachedStatuses->contains($key); @endphp
                            <li class="flex flex-col items-center text-center">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold {{ $done ? 'bg-primary text-white' : 'bg-gray-1 text-body-color dark:bg-dark-3 dark:text-dark-6' }}">
                                    {{ $done ? '✓' : $loop->iteration }}
                                </span>
                                <span class="mt-2 text-xs font-medium {{ $done ? 'text-primary' : 'text-body-color dark:text-dark-6' }}">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ol>

                    @if ($incident->status === 'rejete')
                        <div class="mt-4 rounded-md bg-red-50 p-4">
                            <p class="text-sm font-semibold text-red-600">Signalement rejeté</p>
                            @if ($incident->rejection_reason)
                                <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-red-600">Motif de rejet</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-red-700">{{ $incident->rejection_reason }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                    <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2 md:p-8">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $tone($incident->status) }}">{{ $incident->statusLabel() }}</span>
                            <span class="inline-flex rounded-full bg-gray-1 px-3 py-1 text-xs font-medium text-body-color dark:bg-dark-3 dark:text-dark-7">Gravité {{ mb_strtolower($incident->severityLabel()) }}</span>
                            <span class="inline-flex rounded-full bg-gray-1 px-3 py-1 text-xs font-medium text-body-color dark:bg-dark-3 dark:text-dark-7">Priorité {{ mb_strtolower($incident->priorityLabel()) }}</span>
                            @if ($incident->is_duplicate)
                                <span class="inline-flex rounded-full bg-yellow-50 px-3 py-1 text-xs font-medium text-yellow-600">Doublon probable</span>
                            @endif
                        </div>

                        <h2 class="mt-5 text-lg font-bold text-dark dark:text-white">Description</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-body-color dark:text-dark-6">{{ $incident->description }}</p>

                        <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-stroke pt-5 text-sm dark:border-dark-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-body-color dark:text-dark-6">Adresse</dt>
                                <dd class="mt-1 font-medium text-dark dark:text-white">{{ $incident->address ?? 'Non précisée' }}</dd>
                            </div>
                            <div>
                                <dt class="text-body-color dark:text-dark-6">Infrastructure</dt>
                                <dd class="mt-1 font-medium text-dark dark:text-white">{{ $incident->infrastructure?->nom ?? 'Non précisée' }}</dd>
                            </div>
                            @if ($incident->latitude && $incident->longitude)
                                <div>
                                    <dt class="text-body-color dark:text-dark-6">Coordonnées</dt>
                                    <dd class="mt-1 font-medium text-dark dark:text-white">{{ $incident->latitude }}, {{ $incident->longitude }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt class="text-body-color dark:text-dark-6">Référence</dt>
                                <dd class="mt-1 font-mono text-xs font-medium text-dark dark:text-white">{{ $incident->reference }}</dd>
                            </div>
                        </dl>

                        @if ($incident->is_duplicate && $incident->duplicateOf)
                            <p class="mt-5 rounded-md bg-yellow-50 p-4 text-sm text-yellow-700">
                                Ce signalement présente des similarités avec le signalement {{ $incident->duplicateOf->reference }}. Les services techniques vérifieront s'il s'agit du même événement.
                            </p>
                        @endif

                        @if ($incident->resolution_note)
                            <div class="mt-5 rounded-md bg-green-50 p-4">
                                <p class="text-sm font-semibold text-secondary">Compte rendu de résolution</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-green-800">{{ $incident->resolution_note }}</p>
                                @if ($incident->resolved_at)
                                    <p class="mt-1 text-xs text-green-700">Clôturé le {{ $incident->resolved_at->format('d/m/Y H:i') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Avis et réclamation citoyenne --}}
                    <div id="avis" class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2 md:p-8">
                        <h2 class="text-lg font-bold text-dark dark:text-white">Votre avis sur le traitement</h2>

                        @if (! $feedback && $incident->status === 'traite')
                            <p class="mt-2 text-sm text-body-color dark:text-dark-6">
                                Votre signalement a été traité le {{ $incident->resolved_at?->format('d/m/Y') }}. Votre évaluation aide les services techniques à améliorer la qualité du réseau.
                            </p>

                            @if ($errors->has('feedback'))
                                <div class="mt-4 rounded-md border-l-4 border-red-500 bg-red-50 p-4">
                                    <p class="text-sm text-red-700">{{ $errors->first('feedback') }}</p>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('citizen.incidents.feedback.store', $incident) }}" class="mt-5 space-y-5">
                                @csrf

                                <div>
                                    <span class="block text-sm font-medium text-dark dark:text-white">Note de satisfaction <span class="text-red-500">*</span></span>
                                    <div class="mt-2 flex items-center gap-1">
                                        @for ($star = 1; $star <= 5; $star++)
                                            <label class="cursor-pointer" title="{{ $star }} étoile{{ $star > 1 ? 's' : '' }}">
                                                <input type="radio" name="rating" value="{{ $star }}" class="peer sr-only" required @checked((int) old('rating') === $star)>
                                                <span class="text-3xl leading-none text-gray-300 transition peer-checked:text-yellow-400">{{ '★' }}</span>
                                            </label>
                                        @endfor
                                        <span class="ml-2 text-xs text-body-color dark:text-dark-6">1 à 5 étoiles</span>
                                    </div>
                                    @error('rating')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="feedback-comment" class="mb-2 block text-sm font-medium text-dark dark:text-white">Commentaire (facultatif)</label>
                                    <textarea id="feedback-comment" name="comment" rows="3" maxlength="2000" class="{{ $inputClass }} @error('comment') border-red-500 @enderror" placeholder="Partagez votre expérience sur le traitement du signalement…">{{ old('comment') }}</textarea>
                                    @error('comment')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <span class="block text-sm font-medium text-dark dark:text-white">Le problème est-il réellement résolu ? <span class="text-red-500">*</span></span>
                                    <div class="mt-2 flex flex-wrap gap-4">
                                        <label class="flex items-center gap-2 text-sm text-body-color dark:text-dark-6">
                                            <input type="radio" name="resolution_confirmed" value="1" class="text-primary" required @checked((string) old('resolution_confirmed') === '1')>
                                            Oui, le problème est résolu
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-body-color dark:text-dark-6">
                                            <input type="radio" name="resolution_confirmed" value="0" class="text-primary" @checked((string) old('resolution_confirmed') === '0')>
                                            Non, le problème persiste
                                        </label>
                                    </div>
                                    @error('resolution_confirmed')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="feedback-complaint" class="mb-2 block text-sm font-medium text-dark dark:text-white">Déposer une réclamation</label>
                                    <textarea id="feedback-complaint" name="complaint_description" rows="4" maxlength="2000" class="{{ $inputClass }} @error('complaint_description') border-red-500 @enderror" placeholder="Décrivez la situation si le problème persiste (10 à 2000 caractères). Obligatoire si vous indiquez que le problème persiste.">{{ old('complaint_description') }}</textarea>
                                    <p class="mt-1 text-xs text-body-color dark:text-dark-6">Une seule évaluation est possible par signalement. La réclamation sera examinée par un gestionnaire.</p>
                                    @error('complaint_description')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                                </div>

                                <label class="flex items-start gap-2 text-sm text-body-color dark:text-dark-6">
                                    <input type="checkbox" name="reopen_requested" value="1" class="mt-0.5 rounded border-stroke text-primary" @checked(old('reopen_requested'))>
                                    <span>Je demande la réouverture de l'incident. <span class="text-xs">La réouverture effective reste soumise à la décision d'un gestionnaire.</span></span>
                                </label>
                                @error('reopen_requested')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror

                                <button type="submit" class="rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-dark">Envoyer mon évaluation</button>
                            </form>
                        @elseif ($feedback)
                            <div class="mt-4 flex flex-wrap items-center gap-3">
                                <span class="text-2xl leading-none text-yellow-400">{{ str_repeat('★', $feedback->rating) }}</span>
                                <span class="text-2xl leading-none text-gray-300">{{ str_repeat('☆', 5 - $feedback->rating) }}</span>
                                <span class="text-sm font-semibold text-dark dark:text-white">{{ $feedback->rating }}/5</span>
                                <span class="text-xs text-body-color dark:text-dark-6">Envoyée le {{ $feedback->created_at?->format('d/m/Y à H:i') }}</span>
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $feedback->resolution_confirmed ? 'bg-green-50 text-secondary' : 'bg-red-50 text-red-500' }}">
                                    {{ $feedback->resolution_confirmed ? 'Problème résolu' : 'Problème persistant' }}
                                </span>
                            </div>

                            @if ($feedback->comment)
                                <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-body-color dark:text-dark-6">{{ $feedback->comment }}</p>
                            @endif

                            @if ($feedback->hasComplaint())
                                <div class="mt-5 border-t border-stroke pt-5 dark:border-dark-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-dark dark:text-white">Votre réclamation</p>
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $complaintTone($feedback->complaint_status) }}">{{ $feedback->complaintStatusLabel() }}</span>
                                        @if ($feedback->reopen_requested)
                                            <span class="inline-flex rounded-full bg-yellow-50 px-2.5 py-0.5 text-xs font-medium text-yellow-600">Réouverture demandée</span>
                                        @endif
                                    </div>

                                    <p class="mt-3 whitespace-pre-line text-sm text-body-color dark:text-dark-6">{{ $feedback->complaint_description }}</p>

                                    @if ($feedback->complaint_status === 'en_attente')
                                        <p class="mt-3 rounded-md bg-yellow-50 p-3 text-xs text-yellow-700">Votre réclamation est en attente d'examen par un gestionnaire.</p>
                                    @elseif ($feedback->complaint_status === 'en_examen')
                                        <p class="mt-3 rounded-md bg-blue-50 p-3 text-xs text-primary">Votre réclamation est en cours d'examen.</p>
                                    @elseif ($feedback->complaint_status === 'acceptee')
                                        <p class="mt-3 rounded-md bg-green-50 p-3 text-xs text-green-800">Votre réclamation a été acceptée.</p>
                                    @elseif ($feedback->complaint_status === 'rejetee')
                                        <p class="mt-3 rounded-md bg-red-50 p-3 text-xs text-red-700">Votre réclamation a été refusée.</p>
                                    @elseif ($feedback->complaint_status === 'cloturee')
                                        <p class="mt-3 rounded-md bg-gray-1 p-3 text-xs text-body-color dark:bg-dark-3 dark:text-dark-6">Le dossier de réclamation est clôturé.</p>
                                    @endif

                                    @if ($feedback->manager_response)
                                        <div class="mt-4 rounded-md border border-green-100 bg-green-50 p-4">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-green-700">Réponse du gestionnaire</p>
                                            <p class="mt-1 whitespace-pre-line text-sm text-green-800">{{ $feedback->manager_response }}</p>
                                        </div>
                                    @endif

                                    @if ($feedback->decision_reason)
                                        <div class="mt-3 rounded-md bg-gray-1 p-4 dark:bg-dark-3">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-body-color dark:text-dark-6">
                                                Décision : {{ $feedback->complaintDecisionLabel() ?? 'rendue' }}
                                            </p>
                                            <p class="mt-1 whitespace-pre-line text-sm text-dark dark:text-white">{{ $feedback->decision_reason }}</p>
                                        </div>
                                    @endif

                                    @if ($feedback->reopened_at)
                                        <p class="mt-3 text-xs text-secondary">Incident réouvert le {{ $feedback->reopened_at->format('d/m/Y à H:i') }}.</p>
                                    @endif

                                    @if ($feedback->histories->isNotEmpty())
                                        <ol class="mt-5 space-y-3">
                                            @foreach ($feedback->histories as $history)
                                                <li class="border-l-2 border-primary pl-4">
                                                    <p class="text-xs font-medium text-dark dark:text-white">{{ $history->actionLabel() }}</p>
                                                    <p class="mt-0.5 text-xs text-body-color dark:text-dark-6">
                                                        {{ $history->created_at?->format('d/m/Y H:i') }}
                                                        @if ($history->user && $history->action !== 'depot')
                                                            · {{ $history->user->name }}
                                                        @endif
                                                    </p>
                                                    @if ($history->comment && $history->action !== 'depot')
                                                        <p class="mt-1 whitespace-pre-line text-xs text-body-color dark:text-dark-6">{{ $history->comment }}</p>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ol>
                                    @endif
                                </div>
                            @else
                                <p class="mt-4 border-t border-stroke pt-4 text-sm text-body-color dark:border-dark-3 dark:text-dark-6">
                                    Aucune réclamation n'est associée à cette évaluation.
                                </p>
                            @endif
                        @else
                            <p class="mt-2 text-sm text-body-color dark:text-dark-6">
                                L'évaluation sera disponible dès que ce signalement sera traité par les services techniques.
                            </p>
                        @endif
                    </div>

                    {{-- Analyse automatique --}}
                    @if ($incident->ai_summary)
                        <div class="rounded-md border border-dashed border-stroke bg-gray-1 p-5 dark:border-dark-3 dark:bg-dark-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-body-color dark:text-dark-6">Analyse automatique locale</p>
                            <p class="mt-2 text-sm text-dark dark:text-white">{{ $incident->ai_summary }}</p>
                            @if ($incident->ai_confidence !== null)
                                <p class="mt-2 text-xs text-body-color dark:text-dark-6">Confiance de la classification : {{ $incident->ai_confidence }} %. Cette analyse est indicative et n'utilise aucun fournisseur d'IA externe.</p>
                            @endif
                        </div>
                    @endif

                    {{-- Photos --}}
                    <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                        <h2 class="text-lg font-bold text-dark dark:text-white">Photos</h2>

                        @if ($incident->photos->isNotEmpty())
                            <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                @foreach ($incident->photos as $photo)
                                    <li class="rounded-md border border-stroke p-3 dark:border-dark-3">
                                        <p class="truncate text-xs text-body-color dark:text-dark-6" title="{{ $photo->nom_original }}">{{ $photo->nom_original ?? basename($photo->chemin_fichier) }}</p>
                                        <a href="{{ route('citizen.incidents.photos.download', [$incident, $photo->id]) }}" class="mt-2 inline-block text-xs font-medium text-primary hover:underline">Télécharger</a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-body-color dark:text-dark-6">Aucune photo pour ce signalement.</p>
                        @endif

                        <form method="POST" action="{{ route('citizen.incidents.photos.store', $incident) }}" enctype="multipart/form-data" class="mt-5 border-t border-stroke pt-4 dark:border-dark-3">
                            @csrf
                            <label for="photo" class="mb-2 block text-sm font-medium text-dark dark:text-white">Ajouter une photo</label>
                            <input type="file" id="photo" name="photo"
                                   class="block w-full text-sm text-body-color file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-blue-dark">
                            @error('photo')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                            <button type="submit" class="mt-3 rounded-md border border-primary px-4 py-2 text-sm font-medium text-primary hover:bg-primary hover:text-white">Téléverser</button>
                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">Formats acceptés : JPG, PNG, WEBP — 4 Mo maximum.</p>
                        </form>
                    </div>

                    {{-- Commentaires --}}
                    <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                        <h2 class="text-lg font-bold text-dark dark:text-white">Échanges</h2>

                        @forelse ($incident->comments as $comment)
                            <div class="mt-4 rounded-md bg-gray-1 p-4 dark:bg-dark-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-dark dark:text-white">{{ $comment->user?->name ?? 'Service technique' }}</p>
                                    <p class="text-xs text-body-color dark:text-dark-6">{{ $comment->created_at?->format('d/m/Y H:i') }}</p>
                                </div>
                                <p class="mt-2 whitespace-pre-line text-sm text-body-color dark:text-dark-6">{{ $comment->body }}</p>
                            </div>
                        @empty
                            <p class="mt-2 text-sm text-body-color dark:text-dark-6">Aucun échange pour le moment.</p>
                        @endforelse

                        <form method="POST" action="{{ route('citizen.incidents.comments.store', $incident) }}" class="mt-5 border-t border-stroke pt-4 dark:border-dark-3">
                            @csrf
                            <label for="body" class="mb-2 block text-sm font-medium text-dark dark:text-white">Ajouter un commentaire</label>
                            <textarea id="body" name="body" rows="3" class="{{ $inputClass }} @error('body') border-red-500 @enderror" placeholder="Précision, photo complémentaire, disponibilité…">{{ old('body') }}</textarea>
                            @error('body')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                            <button type="submit" class="mt-3 rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-dark">Envoyer</button>
                        </form>
                    </div>
                </div>

                {{-- Colonne latérale --}}
                <div class="space-y-6">
                    <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                        <h2 class="text-lg font-bold text-dark dark:text-white">Historique</h2>

                        <ol class="mt-4 space-y-4">
                            @forelse ($incident->statusHistories as $history)
                                <li class="border-l-2 border-primary pl-4">
                                    <p class="text-sm font-medium text-dark dark:text-white">
                                        @if ($history->ancienStatutLabel())
                                            {{ $history->ancienStatutLabel() }} → {{ $history->nouveauStatutLabel() }}
                                        @else
                                            {{ $history->nouveauStatutLabel() }} <span class="text-xs font-normal text-body-color dark:text-dark-6">(création)</span>
                                        @endif
                                    </p>
                                    <p class="mt-1 text-xs text-body-color dark:text-dark-6">
                                        {{ $history->created_at?->format('d/m/Y H:i') }}
                                        @if ($history->user)
                                            · {{ $history->user->name }}
                                        @endif
                                    </p>
                                    @if ($history->comment)
                                        <p class="mt-1 whitespace-pre-line text-xs text-body-color dark:text-dark-6">{{ $history->comment }}</p>
                                    @endif
                                    @if ($history->resolution_note)
                                        <p class="mt-1 whitespace-pre-line text-xs text-body-color dark:text-dark-6">
                                            <span class="font-semibold">Compte rendu :</span> {{ $history->resolution_note }}
                                        </p>
                                    @endif
                                    @if ($history->rejection_reason)
                                        <p class="mt-1 whitespace-pre-line text-xs text-body-color dark:text-dark-6">
                                            <span class="font-semibold">Motif de rejet :</span> {{ $history->rejection_reason }}
                                        </p>
                                    @endif
                                </li>
                            @empty
                                <li class="text-sm text-body-color dark:text-dark-6">Aucun changement de statut enregistré.</li>
                            @endforelse
                        </ol>
                    </div>

                    <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                        <h2 class="text-lg font-bold text-dark dark:text-white">Besoin d'aide ?</h2>
                        <p class="mt-2 text-sm text-body-color dark:text-dark-6">
                            Conservez la référence <span class="font-mono">{{ $incident->reference }}</span> pour suivre ce signalement à tout moment.
                        </p>
                        <a href="{{ route('citizen.subscriptions.index') }}" class="mt-4 inline-block text-sm font-medium text-primary hover:underline">Gérer mes alertes par zone</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
