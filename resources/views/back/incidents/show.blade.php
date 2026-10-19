@extends('back.layouts.app')

@section('title', 'AquaSecure — Incident '.$incident->reference)

@section('content')
    @php
        $statusTone = fn (string $status) => match ($status) {
            'nouveau' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            'en_cours' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
            'en_attente' => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
            'traite' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'rejete' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
        };
        $severityTone = fn (string $severity) => match ($severity) {
            'critique' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            'haute' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            'faible' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
        };
        $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90';
    @endphp

    <x-back.page-header :title="'Incident '.$incident->reference" :desc="$incident->title">
        <x-slot:action>
            <a href="{{ route('back.incidents.edit', $incident) }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Modifier</a>
            <x-back.confirm-delete :action="route('back.incidents.destroy', $incident)" message="Cet incident et tout son historique seront supprimés." />
        </x-slot:action>
    </x-back.page-header>

    <div class="grid grid-cols-12 gap-4 md:gap-6">
        <div class="col-span-12 space-y-6 xl:col-span-8">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusTone($incident->status) }}">{{ $incident->statusLabel() }}</span>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $severityTone($incident->severity) }}">Gravité {{ $incident->severityLabel() }}</span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">Priorité {{ $incident->priorityLabel() }}</span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $incident->typeLabel() }}</span>
                    @if ($incident->is_duplicate)
                        <span class="rounded-full bg-warning-50 px-2.5 py-0.5 text-xs font-medium text-warning-600 dark:bg-warning-500/15 dark:text-warning-400">Doublon possible</span>
                    @endif
                </div>

                <p class="mt-4 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $incident->description }}</p>

                <dl class="mt-6 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500 dark:text-gray-400">Zone</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $incident->zone?->nom ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Infrastructure</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $incident->infrastructure?->nom ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Adresse</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $incident->address ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Signalé le</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $incident->reported_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Citoyen</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $incident->user?->name ?? 'Créé en interne' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Assigné à</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $incident->assignee?->name ?? 'Non assigné' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Résolu le</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $incident->resolved_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Coordonnées</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $incident->latitude && $incident->longitude ? $incident->latitude.', '.$incident->longitude : '—' }}</dd></div>
                </dl>

                @if ($incident->resolution_note)
                    <div class="mt-6 rounded-xl border border-success-100 bg-success-50/60 p-4 dark:border-success-500/20 dark:bg-success-500/10">
                        <p class="text-xs font-semibold uppercase text-success-600 dark:text-success-400">Compte rendu de résolution</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $incident->resolution_note }}</p>
                    </div>
                @endif

                @if ($incident->status === 'rejete' && $incident->rejection_reason)
                    <div class="mt-6 rounded-xl border border-error-100 bg-error-50/60 p-4 dark:border-error-500/20 dark:bg-error-500/10">
                        <p class="text-xs font-semibold uppercase text-error-600 dark:text-error-400">Motif de rejet</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $incident->rejection_reason }}</p>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-dashed border-brand-200 bg-brand-50/40 p-5 dark:border-brand-500/30 dark:bg-brand-500/5 md:p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Analyse heuristique locale</h2>
                    @if ($incident->ai_confidence !== null)
                        <span class="text-xs text-gray-500 dark:text-gray-400">Confiance {{ $incident->ai_confidence }} %</span>
                    @endif
                </div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ $incident->ai_summary ?? 'Aucune analyse enregistrée pour cet incident.' }}
                </p>
                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                    Classification déterministe par mots-clés réalisée localement — aucun fournisseur d'IA externe n'est utilisé.
                </p>
                @if ($incident->duplicateOf)
                    <p class="mt-3 text-sm text-warning-700 dark:text-warning-400">
                        Doublon probable de
                        <a href="{{ route('back.incidents.show', $incident->duplicateOf) }}" class="font-medium underline">{{ $incident->duplicateOf->reference }}</a>.
                    </p>
                @endif
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:px-6">
                    <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Commentaires</h2>
                </div>
                <div class="space-y-4 p-5 md:p-6">
                    @forelse ($incident->comments as $comment)
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $comment->user?->name ?? 'Utilisateur supprimé' }}</span>
                                <span>{{ $comment->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $comment->body }}</p>
                            @if ($comment->is_internal)
                                <span class="mt-2 inline-flex rounded-full bg-warning-50 px-2 py-0.5 text-[10px] font-medium uppercase text-warning-600 dark:bg-warning-500/15 dark:text-warning-400">Interne</span>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Aucun commentaire.</p>
                    @endforelse

                    <form method="POST" action="{{ route('back.incidents.comments.store', $incident) }}" class="border-t border-gray-100 pt-4 dark:border-gray-800">
                        @csrf
                        <label for="body" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Ajouter un commentaire</label>
                        <textarea id="body" name="body" rows="3" class="{{ $inputClass }} @error('body') border-error-400 @enderror">{{ old('body') }}</textarea>
                        @error('body')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
                        <label class="mt-3 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input type="checkbox" name="is_internal" value="1" class="rounded border-gray-300 text-brand-500">
                            Commentaire interne (non visible par le citoyen)
                        </label>
                        @error('is_internal')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
                        <button type="submit" class="mt-3 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Publier</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-span-12 space-y-6 xl:col-span-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Changer le statut</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Seules les transitions autorisées par le workflow sont proposées.</p>

                @php
                    $isReopening = in_array($incident->status, ['traite', 'rejete'], true);
                @endphp

                @if ($allowedTransitions === [])
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Aucune transition disponible.</p>
                @else
                    <form method="POST" action="{{ route('back.incidents.status', $incident) }}" class="mt-3 space-y-3">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="incident-status-target" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nouveau statut</label>
                            <select id="incident-status-target" name="status" class="{{ $inputClass }} @error('status') border-error-400 @enderror">
                                @foreach ($allowedTransitions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('status') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
                        </div>

                        <div data-status-field="comment">
                            <label for="incident-comment" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Commentaire{{ $isReopening ? ' (motif de réouverture)' : '' }}
                            </label>
                            <textarea id="incident-comment" name="comment" rows="2" class="{{ $inputClass }} @error('comment') border-error-400 @enderror" placeholder="{{ $isReopening ? 'Justifiez la réouverture (10 caractères minimum)' : 'Commentaire de transition (facultatif)' }}">{{ old('comment') }}</textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $isReopening
                                    ? 'Obligatoire : au moins 10 caractères pour justifier la réouverture.'
                                    : 'Facultatif. Conservé dans l\'historique de l\'incident.' }}
                            </p>
                            @error('comment')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
                        </div>

                        <div data-status-field="resolution_note">
                            <label for="incident-resolution-note" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Compte rendu de résolution</label>
                            <textarea id="incident-resolution-note" name="resolution_note" rows="3" class="{{ $inputClass }} @error('resolution_note') border-error-400 @enderror" placeholder="Obligatoire pour clôturer l'incident">{{ old('resolution_note') }}</textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Obligatoire si le statut cible est « Traité » — 10 à 2000 caractères.</p>
                            @error('resolution_note')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
                        </div>

                        <div data-status-field="rejection_reason">
                            <label for="incident-rejection-reason" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Motif de rejet</label>
                            <textarea id="incident-rejection-reason" name="rejection_reason" rows="3" class="{{ $inputClass }} @error('rejection_reason') border-error-400 @enderror" placeholder="Obligatoire pour rejeter l'incident">{{ old('rejection_reason') }}</textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Obligatoire si le statut cible est « Rejeté » — 10 à 2000 caractères.</p>
                            @error('rejection_reason')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Mettre à jour le statut</button>
                    </form>
                @endif
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Photos</h2>
                @if ($incident->photos->isNotEmpty())
                    <ul class="mt-3 space-y-2">
                        @foreach ($incident->photos as $photo)
                            <li class="flex items-center justify-between gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm dark:border-gray-800">
                                <span class="truncate text-gray-600 dark:text-gray-300">{{ $photo->nom_original ?? basename($photo->chemin_fichier) }}</span>
                                <a href="{{ route('back.incidents.photos.download', [$incident, $photo->id]) }}" class="shrink-0 text-xs font-medium text-brand-500 hover:underline">Télécharger</a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Aucune photo.</p>
                @endif
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Historique des statuts</h2>
                <ol class="mt-3 space-y-4">
                    @forelse ($incident->statusHistories as $history)
                        <li class="border-l-2 border-brand-200 pl-4 dark:border-brand-500/30">
                            <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                @if ($history->ancienStatutLabel())
                                    {{ $history->ancienStatutLabel() }} → {{ $history->nouveauStatutLabel() }}
                                @else
                                    {{ $history->nouveauStatutLabel() }} <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(création)</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $history->created_at?->format('d/m/Y H:i') }} · {{ $history->user?->name ?? 'Système' }}
                            </p>
                            @if ($history->comment)
                                <p class="mt-1 whitespace-pre-line text-xs text-gray-500 dark:text-gray-400">{{ $history->comment }}</p>
                            @endif
                            @if ($history->resolution_note)
                                <p class="mt-1 whitespace-pre-line text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-semibold">Compte rendu :</span> {{ $history->resolution_note }}
                                </p>
                            @endif
                            @if ($history->rejection_reason)
                                <p class="mt-1 whitespace-pre-line text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-semibold">Motif de rejet :</span> {{ $history->rejection_reason }}
                                </p>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-gray-500 dark:text-gray-400">Aucun historique.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const select = document.getElementById('incident-status-target');
            if (!select) {
                return;
            }

            const fields = document.querySelectorAll('[data-status-field]');

            const sync = () => {
                const target = select.value;

                fields.forEach((field) => {
                    const key = field.dataset.statusField;
                    const visible = key === 'comment'
                        || (key === 'resolution_note' && target === 'traite')
                        || (key === 'rejection_reason' && target === 'rejete');

                    field.classList.toggle('hidden', !visible);
                });
            };

            sync();
            select.addEventListener('change', sync);
        })();
    </script>
@endpush
