@php
    $scoreTone = fn (int $score) => match (true) {
        $score >= 85 => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
        $score >= 70 => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
        default => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
    };

    $similarityTone = fn (string $status) => match ($status) {
        \App\Models\Incident\IncidentSimilarity::STATUS_DUPLICATE => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
        \App\Models\Incident\IncidentSimilarity::STATUS_LINKED => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
        \App\Models\Incident\IncidentSimilarity::STATUS_REJECTED => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
        default => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
    };

    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90';

    $aiLabel = fn (string $method) => match ($method) {
        \App\Models\Incident\IncidentSimilarity::METHOD_LOCAL_AI => 'IA : même problème probable',
        \App\Models\Incident\IncidentSimilarity::METHOD_LOCAL_AI_NEGATIVE => 'IA : problème probablement différent',
        \App\Models\Incident\IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE => 'IA indisponible',
        default => null,
    };

    $aiTone = fn (string $method) => match ($method) {
        \App\Models\Incident\IncidentSimilarity::METHOD_LOCAL_AI => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
        \App\Models\Incident\IncidentSimilarity::METHOD_LOCAL_AI_NEGATIVE => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
        default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
    };
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Incidents similaires</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Suggestions calculées localement. L'avis IA, lorsqu'il est disponible, est purement indicatif :
                il ne modifie ni le score ni le statut. Aucune fusion, aucun changement de statut et aucun marquage
                automatique : chaque décision est prise par un gestionnaire.
            </p>
        </div>
        <form novalidate method="POST" action="{{ route('back.incidents.similarities.detect', $incident) }}">
            @csrf
            <button type="submit" class="rounded-lg border border-brand-300 bg-brand-50 px-4 py-2.5 text-sm font-medium text-brand-600 transition hover:bg-brand-100 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/20">
                Rechercher les incidents similaires
            </button>
        </form>
    </div>

    @error('similarities')
        <p class="mt-3 text-sm text-error-600">{{ $message }}</p>
    @enderror

    @if ($recurring['is_recurring'])
        <div class="mt-4 rounded-xl border border-error-100 bg-error-50/60 p-4 dark:border-error-500/20 dark:bg-error-500/10">
            <p class="text-xs font-semibold uppercase text-error-600 dark:text-error-400">Problème récurrent</p>
            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                {{ $recurring['count'] }} incidents similaires recensés dans la même zone sur les
                {{ $recurring['window_days'] }} derniers jours (seuil de signalement : {{ $recurring['threshold'] }}).
            </p>
        </div>
    @endif

    @error('decision')
        <p class="mt-3 text-sm text-error-600">{{ $message }}</p>
    @enderror

    @forelse ($incident->similaritySuggestions as $suggestion)
        @php
            $similar = $suggestion->similarIncident;
            $aiLabelText = $aiLabel($suggestion->method);
        @endphp

        @if ($similar)
            <div class="mt-4 rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('back.incidents.show', $similar) }}" class="text-sm font-semibold text-brand-500 hover:underline dark:text-brand-400">
                            {{ $similar->reference }}
                        </a>
                        <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">{{ $similar->title }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $similar->zone?->nom ?? 'Zone non précisée' }}
                            · {{ $similar->statusLabel() }}
                            · Signalé le {{ $similar->reported_at?->format('d/m/Y') ?? '—' }}
                            @if ($aiLabelText)
                                · <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $aiTone($suggestion->method) }}">{{ $aiLabelText }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $scoreTone($suggestion->score) }}" title="Score de similarité local (heuristique, non calibré)">
                            {{ $suggestion->score }}/100
                        </span>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $similarityTone($suggestion->status) }}">
                            {{ $suggestion->statusLabel() }}
                        </span>
                    </div>
                </div>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ \Illuminate\Support\Str::limit($similar->description, 180) }}
                </p>

                @if ($suggestion->ai_justification)
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        <span class="font-semibold">Avis IA (indicatif) :</span> {{ $suggestion->ai_justification }}
                    </p>
                @endif

                @if ($suggestion->isPending())
                    <form novalidate method="POST" action="{{ route('back.incidents.similarities.decide', [$incident, $suggestion]) }}" class="mt-3 space-y-3">
                        @csrf
                        @method('PATCH')

                        <input
                            type="text"
                            name="note"
                            maxlength="2000"
                            class="{{ $inputClass }}"
                            placeholder="Note facultative (conservée avec la décision)"
                        >

                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="decision" value="doublon" class="rounded-lg bg-error-500 px-3 py-2 text-xs font-medium text-white transition hover:bg-error-600">
                                Marquer comme doublon
                            </button>
                            <button type="submit" name="decision" value="lie" class="rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white transition hover:bg-brand-600">
                                Lier comme incident connexe
                            </button>
                            <button type="submit" name="decision" value="ignorer" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                Ignorer
                            </button>
                        </div>
                    </form>
                @else
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Décision prise par {{ $suggestion->reviewer?->name ?? 'utilisateur supprimé' }}
                        le {{ $suggestion->reviewed_at?->format('d/m/Y H:i') ?? '—' }}
                        @if ($suggestion->decision_note)
                            · <span class="italic">{{ $suggestion->decision_note }}</span>
                        @endif
                    </p>
                @endif
            </div>
        @endif
    @empty
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            Aucune suggestion pour le moment. Utilisez le bouton ci-dessus pour lancer une recherche.
        </p>
    @endforelse
</div>
