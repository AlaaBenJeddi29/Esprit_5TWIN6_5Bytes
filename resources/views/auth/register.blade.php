@extends('front.layouts.app')

@section('title', 'AquaSecure — Créer un compte')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Espace citoyen',
        'heading' => 'Créer un compte',
        'lead' => 'Rejoignez AquaSecure pour signaler les incidents, suivre la qualité de l’eau et recevoir vos alertes.',
    ])

    <section class="bg-[#F4F7FF] py-14 lg:py-20 dark:bg-dark">
        <div class="container px-4 mx-auto">
            <div class="mx-auto max-w-[525px]">
                <div class="wow fadeInUp relative overflow-hidden rounded-lg bg-white px-8 py-14 text-center shadow-form dark:bg-dark-2 sm:px-12 md:px-[60px]" data-wow-delay=".15s">
                    <div class="mb-8 text-center">
                        <a href="{{ url('/') }}" class="inline-flex items-center justify-center gap-2 text-primary">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="fill-current">
                                <path d="M12 2.5c-3.6 4.2-6.5 7.8-6.5 11.2a6.5 6.5 0 0 0 13 0c0-3.4-2.9-7-6.5-11.2Z" />
                            </svg>
                            <span class="text-2xl font-bold">AquaSecure</span>
                        </a>
                    </div>

                    <h2 class="mb-2 text-2xl font-bold text-dark dark:text-white">Inscription citoyenne</h2>
                    <p class="mb-8 text-base text-body-color dark:text-dark-6">
                        Les champs marqués d’un astérisque sont obligatoires.
                    </p>

                    @if ($errors->any())
                        <div class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-left text-sm text-red-600" role="alert">
                            <p class="font-medium">L’inscription n’a pas pu aboutir.</p>
                            <ul class="mt-1 list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register') }}" class="text-left">
                        @csrf

                        <div class="mb-[22px]">
                            <label for="name" class="mb-2 block text-sm font-medium text-dark dark:text-white">
                                Nom complet <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                required
                                autofocus
                                autocomplete="name"
                                placeholder="Prénom et nom"
                                class="w-full rounded-md border bg-transparent px-5 py-3 text-base text-dark outline-none transition placeholder:text-dark-6 focus:border-primary dark:text-white @error('name') border-red-500 @else border-stroke dark:border-dark-3 @enderror"
                            />
                            @error('name')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-[22px]">
                            <label for="email" class="mb-2 block text-sm font-medium text-dark dark:text-white">
                                Adresse e-mail <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autocomplete="username"
                                placeholder="vous@exemple.fr"
                                class="w-full rounded-md border bg-transparent px-5 py-3 text-base text-dark outline-none transition placeholder:text-dark-6 focus:border-primary dark:text-white @error('email') border-red-500 @else border-stroke dark:border-dark-3 @enderror"
                            />
                            @error('email')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-[22px]">
                            <label for="password" class="mb-2 block text-sm font-medium text-dark dark:text-white">
                                Mot de passe <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="new-password"
                                placeholder="8 caractères minimum"
                                class="w-full rounded-md border bg-transparent px-5 py-3 text-base text-dark outline-none transition placeholder:text-dark-6 focus:border-primary dark:text-white @error('password') border-red-500 @else border-stroke dark:border-dark-3 @enderror"
                            />
                            <p class="mt-2 text-xs text-body-color dark:text-dark-6">8 caractères minimum.</p>
                            @error('password')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-[22px]">
                            <label for="password_confirmation" class="mb-2 block text-sm font-medium text-dark dark:text-white">
                                Confirmer le mot de passe <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                placeholder="Répétez le mot de passe"
                                class="w-full rounded-md border bg-transparent px-5 py-3 text-base text-dark outline-none transition placeholder:text-dark-6 focus:border-primary dark:text-white @error('password_confirmation') border-red-500 @else border-stroke dark:border-dark-3 @enderror"
                            />
                            @error('password_confirmation')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <button
                                type="submit"
                                class="w-full rounded-md border border-primary bg-primary px-5 py-3 text-base font-medium text-white transition duration-300 ease-in-out hover:bg-blue-dark"
                            >
                                Créer mon compte
                            </button>
                        </div>

                        <p class="mb-6 text-xs leading-relaxed text-body-color dark:text-dark-6">
                            Votre compte est créé avec le rôle Citoyen. Les comptes gestionnaires
                            et administrateurs sont créés par l’équipe AquaSecure.
                        </p>
                    </form>

                    <p class="text-sm text-body-color dark:text-dark-6">
                        Déjà inscrit ?
                        <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">Se connecter</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
