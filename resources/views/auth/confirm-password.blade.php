@extends('front.layouts.app')

@section('title', 'AquaSecure — Confirmation du mot de passe')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Sécurité',
        'heading' => 'Confirmation du mot de passe',
        'lead' => 'Cette zone est sécurisée. Confirmez votre mot de passe avant de continuer.',
    ])

    <section class="bg-[#F4F7FF] py-14 lg:py-20 dark:bg-dark">
        <div class="container px-4 mx-auto">
            <div class="mx-auto max-w-[525px]">
                <div class="wow fadeInUp relative overflow-hidden rounded-lg bg-white px-8 py-14 text-center shadow-form dark:bg-dark-2 sm:px-12 md:px-[60px]" data-wow-delay=".15s">
                    <h2 class="mb-2 text-2xl font-bold text-dark dark:text-white">Vérification de sécurité</h2>
                    <p class="mb-2 text-base text-body-color dark:text-dark-6">
                        Pour des raisons de sécurité, veuillez confirmer votre mot de passe.
                    </p>
                    <p class="mb-8 text-sm font-medium text-dark dark:text-white">
                        Connecté en tant que {{ auth()->user()->email }}
                    </p>

                    <form method="POST" action="{{ route('password.confirm') }}" class="text-left">
                        @csrf

                        <div class="mb-[22px]">
                            <label for="password" class="mb-2 block text-sm font-medium text-dark dark:text-white">
                                Mot de passe
                            </label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autofocus
                                autocomplete="current-password"
                                placeholder="Votre mot de passe"
                                class="w-full rounded-md border bg-transparent px-5 py-3 text-base text-dark outline-none transition placeholder:text-dark-6 focus:border-primary dark:text-white @error('password') border-red-500 @else border-stroke dark:border-dark-3 @enderror"
                            />
                            @error('password')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <button
                                type="submit"
                                class="w-full rounded-md border border-primary bg-primary px-5 py-3 text-base font-medium text-white transition duration-300 ease-in-out hover:bg-blue-dark"
                            >
                                Confirmer
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-primary hover:underline">
                            Se déconnecter
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
