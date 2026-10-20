@extends('front.layouts.app')

@section('title', 'AquaSecure — Vérification de l’adresse e-mail')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Compte',
        'heading' => 'Vérification de l’adresse e-mail',
        'lead' => 'Une dernière étape avant d’accéder à votre espace AquaSecure.',
    ])

    <section class="bg-[#F4F7FF] py-14 lg:py-20 dark:bg-dark">
        <div class="container px-4 mx-auto">
            <div class="mx-auto max-w-[525px]">
                <div class="wow fadeInUp relative overflow-hidden rounded-lg bg-white px-8 py-14 text-center shadow-form dark:bg-dark-2 sm:px-12 md:px-[60px]" data-wow-delay=".15s">
                    <h2 class="mb-2 text-2xl font-bold text-dark dark:text-white">Vérifiez votre boîte de réception</h2>
                    <p class="mb-8 text-base text-body-color dark:text-dark-6">
                        Merci pour votre inscription. Cliquez sur le lien que nous vous avons
                        envoyé par e-mail pour vérifier votre adresse. Si vous ne l’avez pas
                        reçu, nous pouvons vous en envoyer un nouveau.
                    </p>

                    @if (session('status') == 'verification-link-sent')
                        <div class="mb-6 rounded-md border border-secondary/30 bg-secondary/10 px-4 py-3 text-left text-sm text-secondary" role="status">
                            Un nouveau lien de vérification a été envoyé à l’adresse e-mail utilisée lors de l’inscription.
                        </div>
                    @endif

                    <div class="flex flex-col items-stretch gap-4">
                        <form novalidate method="POST" action="{{ route('verification.send') }}">
                            @csrf
                            <button
                                type="submit"
                                class="w-full rounded-md border border-primary bg-primary px-5 py-3 text-base font-medium text-white transition duration-300 ease-in-out hover:bg-blue-dark"
                            >
                                Renvoyer l’e-mail de vérification
                            </button>
                        </form>

                        <form novalidate method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-primary hover:underline">
                                Se déconnecter
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
