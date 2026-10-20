@extends('back.layouts.app')

@section('title', 'AquaSecure — Réclamation '.$feedback->incident?->reference)

@section('content')
    @php
        $incident = $feedback->incident;
        $statusTone = fn (string $status) => match ($status) {
            'en_attente' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            'en_examen' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
            'acceptee' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'rejetee' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            'cloturee' => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
        };
        $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90';
    @endphp

    <x-back.page-header
        :title="'Réclamation — '.($incident?->reference ?? 'incident supprimé')"
        :desc="$incident?->title ?? 'L\'incident associé a été supprimé.'"
    >
        <x-slot:action>
            <a href="{{ route('back.complaints.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                ← Toutes les réclamations
            </a>
            @if ($incident)
                <a href="{{ route('back.incidents.show', $incident) }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                    Voir l'incident
                </a>
            @endif
        </x-slot:action>
    </x-back.page-header>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-error-100 bg-error-50/70 p-4 dark:border-error-500/20 dark:bg-error-500/10">
            <p class="text-sm font-medium text-error-600 dark:text-error-400">L'action n'a pas pu être effectuée.</p>
            <ul class="mt-2 list-inside list-disc text-sm text-error-600 dark:text-error-400">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-12 gap-4 md:gap-6">
        <div class="col-span-12 space-y-6 xl:col-span-8">
            {{-- Évaluation --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusTone($feedback->complaint_status) }}">
                        {{ $feedback->complaintStatusLabel() }}
                    </span>
                    @if ($feedback->reopen_requested)
                        <span class="inline-flex rounded-full bg-warning-50 px-2.5 py-0.5 text-xs font-medium text-warning-600 dark:bg-warning-500/15 dark:text-warning-400">Réouverture demandée</span>
                    @endif
                    @if ($feedback->reopened_at)
                        <span class="inline-flex rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-600 dark:bg-success-500/15 dark:text-success-400">Incident réouvert</span>
                    @endif
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <span class="text-2xl leading-none text-warning-400">{{ str_repeat('★', $feedback->rating) }}</span>
                    <span class="text-2xl leading-none text-gray-300">{{ str_repeat('☆', 5 - $feedback->rating) }}</span>
                    <span class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $feedback->rating }}/5</span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $feedback->resolution_confirmed ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400' : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400' }}">
                        {{ $feedback->resolution_confirmed ? 'Résolution confirmée' : 'Résolution non confirmée' }}
                    </span>
                </div>

                @if ($feedback->comment)
                    <p class="mt-4 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $feedback->comment }}</p>
                @endif

                <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-gray-100 pt-5 text-sm dark:border-gray-800 sm:grid-cols-2">
                    <div><dt class="text-gray-500 dark:text-gray-400">Citoyen</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $feedback->user?->name ?? 'Citoyen supprimé' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Zone</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $incident?->zone?->nom ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Incident</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $incident?->statusLabel() ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Déposée le</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $feedback->created_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Examinée par</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $feedback->reviewer?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Le</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $feedback->reviewed_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                </dl>
            </div>

            {{-- Réclamation --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Motif de la réclamation</h2>
                <p class="mt-3 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $feedback->complaint_description }}</p>

                @if ($feedback->manager_response)
                    <div class="mt-5 rounded-xl border border-brand-100 bg-brand-50/50 p-4 dark:border-brand-500/20 dark:bg-brand-500/5">
                        <p class="text-xs font-semibold uppercase text-brand-600 dark:text-brand-400">Dernière réponse du gestionnaire</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $feedback->manager_response }}</p>
                    </div>
                @endif

                @if ($feedback->decision_reason)
                    <div class="mt-4 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                            Justification de la décision{{ $feedback->complaintDecisionLabel() ? ' — '.$feedback->complaintDecisionLabel() : '' }}
                        </p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $feedback->decision_reason }}</p>
                    </div>
                @endif

                @if ($feedback->reopen_decision_reason)
                    <div class="mt-4 rounded-xl border border-success-100 bg-success-50/50 p-4 dark:border-success-500/20 dark:bg-success-500/10">
                        <p class="text-xs font-semibold uppercase text-success-600 dark:text-success-400">Motif de réouverture retenu</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $feedback->reopen_decision_reason }}</p>
                    </div>
                @endif
            </div>

            {{-- Historique --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Historique de la réclamation</h2>
                <ol class="mt-4 space-y-4">
                    @forelse ($feedback->histories as $history)
                        <li class="border-l-2 border-brand-200 pl-4 dark:border-brand-500/30">
                            <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $history->actionLabel() }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $history->created_at?->format('d/m/Y H:i') }} · {{ $history->user?->name ?? 'Système' }}
                                @if ($history->ancienStatutLabel() || $history->nouveauStatutLabel())
                                    <span class="ml-2">
                                        {{ $history->ancienStatutLabel() ?? '—' }} → {{ $history->nouveauStatutLabel() ?? '—' }}
                                    </span>
                                @endif
                            </p>
                            @if ($history->comment)
                                <p class="mt-1 whitespace-pre-line text-xs text-gray-500 dark:text-gray-400">{{ $history->comment }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-gray-500 dark:text-gray-400">Aucune action enregistrée.</li>
                    @endforelse
                </ol>
            </div>
        </div>

        {{-- Actions --}}
        <div class="col-span-12 space-y-6 xl:col-span-4">
            @if ($feedback->complaint_status === 'en_attente')
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                    <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Prise en charge</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Marquez la réclamation comme étant en cours d'examen.</p>
                    <form novalidate method="POST" action="{{ route('back.complaints.review', $feedback) }}" class="mt-4">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Prendre en examen</button>
                    </form>
                </div>
            @endif

            @if (isset($allowedTransitions['acceptee']) || isset($allowedTransitions['rejetee']))
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                    <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Décision</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Une justification est obligatoire pour accepter ou refuser.</p>
                    <form novalidate method="POST" action="{{ route('back.complaints.decision', $feedback) }}" class="mt-4 space-y-3">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="complaint-decision" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Décision</label>
                            <select id="complaint-decision" name="decision" class="{{ $inputClass }} @error('decision') border-error-400 @enderror">
                                @foreach ($allowedTransitions as $key => $label)
                                    @if (in_array($key, ['acceptee', 'rejetee'], true))
                                        <option value="{{ $key }}" @selected(old('decision') === $key)>{{ $label }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('decision')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="complaint-justification" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Justification</label>
                            <textarea id="complaint-justification" name="justification" rows="4" class="{{ $inputClass }} @error('justification') border-error-400 @enderror" placeholder="Justifiez la décision (10 à 2000 caractères)">{{ old('justification') }}</textarea>
                            @error('justification')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Enregistrer la décision</button>
                    </form>
                </div>
            @endif

            @if ($feedback->complaint_status !== 'cloturee')
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                    <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Répondre au citoyen</h2>
                    <form novalidate method="POST" action="{{ route('back.complaints.response', $feedback) }}" class="mt-4 space-y-3">
                        @csrf
                        <textarea name="response" rows="4" class="{{ $inputClass }} @error('response') border-error-400 @enderror" placeholder="Votre réponse sera visible par le citoyen">{{ old('response', $feedback->manager_response) }}</textarea>
                        @error('response')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
                        <button type="submit" class="w-full rounded-lg border border-brand-500 px-4 py-2.5 text-sm font-medium text-brand-500 hover:bg-brand-50 dark:hover:bg-brand-500/10">Publier la réponse</button>
                    </form>
                </div>
            @endif

            @if ($feedback->complaint_status === 'acceptee' && $feedback->reopen_requested && $feedback->reopened_at === null)
                <div class="rounded-2xl border border-warning-100 bg-warning-50/40 p-5 dark:border-warning-500/20 dark:bg-warning-500/5 md:p-6">
                    <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Réouverture de l'incident</h2>
                    @if ($incident && $incident->status === 'traite')
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                            La réouverture passe par le workflow des incidents et exige un motif. L'incident repassera au statut « En cours ».
                        </p>
                        <form novalidate method="POST" action="{{ route('back.complaints.reopen', $feedback) }}" class="mt-4 space-y-3">
                            @csrf
                            @method('PATCH')
                            <textarea name="justification" rows="3" class="{{ $inputClass }} @error('justification') border-error-400 @enderror" placeholder="Motif de réouverture (10 à 2000 caractères)">{{ old('justification', $feedback->decision_reason) }}</textarea>
                            @error('justification')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
                            @error('reopen')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
                            <button type="submit" class="w-full rounded-lg bg-warning-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-warning-600">Réouvrir l'incident</button>
                        </form>
                    @else
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            La réouverture n'est pas disponible : l'incident n'est plus au statut « Traité ».
                        </p>
                    @endif
                </div>
            @endif

            @if (isset($allowedTransitions['cloturee']))
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                    <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Clôturer le dossier</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">La clôture est définitive pour cette réclamation.</p>
                    <form novalidate method="POST" action="{{ route('back.complaints.close', $feedback) }}" class="mt-4 space-y-3">
                        @csrf
                        @method('PATCH')
                        <textarea name="justification" rows="2" class="{{ $inputClass }} @error('justification') border-error-400 @enderror" placeholder="Commentaire de clôture (facultatif)">{{ old('justification') }}</textarea>
                        @error('justification')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
                        <button type="submit" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700">Clôturer la réclamation</button>
                    </form>
                </div>
            @endif

            @if ($feedback->complaint_status === 'cloturee')
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                    <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Dossier clôturé</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Aucune nouvelle action n'est disponible. L'historique reste consultable.
                    </p>
                </div>
            @endif
        </div>
    </div>
@endsection
