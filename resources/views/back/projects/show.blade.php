@extends('back.layouts.app')

@section('title', 'AquaSecure — Projet '.$project->titre)

@php use App\Services\ProjectDeadlineIndicators; @endphp

@php
    $statutTone = fn (string $statut) => match ($statut) {
        'en_cours' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
        'suspendu' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
        'terminé' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
        'annulé' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
        default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
    };

    $deadlineLabel = match ($metrics->deadlineStatus) {
        ProjectDeadlineIndicators::ECHEANCE_DEPASSEE => 'Dépassée',
        ProjectDeadlineIndicators::ECHEANCE_PROCHE => 'Proche',
        ProjectDeadlineIndicators::ECHEANCE_AUCUNE => '—',
        default => 'Dans les délais',
    };

    $deadlineTone = match ($metrics->deadlineStatus) {
        ProjectDeadlineIndicators::ECHEANCE_DEPASSEE => 'text-error-600 dark:text-error-400',
        ProjectDeadlineIndicators::ECHEANCE_PROCHE => 'text-warning-600 dark:text-warning-400',
        default => 'text-gray-800 dark:text-white/90',
    };
@endphp

@section('content')

    <x-back.page-header :title="$project->titre"
        :desc="ucfirst($project->type).($project->zone ? ' · '.$project->zone->nom : '')">
        <x-slot:action>
            <a href="{{ route('back.projects.rapport', $project) }}" target="_blank"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
                Rapport PDF
            </a>
            <a href="{{ route('back.audit-events.index', ['project_id' => $project->id]) }}"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
                Historique
            </a>
            <a href="{{ route('back.projects.edit', $project) }}"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
                Modifier
            </a>
            <x-back.confirm-delete :action="route('back.projects.destroy', $project)"
                message="Le projet « {{ $project->titre }} » ainsi que ses phases, financements et documents seront supprimés." />
        </x-slot:action>
    </x-back.page-header>

    {{-- Vue d'ensemble : statut, indicateurs, alertes et informations --}}
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-center gap-2 px-5 pt-4 text-xs text-gray-500 dark:text-gray-400">
            <span>Statut</span>
            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statutTone($project->statut) }}">
                {{ ucfirst(str_replace('_', ' ', $project->statut)) }}
            </span>
        </div>

        <dl class="grid grid-cols-2 gap-x-5 gap-y-4 px-5 py-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Budget prévu</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90 sm:text-base">{{ number_format($metrics->budgetPrevu, 2, ',', ' ') }} €</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Coût des phases</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90 sm:text-base">{{ number_format($metrics->phasesCostTotal, 2, ',', ' ') }} €</dd>
                <p class="mt-0.5 text-theme-xs text-gray-400 dark:text-gray-500">Estimation de travaux</p>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Financements reçus</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90 sm:text-base">{{ number_format($metrics->fundingTotal, 2, ',', ' ') }} €</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Reste à financer</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90 sm:text-base">{{ number_format($metrics->fundingGap, 2, ',', ' ') }} €</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Avancement</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90 sm:text-base">{{ $project->avancement_pourcentage }} %</dd>
                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                    <div class="h-2 rounded-full bg-brand-500" style="width: {{ $project->avancement_pourcentage }}%"></div>
                </div>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Taux de financement</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90 sm:text-base">{{ $metrics->fundingRate }} %</dd>
                <p class="mt-0.5 text-theme-xs text-gray-400 dark:text-gray-500">du budget prévu</p>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Phases en retard</dt>
                <dd class="mt-1 text-sm font-semibold sm:text-base {{ $metrics->overduePhaseCount > 0 ? 'text-error-600 dark:text-error-400' : 'text-gray-800 dark:text-white/90' }}">{{ $metrics->overduePhaseCount }}</dd>
                <p class="mt-0.5 text-theme-xs text-gray-400 dark:text-gray-500">sur {{ $metrics->phaseCount }} phase(s)</p>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">Échéance</dt>
                <dd class="mt-1 text-sm font-semibold sm:text-base {{ $deadlineTone }}">{{ $deadlineLabel }}</dd>
                @if ($project->date_fin_prevue)
                    <p class="mt-0.5 text-theme-xs text-gray-400 dark:text-gray-500">Fin prévue le {{ $project->date_fin_prevue->format('d/m/Y') }}</p>
                @endif
            </div>
        </dl>

        @if ($metrics->deadlineStatus === ProjectDeadlineIndicators::ECHEANCE_DEPASSEE || $metrics->deadlineStatus === ProjectDeadlineIndicators::ECHEANCE_PROCHE)
            <div @class([
                'flex items-start gap-2.5 border-t px-5 py-3 text-xs',
                'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200' => $metrics->deadlineStatus === ProjectDeadlineIndicators::ECHEANCE_DEPASSEE,
                'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200' => $metrics->deadlineStatus === ProjectDeadlineIndicators::ECHEANCE_PROCHE,
            ])>
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <p>{{ ProjectDeadlineIndicators::projectExplanation($project) }}</p>
            </div>
        @endif

        @if ($metrics->overFunded)
            <div class="flex items-start gap-2.5 border-t border-amber-200 bg-amber-50 px-5 py-3 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <p>
                    Sur-financement détecté : les financements enregistrés dépassent le budget prévu
                    de {{ number_format($metrics->overFunding, 2, ',', ' ') }} €
                    ({{ number_format($metrics->fundingTotal, 2, ',', ' ') }} € reçus pour
                    {{ number_format($metrics->budgetPrevu, 2, ',', ' ') }} € planifiés).
                    Aucune donnée n'a été rejetée — cette alerte est purement informative.
                </p>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-5 border-t border-gray-100 p-5 lg:grid-cols-3 dark:border-gray-800">
            <div class="lg:col-span-2">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Description</h2>
                <p class="mt-2 whitespace-pre-line text-sm text-gray-500 dark:text-gray-400">{{ $project->description }}</p>
            </div>

            <div class="border-t border-gray-100 pt-4 lg:col-span-1 lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Informations</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-400 dark:text-gray-500">Début</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $project->date_debut?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-400 dark:text-gray-500">Fin prévue</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $project->date_fin_prevue?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-400 dark:text-gray-500">Responsable</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $project->responsable?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-400 dark:text-gray-500">Infrastructure</dt>
                        <dd class="text-gray-700 dark:text-gray-300">
                            @if ($project->infrastructure)
                                <a href="{{ route('back.infrastructures.show', $project->infrastructure) }}" class="font-medium text-brand-500 hover:underline dark:text-brand-400">{{ $project->infrastructure->nom }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    {{-- Phases --}}
    <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Phases ({{ $project->projectPhases->count() }})</h2>
            <a href="{{ route('back.projects.phases.create', $project) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Ajouter une phase</a>
        </div>

        @if ($project->projectPhases->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Nom</th>
                            <th class="px-5 py-3 font-medium">Entreprise</th>
                            <th class="px-5 py-3 font-medium">Période</th>
                            <th class="px-5 py-3 font-medium">Coût</th>
                            <th class="px-5 py-3 font-medium">Avancement</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($project->projectPhases as $phase)
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $phase->nom }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $phase->contractor?->nom ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $phase->date_debut?->format('d/m/Y') }} → {{ $phase->date_fin?->format('d/m/Y') }}
                                    @php($echeancePhase = ProjectDeadlineIndicators::phaseStatus($phase))
                                    @if (! ProjectDeadlineIndicators::projectIsClosed($project) && $echeancePhase === ProjectDeadlineIndicators::ECHEANCE_DEPASSEE)
                                        <span class="ml-1 inline-block rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600 dark:bg-red-500/10 dark:text-red-300"
                                              title="{{ ProjectDeadlineIndicators::phaseExplanation($phase) }}">En retard</span>
                                    @elseif (! ProjectDeadlineIndicators::projectIsClosed($project) && $echeancePhase === ProjectDeadlineIndicators::ECHEANCE_PROCHE)
                                        <span class="ml-1 inline-block rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-600 dark:bg-amber-500/10 dark:text-amber-300"
                                              title="{{ ProjectDeadlineIndicators::phaseExplanation($phase) }}">Échéance proche</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ number_format((float) $phase->cout, 2, ',', ' ') }} €</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $phase->avancement }} %</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.projects.phases.edit', [$project, $phase]) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                        <x-back.confirm-delete :action="route('back.projects.phases.destroy', [$project, $phase])"
                                            message="La phase « {{ $phase->nom }} » sera supprimée." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucune phase" message="Décomposez ce projet en phases de travaux." />
            </div>
        @endif
    </div>

    {{-- Financements --}}
    <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Financements ({{ $project->fundings->count() }})</h2>
            <a href="{{ route('back.projects.fundings.create', $project) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Ajouter un financement</a>
        </div>

        @if ($project->fundings->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Source</th>
                            <th class="px-5 py-3 font-medium">Donateur</th>
                            <th class="px-5 py-3 font-medium">Montant</th>
                            <th class="px-5 py-3 font-medium">Versement</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($project->fundings as $funding)
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $funding->sourceLabel() }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $funding->donateur?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ number_format((float) $funding->montant, 2, ',', ' ') }} €</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $funding->date_versement?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.projects.fundings.edit', [$project, $funding]) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                        <x-back.confirm-delete :action="route('back.projects.fundings.destroy', [$project, $funding])"
                                            message="Ce financement sera supprimé." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucun financement" message="Enregistrez les sources de financement du projet." />
            </div>
        @endif
    </div>

    {{-- Documents --}}
    <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Documents ({{ $project->projectDocuments->count() }})</h2>
            <a href="{{ route('back.projects.documents.create', $project) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Ajouter un document</a>
        </div>

        @if ($project->projectDocuments->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Titre</th>
                            <th class="px-5 py-3 font-medium">Type</th>
                            <th class="px-5 py-3 font-medium">Fichier</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($project->projectDocuments as $document)
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $document->titre }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $document->typeLabel() }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 break-all dark:text-gray-400">{{ $document->chemin_fichier }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.projects.documents.edit', [$project, $document]) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                        <x-back.confirm-delete :action="route('back.projects.documents.destroy', [$project, $document])"
                                            message="Ce document sera retiré du projet." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucun document" message="Référencez les pièces justificatives du projet." />
            </div>
        @endif
    </div>

@endsection
