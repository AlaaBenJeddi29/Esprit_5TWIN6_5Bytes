@extends('front.layouts.app')

@section('title', 'AquaSecure — Don annulé')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Don citoyen',
        'heading' => 'Paiement annulé',
        'lead' => $project->titre,
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            <div class="mx-auto max-w-xl rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                <p class="rounded-md border-l-4 border-red-500 bg-gray-50 p-4 text-sm text-dark dark:bg-dark-3 dark:text-white">
                    Le paiement a été annulé : aucun débit n'a été effectué et ce don reste impayé.
                </p>

                <p class="mt-4 text-sm text-body-color dark:text-dark-6">
                    Vous pouvez retenter votre don à tout moment depuis la page du projet.
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('public.projects.show', $project) }}" class="inline-flex items-center justify-center rounded-md bg-secondary px-5 py-2.5 text-sm font-medium text-white hover:bg-[#0BB489]">
                        Retenter le don
                    </a>
                    <a href="{{ route('public.projects.index') }}" class="inline-flex items-center justify-center rounded-md border border-primary px-5 py-2.5 text-sm font-medium text-primary hover:bg-primary hover:text-white">
                        Tous les projets
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
