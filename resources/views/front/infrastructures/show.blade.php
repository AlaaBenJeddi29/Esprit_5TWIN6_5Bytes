@extends('front.layouts.app')

@section('title', 'AquaSecure — '.$infrastructure->nom)

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Réseau · '.$infrastructure->typeLabel(),
        'heading' => $infrastructure->nom,
        'lead' => ($infrastructure->zone?->nom ?? 'Zone non précisée').' · Statut : '.$infrastructure->statutLabel(),
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            <a href="{{ route('public.infrastructures.index') }}" class="mb-6 inline-block text-sm font-medium text-primary hover:underline">← Tout le réseau</a>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    <h2 class="text-lg font-bold text-dark dark:text-white">Caractéristiques</h2>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3 border-b border-stroke pb-2 dark:border-dark-3">
                            <dt class="text-body-color dark:text-dark-6">Type</dt>
                            <dd class="font-medium text-dark dark:text-white">{{ $infrastructure->typeLabel() }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-stroke pb-2 dark:border-dark-3">
                            <dt class="text-body-color dark:text-dark-6">Zone</dt>
                            <dd class="font-medium text-dark dark:text-white">{{ $infrastructure->zone?->nom ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-stroke pb-2 dark:border-dark-3">
                            <dt class="text-body-color dark:text-dark-6">Matériau</dt>
                            <dd class="font-medium text-dark dark:text-white">{{ $infrastructure->materiau ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-stroke pb-2 dark:border-dark-3">
                            <dt class="text-body-color dark:text-dark-6">Capacité</dt>
                            <dd class="font-medium text-dark dark:text-white">{{ $infrastructure->capacite !== null ? $infrastructure->capacite.' m³' : '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 border-b border-stroke pb-2 dark:border-dark-3">
                            <dt class="text-body-color dark:text-dark-6">Mise en service</dt>
                            <dd class="font-medium text-dark dark:text-white">{{ $infrastructure->date_installation?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-body-color dark:text-dark-6">Coordonnées</dt>
                            <dd class="font-medium text-dark dark:text-white">
                                @if ($infrastructure->latitude && $infrastructure->longitude)
                                    {{ $infrastructure->latitude }}, {{ $infrastructure->longitude }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Maintenances --}}
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2 lg:col-span-2">
                    <h2 class="text-lg font-bold text-dark dark:text-white">Dernières interventions</h2>

                    @forelse ($infrastructure->maintenances as $maintenance)
                        <div class="border-b border-stroke py-3 last:border-0 dark:border-dark-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-medium text-dark dark:text-white">{{ ucfirst(str_replace('_', ' ', $maintenance->type)) }}</p>
                                <p class="text-xs text-body-color dark:text-dark-6">{{ $maintenance->date_intervention?->format('d/m/Y') }}</p>
                            </div>
                            <p class="mt-1 text-sm text-body-color dark:text-dark-6">{{ $maintenance->description }}</p>
                            <span class="mt-1 inline-flex rounded-full bg-gray-1 px-2.5 py-0.5 text-xs text-body-color dark:bg-dark-3 dark:text-dark-7">{{ ucfirst(str_replace('_', ' ', $maintenance->statut)) }}</span>
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-body-color dark:text-dark-6">Aucune intervention enregistrée.</p>
                    @endforelse
                </div>

                {{-- Projets --}}
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2 lg:col-span-2">
                    <h2 class="text-lg font-bold text-dark dark:text-white">Projets liés</h2>

                    @forelse ($infrastructure->projects as $project)
                        <a href="{{ route('public.projects.show', $project) }}" class="flex items-center justify-between gap-3 border-b border-stroke py-3 last:border-0 dark:border-dark-3">
                            <div>
                                <p class="text-sm font-medium text-dark dark:text-white">{{ $project->titre }}</p>
                                <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ ucfirst(str_replace('_', ' ', $project->type)) }} · {{ $project->avancement_pourcentage }} %</p>
                            </div>
                            <span class="rounded-full bg-gray-1 px-2.5 py-0.5 text-xs text-body-color dark:bg-dark-3 dark:text-dark-7">{{ $project->statutLabel() }}</span>
                        </a>
                    @empty
                        <p class="mt-3 text-sm text-body-color dark:text-dark-6">Aucun projet rattaché à cet ouvrage.</p>
                    @endforelse
                </div>

                {{-- Incidents publics (sans données personnelles) --}}
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    <h2 class="text-lg font-bold text-dark dark:text-white">Signalements récents</h2>

                    @forelse ($infrastructure->incidents as $incident)
                        <div class="border-b border-stroke py-3 last:border-0 dark:border-dark-3">
                            <p class="text-sm text-dark dark:text-white">{{ $incident->typeLabel() }}</p>
                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ $incident->statusLabel() }} · {{ $incident->reported_at?->format('d/m/Y') }}</p>
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-body-color dark:text-dark-6">Aucun signalement public pour cet ouvrage.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
