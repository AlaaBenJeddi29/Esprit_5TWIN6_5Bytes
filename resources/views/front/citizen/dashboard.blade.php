@extends('front.layouts.app')

@section('title', 'AquaSecure — Mon espace citoyen')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Espace citoyen',
        'heading' => 'Bonjour '.auth()->user()->name,
        'lead' => 'Suivez vos signalements, vos abonnements aux alertes et les informations du réseau dans votre zone.',
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            {{-- Raccourcis --}}
            <div class="mb-8 flex flex-wrap gap-3">
                <a href="{{ route('citizen.incidents.create') }}" class="inline-flex items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-medium text-white hover:bg-blue-dark">Signaler un incident</a>
                <a href="{{ route('citizen.incidents.index') }}" class="inline-flex items-center justify-center rounded-md border border-stroke bg-white px-5 py-3 text-sm font-medium text-dark hover:border-primary hover:text-primary dark:border-dark-3 dark:bg-dark-2 dark:text-white">Mes signalements</a>
                <a href="{{ route('citizen.subscriptions.index') }}" class="inline-flex items-center justify-center rounded-md border border-stroke bg-white px-5 py-3 text-sm font-medium text-dark hover:border-primary hover:text-primary dark:border-dark-3 dark:bg-dark-2 dark:text-white">Mes alertes</a>
                <a href="{{ route('public.quality.index') }}" class="inline-flex items-center justify-center rounded-md border border-stroke bg-white px-5 py-3 text-sm font-medium text-dark hover:border-primary hover:text-primary dark:border-dark-3 dark:bg-dark-2 dark:text-white">Qualité de l'eau</a>
            </div>

            {{-- Indicateurs --}}
            <div class="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    <p class="text-sm text-body-color dark:text-dark-6">Signalements ouverts</p>
                    <p class="mt-2 text-3xl font-extrabold text-primary">{{ $openIncidents }}</p>
                </div>
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    <p class="text-sm text-body-color dark:text-dark-6">Zones suivies</p>
                    <p class="mt-2 text-3xl font-extrabold text-primary">{{ $subscriptions->count() }}</p>
                </div>
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    <p class="text-sm text-body-color dark:text-dark-6">Dons simulés cumulés</p>
                    <p class="mt-2 text-3xl font-extrabold text-primary">{{ number_format($donationsTotal, 2, ',', ' ') }} €</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Signalements récents --}}
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-dark dark:text-white">Mes derniers signalements</h2>
                        <a href="{{ route('citizen.incidents.index') }}" class="text-sm font-medium text-primary hover:underline">Tout voir</a>
                    </div>

                    @forelse ($incidents as $incident)
                        <a href="{{ route('citizen.incidents.show', $incident) }}" class="flex items-start justify-between gap-3 border-b border-stroke py-3 last:border-0 dark:border-dark-3">
                            <div>
                                <p class="text-sm font-medium text-dark dark:text-white">{{ $incident->title }}</p>
                                <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ $incident->reference }} · {{ $incident->zone?->nom ?? 'Zone non précisée' }} · {{ $incident->reported_at?->format('d/m/Y') }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-gray-1 px-2.5 py-0.5 text-xs font-medium text-body-color dark:bg-dark-3 dark:text-dark-7">{{ $incident->statusLabel() }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-body-color dark:text-dark-6">Vous n'avez pas encore créé de signalement.</p>
                    @endforelse
                </div>

                {{-- Alertes des zones suivies --}}
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    <h2 class="mb-4 text-lg font-bold text-dark dark:text-white">Alertes qualité dans mes zones</h2>

                    @forelse ($qualityAlerts as $alert)
                        <div class="border-b border-stroke py-3 last:border-0 dark:border-dark-3">
                            <p class="text-sm font-medium text-dark dark:text-white">{{ $alert->message }}</p>
                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ $alert->zone?->nom }} · {{ $alert->created_at?->format('d/m/Y H:i') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-body-color dark:text-dark-6">Aucune alerte active dans les zones auxquelles vous êtes abonné.</p>
                    @endforelse
                </div>

                {{-- Restrictions --}}
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    <h2 class="mb-4 text-lg font-bold text-dark dark:text-white">Restrictions en vigueur</h2>

                    @forelse ($restrictions as $restriction)
                        <div class="border-b border-stroke py-3 last:border-0 dark:border-dark-3">
                            <p class="text-sm font-medium text-dark dark:text-white">{{ $restriction->titre }}</p>
                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ $restriction->typeLabel() }} · {{ $restriction->zone?->nom ?? 'Toutes les zones' }} · {{ $restriction->date_debut?->format('d/m/Y') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-body-color dark:text-dark-6">Aucune restriction active.</p>
                    @endforelse
                </div>

                {{-- Coupures --}}
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    <h2 class="mb-4 text-lg font-bold text-dark dark:text-white">Coupures programmées</h2>

                    @forelse ($cuts as $cut)
                        <div class="border-b border-stroke py-3 last:border-0 dark:border-dark-3">
                            <p class="text-sm font-medium text-dark dark:text-white">{{ $cut->motif }}</p>
                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ $cut->zone?->nom }} · {{ $cut->date_debut?->format('d/m/Y H:i') }} → {{ $cut->date_fin?->format('d/m/Y H:i') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-body-color dark:text-dark-6">Aucune coupure programmée dans vos zones.</p>
                    @endforelse
                </div>
            </div>

            {{-- Dons --}}
            <div class="mt-6 rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                <h2 class="mb-4 text-lg font-bold text-dark dark:text-white">Mes dons simulés</h2>

                @forelse ($donations as $donation)
                    <div class="flex items-center justify-between border-b border-stroke py-3 last:border-0 dark:border-dark-3">
                        <div>
                            <p class="text-sm font-medium text-dark dark:text-white">{{ $donation->project?->nom ?? 'Projet supprimé' }}</p>
                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ $donation->date_versement?->format('d/m/Y') ?? '—' }}</p>
                        </div>
                        <span class="text-sm font-semibold text-primary">{{ number_format((float) $donation->montant, 2, ',', ' ') }} €</span>
                    </div>
                @empty
                    <p class="text-sm text-body-color dark:text-dark-6">
                        Aucun don pour le moment. Les dons sont simulés : aucun paiement réel n'est effectué.
                        <a href="{{ route('public.projects.index') }}" class="font-medium text-primary hover:underline">Découvrir les projets</a>.
                    </p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
