@extends('back.layouts.app')

@section('title', 'AquaSecure — Prélèvements qualité')

@section('content')
    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $tone = fn (string $statut) => match ($statut) {
            'conforme' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'non_conforme' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
        };
        $alertTone = fn (string $niveau) => match ($niveau) {
            'critique' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            'avertissement' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            default => 'bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-400',
        };
        $hasFilters = $search !== '' || $currentStatut !== '' || $currentZone !== null || $currentInfrastructure !== null || $from !== null || $to !== null;
    @endphp

    <x-back.page-header title="Prélèvements qualité" desc="Analyses de l'eau distribuée, conformité et alertes générées.">
        <x-slot:action>
            <a href="{{ route('back.quality-planifications.calendar') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Calendrier</a>
            <a href="{{ route('back.quality-planifications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Planifications</a>
            <a href="{{ route('back.quality-samples.create') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Nouveau prélèvement</a>
        </x-slot:action>
    </x-back.page-header>

    {{-- Rappels de planification --}}
    <div class="mb-8">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Rappels de planification</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Prochains contrôles prévus et retards sur les zones de votre périmètre.</p>
            </div>
            <a href="{{ route('back.quality-planifications.index') }}" class="text-sm font-medium text-brand-500 hover:underline dark:text-brand-400">Voir toutes les planifications</a>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-back.stat-card label="Contrôles à venir" :value="$planningReminders['a_venir']" />
            <x-back.stat-card label="Contrôles en retard" :value="$planningReminders['en_retard']" />
            <x-back.stat-card label="Contrôles effectués" :value="$planningReminders['effectues']" />
        </div>

        <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Prochains contrôles</h3>

            @if ($planningReminders['prochains']->isEmpty())
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Aucun contrôle à venir.</p>
            @else
                <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($planningReminders['prochains'] as $prochain)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                            <span class="text-gray-700 dark:text-gray-300">
                                {{ $prochain->date_prevue->format('d/m/Y') }} — {{ $prochain->zone?->nom }}
                                <span class="text-gray-400">({{ $prochain->frequenceLabel() }})</span>
                            </span>
                            <a href="{{ route('back.quality-samples.create', ['planification' => $prochain->id]) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Enregistrer le prélèvement</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Rapport PDF par zone --}}
    <div class="mb-8 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Rapport PDF par zone</h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">Génère un rapport contenant uniquement les prélèvements, mesures, seuils, statuts et alertes de la zone sélectionnée.</p>
            </div>

            <form method="GET" action="{{ route('back.quality-samples.index') }}" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="pdf" value="1">

                <div>
                    <label for="report-zone" class="mb-1 block text-sm text-gray-500 dark:text-gray-400">Zone</label>
                    <select id="report-zone" name="perimetre" required class="{{ $inputClass }}">
                        <option value="">Sélectionnez une zone…</option>
                        @foreach ($zones as $zoneOption)
                            <option value="{{ $zoneOption->id }}" @selected($perimetre?->id === $zoneOption->id)>{{ $zoneOption->nom }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" @disabled($zones->isEmpty()) class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50">Générer un rapport PDF</button>
            </form>
        </div>
    </div>

    {{-- Tableau de bord --}}
    <div class="mb-8">
        <h2 class="mb-4 text-base font-medium text-gray-800 dark:text-white/90">Tableau de bord qualité</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-back.stat-card data-testid="quality-stat-total" label="Total prélèvements" :value="$stats['total']" />
            <x-back.stat-card data-testid="quality-stat-conformes" label="Conformes" :value="$stats['conformes']" />
            <x-back.stat-card data-testid="quality-stat-non-conformes" label="Non conformes" :value="$stats['non_conformes']" />
            <x-back.stat-card data-testid="quality-stat-alertes" label="Alertes actives" :value="$stats['alertes_actives']" />
            <x-back.stat-card data-testid="quality-stat-dernier" label="Dernier prélèvement" :value="$stats['dernier_prelevement']?->format('d/m/Y H:i') ?? '—'" />
        </div>
    </div>

    {{-- Évolution de la qualité par périmètre --}}
    <div class="mb-8">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Évolution par périmètre</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Chaque périmètre est analysé séparément, uniquement à partir de ses propres prélèvements.</p>
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-2">
                @if ($search !== '')
                    <input type="hidden" name="search" value="{{ $search }}">
                @endif
                @if ($currentStatut !== '')
                    <input type="hidden" name="statut" value="{{ $currentStatut }}">
                @endif
                @if ($currentZone)
                    <input type="hidden" name="zone_id" value="{{ $currentZone }}">
                @endif

                <label for="perimetre" class="text-sm text-gray-500 dark:text-gray-400">Périmètre</label>
                <select id="perimetre" name="perimetre" onchange="this.form.submit()" class="{{ $inputClass }}">
                    <option value="">Sélectionnez un périmètre…</option>
                    @foreach ($zones as $zoneOption)
                        <option value="{{ $zoneOption->id }}" @selected($perimetre?->id === $zoneOption->id)>{{ $zoneOption->nom }}</option>
                    @endforeach
                </select>
                <noscript>
                    <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Afficher</button>
                </noscript>
            </form>
        </div>

        @if (! $perimetre)
            <x-back.empty-state title="Aucun périmètre sélectionné" message="Choisissez un périmètre dans la liste pour afficher uniquement son évolution et son historique." />
        @else
            @php
                $tendanceMeta = match ($perimetreSummary['tendance']) {
                    'amelioration' => ['label' => 'En amélioration', 'class' => 'text-success-600 dark:text-success-400'],
                    'degradation' => ['label' => 'En dégradation', 'class' => 'text-error-600 dark:text-error-400'],
                    default => ['label' => 'Stable', 'class' => 'text-gray-800 dark:text-white/90'],
                };
            @endphp

            <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-back.stat-card label="Score moyen (6 derniers)" :value="$perimetreSummary['score_moyen'] !== null ? $perimetreSummary['score_moyen'].' %' : '—'" />
                <x-back.stat-card label="Conformité 90 jours" :value="$perimetreSummary['conformite_90j'] !== null ? $perimetreSummary['conformite_90j'].' %' : '—'" />
                <x-back.stat-card label="Alertes actives" :value="$perimetreSummary['alertes_actives']" />
                <x-back.stat-card label="Dernier prélèvement" :value="$perimetreSummary['latest']?->date_prelevement?->format('d/m/Y H:i') ?? '—'" />
            </div>

            <div class="mb-5 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">Tendance de conformité — {{ $perimetre->nom }}</p>
                @if ($trendDisponible)
                    <p class="mt-1 text-lg font-semibold {{ $tendanceMeta['class'] }}">{{ $tendanceMeta['label'] }}</p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Calculée à partir des scores réellement enregistrés (au moins 3 prélèvements analysés).</p>
                @else
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tendance non déterminable : les données analysées de ce périmètre sont insuffisantes.</p>
                @endif
            </div>

            @if (empty($perimetreTrends))
                <x-back.empty-state title="Aucun prélèvement pour ce périmètre" message="Aucune mesure n'a été enregistrée pour ce périmètre : aucun graphique n'est généré." />
            @else
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ $evolutionLimit }} derniers prélèvements du périmètre, du plus ancien au plus récent. Chaque paramètre conserve son unité et son échelle.</p>
                @include('back.quality.partials.trends', ['trends' => $perimetreTrends])
            @endif

            <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:px-6">
                    <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Historique chronologique — {{ $perimetre->nom }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Du plus ancien au plus récent, avec mesures, unités et conformité issues de l'analyse existante.</p>
                </div>

                @if ($perimetreHistory->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    <th class="px-5 py-3 font-medium">Date</th>
                                    <th class="px-5 py-3 font-medium">Référence</th>
                                    <th class="px-5 py-3 font-medium">Point de prélèvement</th>
                                    <th class="px-5 py-3 font-medium">Mesures</th>
                                    <th class="px-5 py-3 font-medium">Statut</th>
                                    <th class="px-5 py-3 font-medium">Alertes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($perimetreHistory as $sample)
                                    <tr class="border-b border-gray-100 align-top last:border-0 dark:border-gray-800">
                                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $sample->date_prelevement?->format('d/m/Y H:i') }}</td>
                                        <td class="px-5 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">
                                            <a href="{{ route('back.quality-samples.show', $sample) }}" class="text-brand-500 hover:underline dark:text-brand-400">{{ $sample->reference }}</a>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $sample->source ?? '—' }}</td>
                                        <td class="px-5 py-3">
                                            @forelse ($sample->parameters as $parameter)
                                                <span class="mb-1 flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                                    <span>{{ $parameter->parametreLabel() }} : <strong class="font-medium">{{ $parameter->valeur }} {{ $parameter->unite }}</strong></span>
                                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $tone($parameter->est_conforme ? 'conforme' : 'non_conforme') }}">{{ $parameter->est_conforme ? 'Conforme' : 'Non conforme' }}</span>
                                                </span>
                                            @empty
                                                <span class="text-sm text-gray-400 dark:text-gray-500">Aucune mesure</span>
                                            @endforelse
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tone($sample->statut) }}">{{ $sample->statutLabel() }}</span>
                                            @if ($sample->score !== null)
                                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $sample->score }} %</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            @forelse ($sample->alerts->where('statut', 'active') as $alert)
                                                <span class="mb-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium {{ $alertTone($alert->niveau) }}">{{ $alert->niveauLabel() }}</span>
                                            @empty
                                                <span class="text-sm text-gray-400 dark:text-gray-500">—</span>
                                            @endforelse
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-5">
                        <x-back.empty-state title="Aucun prélèvement pour ce périmètre" message="Aucun historique n'est disponible pour ce périmètre." />
                    </div>
                @endif
            </div>
        @endif
    </div>

    <form method="GET" class="mb-5 flex flex-wrap gap-2">
        @if ($perimetre)
            <input type="hidden" name="perimetre" value="{{ $perimetre->id }}">
        @endif
        <input type="search" name="search" value="{{ $search }}" placeholder="Référence…" class="w-full max-w-xs {{ $inputClass }}">
        <select name="statut" class="{{ $inputClass }}">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $key => $label)
                <option value="{{ $key }}" @selected($currentStatut === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="zone_id" class="{{ $inputClass }}">
            <option value="">Toutes les zones</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        <select name="infrastructure_id" class="{{ $inputClass }}">
            <option value="">Toutes les infrastructures</option>
            @foreach ($infrastructures as $infrastructure)
                <option value="{{ $infrastructure->id }}" @selected($currentInfrastructure === $infrastructure->id)>{{ $infrastructure->nom }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $from }}" aria-label="Prélevé du" class="{{ $inputClass }}">
        <input type="date" name="to" value="{{ $to }}" aria-label="Prélevé au" class="{{ $inputClass }}">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Filtrer</button>
        @if ($hasFilters)
            <a href="{{ route('back.quality-samples.index', $perimetre ? ['perimetre' => $perimetre->id] : []) }}" class="inline-flex items-center px-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Réinitialiser</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($samples->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Référence</th>
                            <th class="px-5 py-3 font-medium">Zone</th>
                            <th class="px-5 py-3 font-medium">Prélevé le</th>
                            <th class="px-5 py-3 font-medium">Score</th>
                            <th class="px-5 py-3 font-medium">Conformité</th>
                            <th class="px-5 py-3 font-medium">Préleveur</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($samples as $sample)
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-xs font-mono text-gray-500 dark:text-gray-400">{{ $sample->reference }}</td>
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $sample->zone?->nom ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $sample->date_prelevement?->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $sample->score !== null ? $sample->score.' %' : '—' }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tone($sample->statut) }}">{{ $sample->statutLabel() }}</span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $sample->preleveur?->name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.quality-samples.show', $sample) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
                                        <a href="{{ route('back.quality-samples.edit', $sample) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                        <x-back.confirm-delete :action="route('back.quality-samples.destroy', $sample)" message="Ce prélèvement, ses paramètres et ses alertes seront supprimés." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-back.pagination :paginator="$samples" label="prélèvements" />
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucun prélèvement" :message="$hasFilters ? 'Aucun prélèvement ne correspond aux filtres sélectionnés.' : 'Créez un premier prélèvement pour analyser la qualité de l\'eau.'" />
            </div>
        @endif
    </div>
@endsection
