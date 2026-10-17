@extends('back.layouts.app')

@section('title', 'AquaSecure — Projet '.$project->titre)

@section('content')

    <x-back.page-header :title="$project->titre"
        :desc="ucfirst($project->type).' — '.ucfirst(str_replace('_', ' ', $project->statut)).($project->zone ? ' · '.$project->zone->nom : '')">
        <x-slot:action>
            <a href="{{ route('back.projects.edit', $project) }}"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
                Modifier
            </a>
            <x-back.confirm-delete :action="route('back.projects.destroy', $project)"
                message="Le projet « {{ $project->titre }} » ainsi que ses phases, financements et documents seront supprimés." />
        </x-slot:action>
    </x-back.page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-back.stat-card label="Budget prévu" :value="number_format((float) $project->budget_prevu, 2, ',', ' ').' €'" />
        <x-back.stat-card label="Coût des phases" :value="number_format($budgetTotal, 2, ',', ' ').' €'" />
        <x-back.stat-card label="Financements reçus" :value="number_format($fundingTotal, 2, ',', ' ').' €'" />
        <x-back.stat-card label="Avancement" :value="$project->avancement_pourcentage.' %'" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 lg:col-span-2">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Description</h2>
            <p class="mt-2 whitespace-pre-line text-sm text-gray-500 dark:text-gray-400">{{ $project->description }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
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

    {{-- Phases --}}
    <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
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
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $phase->date_debut?->format('d/m/Y') }} → {{ $phase->date_fin?->format('d/m/Y') }}</td>
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
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
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
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800">
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
