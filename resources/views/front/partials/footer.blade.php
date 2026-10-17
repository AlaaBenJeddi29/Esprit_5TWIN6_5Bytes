{{-- Front Office footer --}}
<footer class="wow fadeInUp relative z-10 bg-[#090E34] pt-20 lg:pt-[100px]" data-wow-delay=".15s">
    <div class="container px-4 mx-auto">
        <div class="flex flex-wrap -mx-4">
            <div class="w-full px-4 sm:w-1/2 md:w-1/2 lg:w-4/12 xl:w-3/12">
                <div class="w-full mb-10">
                    <a href="{{ url('/') }}" class="mb-6 inline-block">
                        <span class="flex items-center gap-2 text-white">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="fill-current">
                                <path d="M12 2.5c-3.6 4.2-6.5 7.8-6.5 11.2a6.5 6.5 0 0 0 13 0c0-3.4-2.9-7-6.5-11.2Z" />
                            </svg>
                            <span class="text-xl font-bold">AquaSecure</span>
                        </span>
                    </a>
                    <p class="mb-8 max-w-[270px] text-base text-gray-7">
                        La plateforme citoyenne de surveillance de l'eau potable :
                        qualité, signalements et interruptions, en toute transparence.
                    </p>
                    <p class="text-sm text-gray-7">
                        Plateforme de démonstration — données simulées.
                    </p>
                </div>
            </div>
            <div class="w-full px-4 sm:w-1/2 md:w-1/2 lg:w-2/12 xl:w-2/12">
                <div class="w-full mb-10">
                    <h4 class="text-lg font-semibold text-white mb-9">Plateforme</h4>
                    <ul>
                        <li>
                            <a href="{{ route('front.home') }}" class="inline-block mb-3 text-base text-gray-7 hover:text-primary">Accueil</a>
                        </li>
                        <li>
                            <a href="{{ route('public.quality.index') }}" class="inline-block mb-3 text-base text-gray-7 hover:text-primary">Qualité de l'eau</a>
                        </li>
                        <li>
                            <a href="{{ auth()->check() ? route('citizen.incidents.create') : route('login') }}" class="inline-block mb-3 text-base text-gray-7 hover:text-primary">Signaler un incident</a>
                        </li>
                        <li>
                            <a href="{{ route('public.drought.index') }}" class="inline-block mb-3 text-base text-gray-7 hover:text-primary">Sécheresse & interruptions</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="w-full px-4 sm:w-1/2 md:w-1/2 lg:w-3/12 xl:w-2/12">
                <div class="w-full mb-10">
                    <h4 class="text-lg font-semibold text-white mb-9">Ressources</h4>
                    <ul>
                        <li>
                            <a href="{{ route('public.projects.index') }}" class="inline-block mb-3 text-base text-gray-7 hover:text-primary">Projets</a>
                        </li>
                        <li>
                            <a href="{{ route('public.infrastructures.index') }}" class="inline-block mb-3 text-base text-gray-7 hover:text-primary">Réseau & infrastructures</a>
                        </li>
                        <li>
                            <a href="{{ route('front.incidents.track.form') }}" class="inline-block mb-3 text-base text-gray-7 hover:text-primary">Suivi de signalement</a>
                        </li>
                        <li>
                            <a href="{{ auth()->check() ? route('citizen.subscriptions.index') : route('login') }}" class="inline-block mb-3 text-base text-gray-7 hover:text-primary">Alertes eau</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="w-full px-4 sm:w-1/2 md:w-1/2 lg:w-3/12 xl:w-2/12">
                <div class="w-full mb-10">
                    <h4 class="text-lg font-semibold text-white mb-9">Espace gestionnaire</h4>
                    <ul>
                        <li>
                            <a href="{{ route('login') }}" class="inline-block mb-3 text-base text-gray-7 hover:text-primary">Connexion</a>
                        </li>
                        <li>
                            <a href="{{ route('register') }}" class="inline-block mb-3 text-base text-gray-7 hover:text-primary">Créer un compte</a>
                        </li>
                        <li>
                            <a href="{{ route('back.dashboard') }}" class="inline-block mb-3 text-base text-gray-7 hover:text-primary">Tableau de bord</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-12 border-t border-[#8890A4]/40 py-8 lg:mt-[60px]">
        <div class="container px-4 mx-auto">
            <div class="flex flex-wrap -mx-4">
                <div class="w-full px-4 md:w-2/3 lg:w-1/2">
                    <div class="my-1">
                        <div class="flex items-center justify-center -mx-3 md:justify-start">
                            <p class="px-3 text-base text-gray-7">AquaSecure — surveillance citoyenne de l'eau potable.</p>
                        </div>
                    </div>
                </div>
                <div class="w-full px-4 md:w-1/3 lg:w-1/2">
                    <div class="flex justify-center my-1 md:justify-end">
                        <p class="text-base text-gray-7">
                            © {{ date('Y') }} AquaSecure. Design basé sur
                            <a href="https://tailgrids.com" rel="nofollow noopener" target="_blank" class="text-gray-1 hover:underline">TailGrids / UIdeck</a>
                            (Play), distribué par
                            <a href="https://themewagon.com" rel="nofollow noopener" target="_blank" class="text-gray-1 hover:underline">ThemeWagon</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <span class="absolute left-0 top-0 z-[-1]">
            <img src="{{ asset('front/images/footer/shape-1.svg') }}" alt="" />
        </span>
        <span class="absolute bottom-0 right-0 z-[-1]">
            <img src="{{ asset('front/images/footer/shape-3.svg') }}" alt="" />
        </span>
    </div>
</footer>
