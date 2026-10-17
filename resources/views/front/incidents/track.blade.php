@extends('front.layouts.app')

@section('title', 'AquaSecure — Suivi d\'un signalement')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Suivi',
        'heading' => 'Suivre un signalement',
        'lead' => 'Saisissez la référence reçue lors du dépôt pour consulter son état d\'avancement.',
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            <div class="mx-auto max-w-2xl">
                @php
                    $inputClass = 'w-full rounded-md border border-stroke bg-transparent px-5 py-3 text-sm text-dark outline-none focus:border-primary dark:border-dark-3 dark:text-white';
                @endphp

                <form method="POST" action="{{ route('front.incidents.track') }}" class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                    @csrf
                    <label for="reference" class="mb-2 block text-sm font-medium text-dark dark:text-white">Référence du signalement <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-3">
                        <input type="text" id="reference" name="reference" value="{{ old('reference', $reference ?? '') }}"
                               placeholder="INC-20261017-XXXXX" class="{{ $inputClass }} flex-1 font-mono uppercase @error('reference') border-red-500 @enderror">
                        <button type="submit" class="rounded-md bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-blue-dark">Rechercher</button>
                    </div>
                    @error('reference')<p class="mt-2 text-xs text-red-500">{{ $message }}</p>@enderror
                    <p class="mt-3 text-xs text-body-color dark:text-dark-6">
                        La référence figure dans le message de confirmation affiché après le dépôt du signalement.
                    </p>
                </form>

                @if (! empty($notFound))
                    <div class="mt-6 rounded-md border border-red-200 bg-red-50 p-5">
                        <p class="text-sm font-medium text-red-600">Aucun signalement ne correspond à la référence « {{ $reference }} ».</p>
                    </div>
                @endif

                @isset($incident)
                    @php
                        $tone = fn (string $statut) => match ($statut) {
                            'nouveau' => 'bg-blue-50 text-primary',
                            'en_cours' => 'bg-yellow-50 text-yellow-600',
                            'en_attente' => 'bg-gray-1 text-body-color dark:bg-dark-3 dark:text-dark-7',
                            'traite' => 'bg-green-50 text-secondary',
                            'rejete' => 'bg-red-50 text-red-500',
                            default => 'bg-gray-1 text-body-color',
                        };
                    @endphp

                    <div class="mt-6 rounded-md bg-white p-6 shadow-1 dark:bg-dark-2">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-mono text-body-color dark:text-dark-6">{{ $incident->reference }}</p>
                                <h2 class="mt-1 text-lg font-bold text-dark dark:text-white">{{ $incident->title }}</h2>
                            </div>
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $tone($incident->status) }}">{{ $incident->statusLabel() }}</span>
                        </div>

                        <p class="mt-3 text-sm text-body-color dark:text-dark-6">
                            {{ $incident->typeLabel() }} · {{ $incident->zone?->nom ?? 'Zone non précisée' }} · déposé le {{ $incident->reported_at?->format('d/m/Y à H:i') }}
                        </p>

                        @if ($incident->resolved_at)
                            <p class="mt-1 text-sm text-secondary">Traité le {{ $incident->resolved_at->format('d/m/Y à H:i') }}</p>
                        @endif

                        <h3 class="mt-6 text-sm font-semibold uppercase tracking-wide text-body-color dark:text-dark-6">Historique</h3>
                        <ol class="mt-3 space-y-4">
                            @forelse ($incident->statusHistories as $history)
                                <li class="border-l-2 border-primary pl-4">
                                    <p class="text-sm font-medium text-dark dark:text-white">{{ $history->nouveauStatutLabel() }}</p>
                                    <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ $history->created_at?->format('d/m/Y H:i') }}</p>
                                </li>
                            @empty
                                <li class="text-sm text-body-color dark:text-dark-6">Aucun changement de statut enregistré.</li>
                            @endforelse
                        </ol>
                    </div>
                @endisset
            </div>
        </div>
    </section>
@endsection
