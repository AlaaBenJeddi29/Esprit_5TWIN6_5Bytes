@extends('front.layouts.app')

@section('title', 'AquaSecure — Nouveau mot de passe')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Compte',
        'heading' => 'Nouveau mot de passe',
        'lead' => 'Choisissez un nouveau mot de passe pour sécuriser votre compte AquaSecure.',
    ])

    <section class="bg-[#F4F7FF] py-14 lg:py-20 dark:bg-dark">
        <div class="container px-4 mx-auto">
            <div class="mx-auto max-w-[525px]">
                <div class="wow fadeInUp relative overflow-hidden rounded-lg bg-white px-8 py-14 text-center shadow-form dark:bg-dark-2 sm:px-12 md:px-[60px]" data-wow-delay=".15s">
                    <h2 class="mb-2 text-2xl font-bold text-dark dark:text-white">Réinitialisation</h2>
                    <p class="mb-8 text-base text-body-color dark:text-dark-6">
                        Renseignez votre adresse e-mail et votre nouveau mot de passe.
                    </p>

                    <form method="POST" action="{{ route('password.store') }}" class="text-left">
                        @csrf

                        <input type="hidden" name="token" value="{{ $request->route('token') }}" />

                        <div class="mb-[22px]">
                            <label for="email" class="mb-2 block text-sm font-medium text-dark dark:text-white">
                                Adresse e-mail
                            </label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email', $request->email) }}"
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
                            <label for="password" class="mb-2 block text-sm font-medium text-dark dark:text-white">
                                Nouveau mot de passe
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
                            @error('password')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-[22px]">
                            <label for="password_confirmation" class="mb-2 block text-sm font-medium text-dark dark:text-white">
                                Confirmer le nouveau mot de passe
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
                                Réinitialiser le mot de passe
                            </button>
                        </div>
                    </form>

                    <a href="{{ route('login') }}" class="text-sm font-medium text-primary hover:underline">
                        Retour à la connexion
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
