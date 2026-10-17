@extends('front.layouts.app')

@section('title', 'AquaSecure — L\'eau potable, surveillée pour vous')

@section('content')

    {{-- ====== Hero Section ====== --}}
    <div id="home" class="relative overflow-hidden bg-primary pt-[120px] pb-20 md:pt-[130px] lg:pt-[160px] lg:pb-[120px]">
        <div class="container px-4 mx-auto">
            <div class="flex flex-wrap items-center -mx-4">
                <div class="w-full px-4">
                    <div class="hero-content wow fadeInUp mx-auto max-w-[780px] text-center" data-wow-delay=".2s">
                        <h1 class="mb-6 text-3xl font-bold leading-snug text-white sm:text-4xl sm:leading-snug lg:text-5xl lg:leading-[1.2]">
                            Votre eau potable, surveillée et transparente
                        </h1>
                        <p class="mx-auto mb-9 max-w-[600px] text-base font-medium text-white sm:text-lg sm:leading-[1.44]">
                            AquaSecure informe les citoyens sur la qualité de l'eau distribuée,
                            permet de signaler une fuite ou un incident, et publie les interruptions
                            programmées sur le réseau.
                        </p>
                        <ul class="flex flex-wrap items-center justify-center gap-5">
                            <li>
                                <a href="{{ auth()->check() && auth()->user()->isCitoyen() ? route('citizen.incidents.create') : route('login') }}" class="inline-flex items-center justify-center rounded-md bg-white px-7 py-[14px] text-center text-base font-medium text-dark shadow-1 transition duration-300 ease-in-out hover:bg-gray-2 hover:text-body-color">
                                    Signaler un incident
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('public.quality.index') }}" class="flex items-center gap-3 rounded-md bg-white/[0.12] px-6 py-[14px] text-base font-medium text-white transition duration-300 ease-in-out hover:bg-white hover:text-dark">
                                    <svg class="fill-current" width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 2.5c-3.6 4.2-6.5 7.8-6.5 11.2a6.5 6.5 0 0 0 13 0c0-3.4-2.9-7-6.5-11.2Z" />
                                    </svg>
                                    Consulter la qualité de l'eau
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ====== Chiffres clés ====== --}}
    <section class="relative z-10 -mt-10 pb-4">
        <div class="container px-4 mx-auto">
            <div class="grid grid-cols-2 gap-4 rounded-md bg-white p-6 shadow-2 dark:bg-dark-2 md:grid-cols-4 md:p-8">
                <div class="text-center">
                    <span class="block text-3xl font-extrabold text-primary">{{ $zonesCount }}</span>
                    <span class="mt-1 block text-sm text-body-color dark:text-dark-6">Zones desservies</span>
                </div>
                <div class="text-center">
                    <span class="block text-3xl font-extrabold text-primary">{{ $infrastructuresCount }}</span>
                    <span class="mt-1 block text-sm text-body-color dark:text-dark-6">Infrastructures suivies</span>
                </div>
                <div class="text-center">
                    <span class="block text-3xl font-extrabold text-primary">{{ $alertsCount }}</span>
                    <span class="mt-1 block text-sm text-body-color dark:text-dark-6">Alertes qualité actives</span>
                </div>
                <div class="text-center">
                    <span class="block text-3xl font-extrabold text-primary">{{ $activeRestrictions->count() + $upcomingCuts->count() }}</span>
                    <span class="mt-1 block text-sm text-body-color dark:text-dark-6">Restrictions & coupures</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ====== Services Section ====== --}}
    <section class="pb-8 pt-20 dark:bg-dark lg:pb-[70px] lg:pt-[120px]">
        <div class="container px-4 mx-auto">
            <div class="flex flex-wrap -mx-4">
                <div class="w-full px-4">
                    <div class="mx-auto mb-12 max-w-[485px] text-center lg:mb-[70px]">
                        <span class="block mb-2 text-lg font-semibold text-primary">Nos services</span>
                        <h2 class="mb-3 text-3xl font-bold text-dark dark:text-white sm:text-4xl md:text-[40px] md:leading-[1.2]">
                            Ce qu'AquaSecure met à votre disposition
                        </h2>
                        <p class="text-base text-body-color dark:text-dark-6">
                            Un accès simple et transparent à l'information sur l'eau potable
                            pour tous les citoyens.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap -mx-4">
                {{-- Service 1 --}}
                <div class="w-full px-4 md:w-1/2 lg:w-1/4">
                    <div class="mb-12 wow fadeInUp group" data-wow-delay=".1s">
                        <div class="relative z-10 mb-10 flex h-[70px] w-[70px] items-center justify-center rounded-[14px] bg-primary">
                            <span class="absolute left-0 top-0 -z-1 mb-8 flex h-[70px] w-[70px] rotate-[25deg] items-center justify-center rounded-[14px] bg-primary/20 duration-300 group-hover:rotate-45"></span>
                            <svg width="37" height="37" viewBox="0 0 37 37" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M30.5801 8.30514H27.9926C28.6113 7.85514 29.1176 7.34889 29.3426 6.73014C29.6801 5.88639 29.6801 4.48014 27.9363 2.84889C26.0801 1.04889 24.3926 1.04889 23.3238 1.33014C20.9051 1.94889 19.2738 4.76139 18.3738 6.78639C17.4738 4.76139 15.8426 2.00514 13.4238 1.33014C12.3551 1.04889 10.6676 1.10514 8.81133 2.84889C7.06758 4.53639 7.12383 5.88639 7.40508 6.73014C7.63008 7.34889 8.13633 7.85514 8.75508 8.30514H5.71758C4.08633 8.30514 2.73633 9.65514 2.73633 11.2864V14.9989C2.73633 16.5739 4.03008 17.8676 5.60508 17.9239V31.6489C5.60508 33.5614 7.18008 35.1926 9.14883 35.1926H27.5426C29.4551 35.1926 31.0863 33.6176 31.0863 31.6489V17.8676C32.4926 17.6426 33.5613 16.4051 33.5613 14.9426V11.2301C33.5613 9.59889 32.2113 8.30514 30.5801 8.30514Z" fill="white" />
                            </svg>
                        </div>
                        <h4 class="mb-3 text-xl font-bold text-dark dark:text-white">Surveillance de la qualité</h4>
                        <p class="mb-8 text-body-color dark:text-dark-6 lg:mb-9">
                            Suivez les principaux indicateurs de la qualité de l'eau distribuée.
                        </p>
                        <a href="{{ route('public.quality.index') }}" class="text-base font-medium text-dark hover:text-primary dark:text-white dark:hover:text-primary">Consulter</a>
                    </div>
                </div>
                {{-- Service 2 --}}
                <div class="w-full px-4 md:w-1/2 lg:w-1/4">
                    <div class="mb-12 wow fadeInUp group" data-wow-delay=".15s">
                        <div class="relative z-10 mb-10 flex h-[70px] w-[70px] items-center justify-center rounded-[14px] bg-primary">
                            <span class="absolute left-0 top-0 -z-1 mb-8 flex h-[70px] w-[70px] rotate-[25deg] items-center justify-center rounded-[14px] bg-primary/20 duration-300 group-hover:rotate-45"></span>
                            <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M30.5998 1.01245H5.39981C2.98105 1.01245 0.956055 2.9812 0.956055 5.4562V30.6562C0.956055 33.075 2.9248 35.0437 5.39981 35.0437H30.5998C33.0186 35.0437 34.9873 33.075 34.9873 30.6562V5.39995C34.9873 2.9812 33.0186 1.01245 30.5998 1.01245ZM5.39981 3.48745H30.5998C31.6123 3.48745 32.4561 4.3312 32.4561 5.39995V11.1937H3.4873V5.39995C3.4873 4.38745 4.38731 3.48745 5.39981 3.48745Z" fill="white" />
                            </svg>
                        </div>
                        <h4 class="mb-3 text-xl font-bold text-dark dark:text-white">Signalement de fuites</h4>
                        <p class="mb-8 text-body-color dark:text-dark-6 lg:mb-9">
                            Déclarez une fuite, une coupure non annoncée ou un incident sur le réseau.
                        </p>
                        <a href="{{ auth()->check() && auth()->user()->isCitoyen() ? route('citizen.incidents.create') : route('login') }}" class="text-base font-medium text-dark hover:text-primary dark:text-white dark:hover:text-primary">Signaler</a>
                    </div>
                </div>
                {{-- Service 3 --}}
                <div class="w-full px-4 md:w-1/2 lg:w-1/4">
                    <div class="mb-12 wow fadeInUp group" data-wow-delay=".2s">
                        <div class="relative z-10 mb-10 flex h-[70px] w-[70px] items-center justify-center rounded-[14px] bg-primary">
                            <span class="absolute left-0 top-0 -z-1 mb-8 flex h-[70px] w-[70px] rotate-[25deg] items-center justify-center rounded-[14px] bg-primary/20 duration-300 group-hover:rotate-45"></span>
                            <svg width="37" height="37" viewBox="0 0 37 37" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M33.5613 21.4677L31.3675 20.1177C30.805 19.7239 30.0175 19.9489 29.6238 20.5114C29.23 21.1302 29.455 21.8614 30.0175 22.2552L31.48 23.2114L18.1488 31.5927L4.76127 23.2114L6.22377 22.2552C6.84252 21.8614 7.01127 21.0739 6.61752 20.5114C6.22377 19.8927 5.43627 19.7239 4.87377 20.1177L2.68002 21.4677C2.11752 21.8614 1.72377 22.4802 1.72377 23.1552C1.72377 23.8302 2.06127 24.5052 2.68002 24.8427L17.08 33.8989C17.4175 34.1239 17.755 34.1802 18.1488 34.1802C18.5425 34.1802 18.88 34.0677 19.2175 33.8989L33.5613 24.8989C34.1238 24.5052 34.5175 23.8864 34.5175 23.2114C34.5175 22.5364 34.18 21.8614 33.5613 21.4677Z" fill="white" />
                            </svg>
                        </div>
                        <h4 class="mb-3 text-xl font-bold text-dark dark:text-white">Alertes d'interruption</h4>
                        <p class="mb-8 text-body-color dark:text-dark-6 lg:mb-9">
                            Consultez les coupures programmées et les travaux en cours près de chez vous.
                        </p>
                        <a href="{{ route('public.drought.index') }}" class="text-base font-medium text-dark hover:text-primary dark:text-white dark:hover:text-primary">Voir les interruptions</a>
                    </div>
                </div>
                {{-- Service 4 --}}
                <div class="w-full px-4 md:w-1/2 lg:w-1/4">
                    <div class="mb-12 wow fadeInUp group" data-wow-delay=".25s">
                        <div class="relative z-10 mb-10 flex h-[70px] w-[70px] items-center justify-center rounded-[14px] bg-primary">
                            <span class="absolute left-0 top-0 -z-1 mb-8 flex h-[70px] w-[70px] rotate-[25deg] items-center justify-center rounded-[14px] bg-primary/20 duration-300 group-hover:rotate-45"></span>
                            <svg width="37" height="37" viewBox="0 0 37 37" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12.355 2.0614H5.21129C3.29879 2.0614 1.72379 3.6364 1.72379 5.5489V12.6927C1.72379 14.6052 3.29879 16.1802 5.21129 16.1802H12.355C14.2675 16.1802 15.8425 14.6052 15.8425 12.6927V5.60515C15.8988 3.6364 14.3238 2.0614 12.355 2.0614ZM13.3675 12.7489C13.3675 13.3114 12.9175 13.7614 12.355 13.7614H5.21129C4.64879 13.7614 4.19879 13.3114 4.19879 12.7489V5.60515C4.19879 5.04265 4.64879 4.59265 5.21129 4.59265H12.355C12.9175 4.59265 13.3675 5.04265 13.3675 5.60515V12.7489Z" fill="white" />
                                <path d="M31.0863 2.0614H23.9425C22.03 2.0614 20.455 3.6364 20.455 5.5489V12.6927C20.455 14.6052 22.03 16.1802 23.9425 16.1802H31.0863C32.9988 16.1802 34.5738 14.6052 34.5738 12.6927V5.60515C34.5738 3.6364 32.9988 2.0614 31.0863 2.0614Z" fill="white" />
                                <path d="M12.355 20.0051H5.21129C3.29879 20.0051 1.72379 21.5801 1.72379 23.4926V30.6364C1.72379 32.5489 3.29879 34.1239 5.21129 34.1239H12.355C14.2675 34.1239 15.8425 32.5489 15.8425 30.6364V23.5489C15.8988 21.5801 14.3238 20.0051 12.355 20.0051Z" fill="white" />
                                <path d="M31.0863 20.0051H23.9425C22.03 20.0051 20.455 21.5801 20.455 23.4926V30.6364C20.455 32.5489 22.03 34.1239 23.9425 34.1239H31.0863C32.9988 34.1239 34.5738 32.5489 34.5738 30.6364V23.5489C34.5738 21.5801 32.9988 20.0051 31.0863 20.0051Z" fill="white" />
                            </svg>
                        </div>
                        <h4 class="mb-3 text-xl font-bold text-dark dark:text-white">Projets & transparence</h4>
                        <p class="mb-8 text-body-color dark:text-dark-6 lg:mb-9">
                            Suivez les projets d'amélioration du réseau, leur financement et leur avancement.
                        </p>
                        <a href="{{ route('public.projects.index') }}" class="text-base font-medium text-dark hover:text-primary dark:text-white dark:hover:text-primary">Découvrir les projets</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ====== Qualité de l'eau Section ====== --}}
    <section id="quality" class="bg-gray-1 pb-12 pt-20 dark:bg-dark-2 lg:pb-[90px] lg:pt-[120px]">
        <div class="container px-4 mx-auto">
            <div class="mx-auto mb-10 max-w-[500px] text-center wow fadeInUp" data-wow-delay=".1s">
                <span class="block mb-2 text-lg font-semibold text-primary">Qualité de l'eau</span>
                <h2 class="mb-3 text-3xl font-bold text-dark dark:text-white sm:text-4xl md:text-[40px] md:leading-[1.2]">
                    La qualité, zone par zone
                </h2>
                <p class="text-base text-body-color dark:text-dark-6">
                    Dernières analyses connues et alertes actives sur le réseau.
                </p>
            </div>
            <div class="flex flex-wrap -mx-4">
                @forelse ($summaries->take(4) as $item)
                    @php
                        $zone = $item['zone'];
                        $summary = $item['summary'];
                        $latest = $summary['latest'];
                        $statutLabel = match ($latest?->statut) {
                            'conforme' => 'Conforme',
                            'non_conforme' => 'Non conforme',
                            'en_attente' => 'En attente',
                            default => 'Aucune analyse',
                        };
                        $statutTone = match ($latest?->statut) {
                            'conforme' => 'text-secondary',
                            'non_conforme' => 'text-red-500',
                            default => 'text-body-color dark:text-dark-6',
                        };
                    @endphp
                    <div class="w-full px-4 sm:w-1/2 lg:w-1/4">
                        <a href="{{ route('public.quality.zone', $zone) }}" class="mb-8 block rounded-md bg-white p-8 text-center shadow-1 transition hover:-translate-y-1 dark:bg-dark wow fadeInUp" data-wow-delay=".1s">
                            <span class="block text-3xl font-extrabold {{ $statutTone }}">
                                {{ $summary['score_moyen'] !== null ? $summary['score_moyen'].' %' : '—' }}
                            </span>
                            <h4 class="mt-3 text-lg font-bold text-dark dark:text-white">{{ $zone->nom }}</h4>
                            <p class="text-sm {{ $statutTone }}">{{ $statutLabel }}</p>
                            @if ($summary['alertes_actives'] > 0)
                                <p class="mt-2 inline-flex rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-500">
                                    {{ $summary['alertes_actives'] }} alerte(s) active(s)
                                </p>
                            @else
                                <p class="mt-2 text-xs text-body-color dark:text-dark-6">Aucune alerte active</p>
                            @endif
                        </a>
                    </div>
                @empty
                    <div class="w-full px-4">
                        <div class="mb-8 rounded-md bg-white p-8 text-center shadow-1 dark:bg-dark">
                            <p class="text-base text-body-color dark:text-dark-6">Aucune zone de distribution renseignée pour le moment.</p>
                        </div>
                    </div>
                @endforelse
            </div>
            <p class="mx-auto mt-2 max-w-[640px] text-center text-sm text-body-color dark:text-dark-6">
                <a href="{{ route('public.quality.index') }}" class="font-medium text-primary hover:underline">Voir le détail de toutes les zones</a>
                — données issues des prélèvements enregistrés par les services techniques.
            </p>
        </div>
    </section>

    {{-- ====== Signalement Section ====== --}}
    <section id="report" class="pb-12 pt-20 dark:bg-dark lg:pb-[90px] lg:pt-[120px]">
        <div class="container px-4 mx-auto">
            <div class="flex flex-wrap items-center -mx-4">
                <div class="w-full px-4 lg:w-1/2">
                    <div class="mb-12 max-w-[540px] wow fadeInUp lg:mb-0" data-wow-delay=".1s">
                        <span class="block mb-2 text-lg font-semibold text-primary">Signaler un incident</span>
                        <h2 class="mb-5 text-3xl font-bold leading-tight text-dark dark:text-white sm:text-[40px] sm:leading-[1.2]">
                            Une fuite ou une coupure ? Prévenez-nous.
                        </h2>
                        <p class="mb-10 text-base leading-relaxed text-body-color dark:text-dark-6">
                            Votre signalement aide les services techniques à intervenir plus vite
                            et à améliorer la continuité du service pour tous.
                        </p>
                        <a href="{{ auth()->check() && auth()->user()->isCitoyen() ? route('citizen.incidents.create') : route('register') }}" class="inline-flex items-center justify-center py-3 text-base font-medium text-center text-white border rounded-md border-primary bg-primary px-7 hover:border-blue-dark hover:bg-blue-dark">
                            {{ auth()->check() && auth()->user()->isCitoyen() ? 'Signaler un incident' : 'Créer un compte pour signaler' }}
                        </a>
                        <a href="{{ route('front.incidents.track.form') }}" class="ml-4 inline-flex items-center justify-center py-3 text-base font-medium text-center text-primary hover:underline">
                            Suivre un signalement
                        </a>
                    </div>
                </div>
                <div class="w-full px-4 lg:w-1/2">
                    <div class="flex flex-wrap -mx-4">
                        <div class="w-full px-4 md:w-1/3">
                            <div class="mb-8 wow fadeInUp" data-wow-delay=".1s">
                                <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-primary text-lg font-bold text-white">1</div>
                                <h4 class="mb-2 text-lg font-bold text-dark dark:text-white">Décrivez l'incident</h4>
                                <p class="text-sm text-body-color dark:text-dark-6">Type, adresse et description.</p>
                            </div>
                        </div>
                        <div class="w-full px-4 md:w-1/3">
                            <div class="mb-8 wow fadeInUp" data-wow-delay=".15s">
                                <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-primary text-lg font-bold text-white">2</div>
                                <h4 class="mb-2 text-lg font-bold text-dark dark:text-white">Localisez-le</h4>
                                <p class="text-sm text-body-color dark:text-dark-6">Indiquez l'emplacement sur le réseau.</p>
                            </div>
                        </div>
                        <div class="w-full px-4 md:w-1/3">
                            <div class="mb-8 wow fadeInUp" data-wow-delay=".2s">
                                <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-primary text-lg font-bold text-white">3</div>
                                <h4 class="mb-2 text-lg font-bold text-dark dark:text-white">Suivez le traitement</h4>
                                <p class="text-sm text-body-color dark:text-dark-6">Suivez l'avancement de votre signalement.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ====== Interruptions Section ====== --}}
    <section id="outages" class="bg-gray-1 pb-12 pt-20 dark:bg-dark-2 lg:pb-[90px] lg:pt-[120px]">
        <div class="container px-4 mx-auto">
            <div class="mx-auto mb-10 max-w-[500px] text-center wow fadeInUp" data-wow-delay=".1s">
                <span class="block mb-2 text-lg font-semibold text-primary">Interruptions</span>
                <h2 class="mb-3 text-3xl font-bold text-dark dark:text-white sm:text-4xl md:text-[40px] md:leading-[1.2]">
                    État du service
                </h2>
                <p class="text-base text-body-color dark:text-dark-6">
                    Coupures programmées et restrictions d'usage en cours sur le réseau.
                </p>
            </div>
            <div class="mx-auto max-w-[760px] space-y-4 wow fadeInUp" data-wow-delay=".15s">
                @forelse ($activeRestrictions as $restriction)
                    <div class="flex items-start gap-4 rounded-md bg-white p-6 shadow-1 dark:bg-dark">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-warning/20">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.29 2.25h17.78a1.5 1.5 0 0 0 1.29-2.25L13.71 3.86a1.5 1.5 0 0 0-2.58 0Z" stroke="#FBBF24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div>
                            <p class="text-xs font-semibold uppercase text-warning">Restriction {{ $restriction->typeLabel() }} · {{ $restriction->zone?->nom ?? 'Toutes les zones' }}</p>
                            <h4 class="mt-1 text-lg font-bold text-dark dark:text-white">{{ $restriction->titre }}</h4>
                            @if ($restriction->usages_interdits)
                                <p class="mt-1 text-sm text-body-color dark:text-dark-6">{{ \Illuminate\Support\Str::limit($restriction->usages_interdits, 160) }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach

                @forelse ($upcomingCuts as $cut)
                    <div class="flex items-start gap-4 rounded-md bg-white p-6 shadow-1 dark:bg-dark">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="fill-primary">
                                <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 11h-4v-2h2V7h2v6Z" />
                            </svg>
                        </span>
                        <div>
                            <p class="text-xs font-semibold uppercase text-primary">Coupure programmée · {{ $cut->zone?->nom ?? 'Toutes les zones' }}</p>
                            <h4 class="mt-1 text-lg font-bold text-dark dark:text-white">{{ $cut->motif }}</h4>
                            <p class="mt-1 text-sm text-body-color dark:text-dark-6">
                                {{ $cut->date_debut?->format('d/m/Y H:i') }} → {{ $cut->date_fin?->format('d/m/Y H:i') }}
                            </p>
                        </div>
                    </div>
                @endforeach

                @if ($activeRestrictions->isEmpty() && $upcomingCuts->isEmpty())
                    <div class="flex items-center gap-4 rounded-md bg-white p-6 shadow-1 dark:bg-dark">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-secondary/20">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="fill-secondary">
                                <path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z" />
                            </svg>
                        </span>
                        <div>
                            <h4 class="text-lg font-bold text-dark dark:text-white">Aucune interruption en cours</h4>
                            <p class="text-sm text-body-color dark:text-dark-6">
                                Les restrictions et coupures programmées apparaîtront ici automatiquement.
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ====== About Section ====== --}}
    <section id="about" class="pb-8 pt-20 dark:bg-dark lg:pb-[70px] lg:pt-[120px]">
        <div class="container px-4 mx-auto">
            <div class="wow fadeInUp" data-wow-delay=".2s">
                <div class="flex flex-wrap items-center -mx-4">
                    <div class="w-full px-4 lg:w-1/2">
                        <div class="mb-12 max-w-[540px] lg:mb-0">
                            <span class="block mb-2 text-lg font-semibold text-primary">À propos</span>
                            <h2 class="mb-5 text-3xl font-bold leading-tight text-dark dark:text-white sm:text-[40px] sm:leading-[1.2]">
                                La transparence au service de l'eau potable
                            </h2>
                            <p class="mb-10 text-base leading-relaxed text-body-color dark:text-dark-6">
                                AquaSecure met l'information sur l'eau potable à la portée de tous les
                                citoyens. La plateforme centralise la surveillance de la qualité, les
                                signalements d'incidents et les interruptions afin de renforcer la
                                confiance et la réactivité du service public de l'eau.
                                <br /><br />
                                Notre objectif : permettre à chacun de savoir ce que contient son eau,
                                de signaler rapidement un problème et d'être informé en cas de coupure.
                            </p>
                            <a href="{{ auth()->check() && auth()->user()->isCitoyen() ? route('citizen.incidents.create') : route('login') }}" class="inline-flex items-center justify-center py-3 text-base font-medium text-center text-white border rounded-md border-primary bg-primary px-7 hover:border-blue-dark hover:bg-blue-dark">
                                Signaler un problème
                            </a>
                        </div>
                    </div>
                    <div class="w-full px-4 lg:w-1/2">
                        <div class="flex flex-wrap -mx-2 sm:-mx-4 lg:-mx-2 xl:-mx-4">
                            <div class="w-full px-2 sm:w-1/2 sm:px-4 lg:px-2 xl:px-4">
                                <div class="mb-4 sm:mb-8 sm:h-[400px] md:h-[540px] lg:h-[400px] xl:h-[500px]">
                                    <img src="{{ asset('front/images/about/about-image-01.jpg') }}" alt="Surveillance de l'eau" class="object-cover object-center w-full h-full" />
                                </div>
                            </div>
                            <div class="w-full px-2 sm:w-1/2 sm:px-4 lg:px-2 xl:px-4">
                                <div class="mb-4 sm:mb-8 sm:h-[220px] md:h-[346px] lg:mb-4 lg:h-[225px] xl:mb-8 xl:h-[310px]">
                                    <img src="{{ asset('front/images/about/about-image-02.jpg') }}" alt="Qualité de l'eau" class="object-cover object-center w-full h-full" />
                                </div>
                                <div class="relative z-10 mb-4 flex items-center justify-center overflow-hidden bg-primary px-6 py-12 sm:mb-8 sm:h-[160px] sm:p-5 lg:mb-4 xl:mb-8">
                                    <div class="text-center">
                                        <span class="block text-5xl font-extrabold text-white">24/7</span>
                                        <span class="block text-base font-semibold text-white">Surveillance</span>
                                        <span class="block text-base font-medium text-white text-opacity-70">du réseau d'eau</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ====== CTA Section ====== --}}
    <section class="relative z-10 overflow-hidden bg-primary py-20 lg:py-[115px]">
        <div class="container px-4 mx-auto">
            <div class="relative overflow-hidden">
                <div class="flex flex-wrap items-stretch -mx-4">
                    <div class="w-full px-4">
                        <div class="mx-auto max-w-[570px] text-center">
                            <h2 class="mb-2.5 text-3xl font-bold text-white md:text-[38px] md:leading-[1.44]">
                                <span>Un problème avec votre eau ?</span>
                                <span class="text-3xl font-normal md:text-[40px]">Agissez maintenant</span>
                            </h2>
                            <p class="mx-auto mb-6 max-w-[515px] text-base leading-[1.5] text-white">
                                Signalez un incident en quelques clics et suivez son traitement.
                            </p>
                            <a href="{{ auth()->check() && auth()->user()->isCitoyen() ? route('citizen.incidents.create') : route('login') }}" class="inline-block rounded-md border border-transparent bg-secondary px-7 py-3 text-base font-medium text-white transition hover:bg-[#0BB489]">
                                Signaler un incident
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div>
            <span class="absolute top-0 left-0">
                <svg width="495" height="470" viewBox="0 0 495 470" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="55" cy="442" r="138" stroke="white" stroke-opacity="0.04" stroke-width="50" />
                    <circle cx="446" r="39" stroke="white" stroke-opacity="0.04" stroke-width="20" />
                </svg>
            </span>
            <span class="absolute bottom-0 right-0">
                <svg width="493" height="470" viewBox="0 0 493 470" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="462" cy="5" r="138" stroke="white" stroke-opacity="0.04" stroke-width="50" />
                    <circle cx="49" cy="470" r="39" stroke="white" stroke-opacity="0.04" stroke-width="20" />
                </svg>
            </span>
        </div>
    </section>

@endsection
