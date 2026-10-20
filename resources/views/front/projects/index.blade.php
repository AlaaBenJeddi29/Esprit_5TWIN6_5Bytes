@extends('front.layouts.app')

@section('title', 'AquaSecure — Projets du réseau')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Transparence',
        'heading' => 'Projets d\'amélioration du réseau',
        'lead' => 'Suivez les projets de réparation, modernisation, extension et construction, leur avancement et leur financement.',
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            @php
                $inputClass = 'w-full rounded-md border border-stroke bg-white px-4 py-2.5 text-sm text-dark outline-none focus:border-primary dark:border-dark-3 dark:bg-dark-2 dark:text-white';
            @endphp

            <form method="GET" class="mb-8 flex flex-wrap gap-3">
                <input type="search" name="search" value="{{ $search }}" placeholder="Titre du projet…" class="{{ $inputClass }} max-w-xs">
                <select name="zone_id" class="{{ $inputClass }} max-w-xs">
                    <option value="">Toutes les zones</option>
                    @foreach ($zones as $zone)
                        <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
                    @endforeach
                </select>
                <select name="statut" class="{{ $inputClass }} max-w-xs">
                    <option value="">Tous les statuts</option>
                    @foreach ($statuts as $statut)
                        <option value="{{ $statut }}" @selected($currentStatut === $statut)>{{ ucfirst(str_replace('_', ' ', $statut)) }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <button type="submit" class="rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-dark">Filtrer</button>
                <a href="{{ route('public.projects.index') }}" class="rounded-md border border-stroke px-5 py-2.5 text-sm text-body-color hover:border-primary hover:text-primary dark:border-dark-3 dark:text-dark-6">Réinitialiser</a>
            </form>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($projects as $project)
                    @php
                        $rate = $project->fundingRate();
                        $tone = match ($project->statut) {
                            'en_cours' => 'bg-blue-50 text-primary',
                            'terminé' => 'bg-green-50 text-secondary',
                            'suspendu' => 'bg-yellow-50 text-yellow-600',
                            'annulé' => 'bg-red-50 text-red-500',
                            default => 'bg-gray-1 text-body-color dark:bg-dark-3 dark:text-dark-7',
                        };
                    @endphp

                    <a href="{{ route('public.projects.show', $project) }}" class="flex flex-col rounded-md bg-white p-6 shadow-1 transition hover:-translate-y-1 dark:bg-dark-2">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-body-color dark:text-dark-6">{{ ucfirst(str_replace('_', ' ', $project->type)) }}</p>
                                <h2 class="mt-1 text-lg font-bold text-dark dark:text-white">{{ $project->titre }}</h2>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tone }}">{{ $project->statutLabel() }}</span>
                        </div>

                        <p class="mt-2 text-xs text-body-color dark:text-dark-6">{{ $project->zone?->nom ?? 'Zone non précisée' }}</p>

                        <div class="mt-5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-body-color dark:text-dark-6">Avancement</span>
                                <span class="font-semibold text-dark dark:text-white">{{ $project->avancement_pourcentage }} %</span>
                            </div>
                            <div class="mt-2 h-2 w-full rounded-full bg-gray-1 dark:bg-dark-3">
                                <div class="h-2 rounded-full bg-secondary" style="width: {{ max(2, $project->avancement_pourcentage) }}%"></div>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-3 border-t border-stroke pt-4 text-xs dark:border-dark-3">
                            <div>
                                <p class="text-body-color dark:text-dark-6">Budget prévu</p>
                                <p class="mt-1 font-semibold text-dark dark:text-white">{{ number_format((float) $project->budget_prevu, 0, ',', ' ') }} €</p>
                            </div>
                            <div>
                                <p class="text-body-color dark:text-dark-6">Financé</p>
                                <p class="mt-1 font-semibold text-dark dark:text-white">{{ number_format($rate, 0, ',', ' ') }} %</p>
                            </div>
                        </div>

                        <span class="mt-5 inline-block text-sm font-medium text-primary">Voir le projet →</span>
                    </a>
                @empty
                    <div class="md:col-span-2 xl:col-span-3">
                        <div class="rounded-md bg-white p-8 text-center shadow-1 dark:bg-dark-2">
                            <p class="text-sm text-body-color dark:text-dark-6">Aucun projet ne correspond aux filtres.</p>
                        </div>
                    </div>
                @endforelse
            </div>

            @if ($projects->isNotEmpty())
                <x-front.pagination :paginator="$projects" label="projets" class="mt-8 !border-0 !px-0" />
            @endif
        </div>
    </section>
@endsection
