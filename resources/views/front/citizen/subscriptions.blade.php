@extends('front.layouts.app')

@section('title', 'AquaSecure — Mes alertes')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Espace citoyen',
        'heading' => 'Mes abonnements aux alertes',
        'lead' => 'Choisissez les zones et les types d\'alertes que vous souhaitez suivre : qualité de l\'eau, restrictions ou coupures programmées.',
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2 lg:col-span-1">
                    <h2 class="mb-4 text-lg font-bold text-dark dark:text-white">S'abonner à une zone</h2>

                    <form method="POST" action="{{ route('citizen.subscriptions.store') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="zone_id" class="mb-2 block text-sm font-medium text-dark dark:text-white">Zone <span class="text-red-500">*</span></label>
                            <select id="zone_id" name="zone_id" class="w-full rounded-md border border-stroke bg-transparent px-5 py-3 text-sm text-dark outline-none focus:border-primary dark:border-dark-3 dark:text-white @error('zone_id') border-red-500 @enderror">
                                <option value="">— Sélectionner —</option>
                                @foreach ($zones as $zone)
                                    <option value="{{ $zone->id }}" @selected((int) old('zone_id') === $zone->id)>{{ $zone->nom }}</option>
                                @endforeach
                            </select>
                            @error('zone_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="type_alerte" class="mb-2 block text-sm font-medium text-dark dark:text-white">Type d'alerte <span class="text-red-500">*</span></label>
                            <select id="type_alerte" name="type_alerte" class="w-full rounded-md border border-stroke bg-transparent px-5 py-3 text-sm text-dark outline-none focus:border-primary dark:border-dark-3 dark:text-white @error('type_alerte') border-red-500 @enderror">
                                @foreach ($types as $key => $label)
                                    <option value="{{ $key }}" @selected(old('type_alerte', 'tous') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('type_alerte')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-md bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-blue-dark">
                            Enregistrer l'abonnement
                        </button>

                        <p class="text-xs text-body-color dark:text-dark-6">
                            Les alertes sont visibles dans votre espace citoyen et sur les pages publiques. Aucun e-mail n'est envoyé.
                        </p>
                    </form>
                </div>

                <div class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2 lg:col-span-2">
                    <h2 class="mb-4 text-lg font-bold text-dark dark:text-white">Zones suivies</h2>

                    @forelse ($subscriptions as $subscription)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stroke py-3 last:border-0 dark:border-dark-3">
                            <div>
                                <p class="text-sm font-medium text-dark dark:text-white">{{ $subscription->zone?->nom ?? 'Zone supprimée' }}</p>
                                <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ $subscription->typeLabel() }} · depuis le {{ $subscription->created_at?->format('d/m/Y') }}</p>
                            </div>

                            <form method="POST" action="{{ route('citizen.subscriptions.destroy', $subscription) }}" onsubmit="return confirm('Supprimer cet abonnement ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-500 hover:underline">Se désabonner</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-body-color dark:text-dark-6">
                            Vous n'êtes abonné à aucune zone. Utilisez le formulaire pour suivre les alertes de votre secteur.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
