@extends('front.layouts.app')

@section('title', 'AquaSecure — Vérification en deux étapes')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Sécurité',
        'heading' => 'Vérification en deux étapes',
        'lead' => 'Un dernier contrôle est nécessaire avant d’accéder à votre espace.',
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

                    <h2 class="mb-2 text-2xl font-bold text-dark dark:text-white">Authentification à deux facteurs</h2>
                    <p class="mb-2 text-base text-body-color dark:text-dark-6">
                        Saisissez le code affiché par votre application d’authentification.
                    </p>
                    <p class="mb-8 text-sm font-medium text-dark dark:text-white">
                        Connexion pour {{ $email }}
                    </p>

                    @if ($errors->any())
                        <div class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-left text-sm text-red-600" role="alert">
                            <p class="font-medium">La vérification a échoué.</p>
                            <ul class="mt-1 list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('two-factor.login.store') }}" class="text-left">
                        @csrf

                        <div class="mb-[22px]">
                            <label for="code" class="mb-2 block text-sm font-medium text-dark dark:text-white">
                                Code à six chiffres
                            </label>
                            <input
                                id="code"
                                type="text"
                                name="code"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                autocomplete="one-time-code"
                                autofocus
                                placeholder="123456"
                                class="w-full rounded-md border bg-transparent px-5 py-3 text-center text-2xl tracking-[0.4em] text-dark outline-none transition placeholder:text-dark-6 focus:border-primary dark:text-white @error('code') border-red-500 @else border-stroke dark:border-dark-3 @enderror"
                            />
                            @error('code')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <button
                                type="submit"
                                class="w-full rounded-md border border-primary bg-primary px-5 py-3 text-base font-medium text-white transition duration-300 ease-in-out hover:bg-blue-dark"
                            >
                                Valider le code
                            </button>
                        </div>
                    </form>

                    <details class="mt-2 text-left">
                        <summary class="cursor-pointer text-sm font-medium text-primary hover:underline">
                            Utiliser un code de secours
                        </summary>

                        <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-4">
                            @csrf

                            <label for="recovery_code" class="mb-2 block text-sm font-medium text-dark dark:text-white">
                                Code de secours
                            </label>
                            <input
                                id="recovery_code"
                                type="text"
                                name="recovery_code"
                                autocomplete="off"
                                placeholder="ABCDE-FGHIJ"
                                class="w-full rounded-md border bg-transparent px-5 py-3 text-center text-lg uppercase tracking-widest text-dark outline-none transition placeholder:text-dark-6 focus:border-primary dark:text-white @error('recovery_code') border-red-500 @else border-stroke dark:border-dark-3 @enderror"
                            />
                            @error('recovery_code')
                                <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                            @enderror

                            <p class="mt-2 text-xs text-body-color dark:text-dark-6">
                                Chaque code de secours ne peut être utilisé qu’une seule fois.
                            </p>

                            <button
                                type="submit"
                                class="mt-4 w-full rounded-md border border-stroke px-5 py-3 text-base font-medium text-dark transition duration-300 ease-in-out hover:border-primary hover:text-primary dark:border-dark-3 dark:text-white"
                            >
                                Valider le code de secours
                            </button>
                        </form>
                    </details>

                    <form method="POST" action="{{ route('two-factor.login.cancel') }}" class="mt-8">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-body-color hover:text-primary dark:text-dark-6">
                            Annuler et revenir à la connexion
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
