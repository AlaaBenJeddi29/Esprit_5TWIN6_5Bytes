@extends('front.layouts.app')

@section('title', 'AquaSecure — Connexion')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Espace personnel',
        'heading' => 'Connexion',
        'lead' => 'Accédez à votre espace citoyen ou à l’espace de gestion AquaSecure.',
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

                    <h2 class="mb-2 text-2xl font-bold text-dark dark:text-white">Se connecter</h2>
                    <p class="mb-8 text-base text-body-color dark:text-dark-6">
                        Renseignez vos identifiants pour accéder à votre espace.
                    </p>

                    @if (session('status'))
                        <div class="mb-6 rounded-md border border-secondary/30 bg-secondary/10 px-4 py-3 text-left text-sm text-secondary" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-left text-sm text-red-600" role="alert">
                            <p class="font-medium">La connexion a échoué.</p>
                            <ul class="mt-1 list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form novalidate method="POST" action="{{ route('login') }}" class="text-left">
                        @csrf

                        <div class="mb-[22px]">
                            <label for="email" class="mb-2 block text-sm font-medium text-dark dark:text-white">
                                Adresse e-mail
                            </label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="vous@exemple.fr"
                                class="w-full rounded-md border bg-transparent px-5 py-3 text-base text-dark outline-none transition placeholder:text-dark-6 focus:border-primary dark:text-white @error('email') border-red-500 @else border-stroke dark:border-dark-3 @enderror"
                            />
                            @error('email')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-[22px]">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label for="password" class="block text-sm font-medium text-dark dark:text-white">
                                    Mot de passe
                                </label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary hover:underline">
                                        Mot de passe oublié ?
                                    </a>
                                @endif
                            </div>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="Votre mot de passe"
                                class="w-full rounded-md border bg-transparent px-5 py-3 text-base text-dark outline-none transition placeholder:text-dark-6 focus:border-primary dark:text-white @error('password') border-red-500 @else border-stroke dark:border-dark-3 @enderror"
                            />
                            @error('password')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <label for="remember_me" class="mb-9 flex items-center gap-2 text-sm text-body-color dark:text-dark-6">
                            <input
                                id="remember_me"
                                type="checkbox"
                                name="remember"
                                @checked(old('remember'))
                                class="h-4 w-4 rounded border-stroke text-primary focus:ring-primary dark:border-dark-3 dark:bg-dark-2"
                            />
                            <span>Se souvenir de moi</span>
                        </label>

                        <div class="mb-6">
                            <button
                                type="submit"
                                class="w-full rounded-md border border-primary bg-primary px-5 py-3 text-base font-medium text-white transition duration-300 ease-in-out hover:bg-blue-dark"
                            >
                                Se connecter
                            </button>
                        </div>
                    </form>

                    <p class="text-sm text-body-color dark:text-dark-6">
                        Pas encore de compte ?
                        <a href="{{ route('register') }}" class="font-medium text-primary hover:underline">Créer un compte</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
