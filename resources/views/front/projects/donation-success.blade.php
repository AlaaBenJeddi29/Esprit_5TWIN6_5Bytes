@extends('front.layouts.app')

@section('title', 'AquaSecure — Don au projet '.$project->titre)

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Don citoyen',
        'heading' => 'Merci pour votre soutien',
        'lead' => $project->titre,
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            <div class="mx-auto max-w-xl rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                <p class="text-sm text-body-color dark:text-dark-6">
                    Montant du don :
                    <span class="font-semibold text-dark dark:text-white">{{ number_format((float) $donation->montant, 2, ',', ' ') }} €</span>
                </p>

                @if ($donation->estConfirmee())
                    <p class="mt-4 rounded-md border-l-4 border-secondary bg-gray-50 p-4 text-sm text-dark dark:bg-dark-3 dark:text-white">
                        Votre don a été confirmé. Merci pour votre contribution au projet.
                    </p>
                @else
                    <p class="mt-4 rounded-md border-l-4 border-yellow-500 bg-gray-50 p-4 text-sm text-dark dark:bg-dark-3 dark:text-white">
                        Votre paiement est en cours de confirmation par Stripe. Le don sera marqué comme payé dès réception de la confirmation sécurisée.
                    </p>
                @endif

                <p class="mt-4 text-xs text-body-color dark:text-dark-6">
                    Aucun numéro de carte n'est collecté ni conservé par AquaSecure.
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('public.projects.show', $project) }}" class="inline-flex items-center justify-center rounded-md border border-primary px-5 py-2.5 text-sm font-medium text-primary hover:bg-primary hover:text-white">
                        Retour au projet
                    </a>
                    <a href="{{ route('citizen.dashboard') }}" class="inline-flex items-center justify-center rounded-md bg-secondary px-5 py-2.5 text-sm font-medium text-white hover:bg-[#0BB489]">
                        Mon espace
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
