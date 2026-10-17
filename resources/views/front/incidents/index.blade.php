@extends('front.layouts.app')

@section('title', 'AquaSecure — Mes signalements')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Espace citoyen',
        'heading' => 'Mes signalements',
        'lead' => 'Retrouvez l\'état de vos signalements et échangez avec les services techniques.',
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <a href="{{ route('citizen.dashboard') }}" class="text-sm font-medium text-primary hover:underline">← Retour à mon espace</a>
                <a href="{{ route('citizen.incidents.create') }}" class="inline-flex items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-medium text-white hover:bg-blue-dark">Nouveau signalement</a>
            </div>

            <div class="overflow-hidden rounded-md bg-white shadow-1 dark:bg-dark-2">
                @if ($incidents->isNotEmpty())
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

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-stroke text-left text-xs uppercase text-body-color dark:border-dark-3 dark:text-dark-6">
                                    <th class="px-6 py-4 font-medium">Référence</th>
                                    <th class="px-6 py-4 font-medium">Objet</th>
                                    <th class="px-6 py-4 font-medium">Zone</th>
                                    <th class="px-6 py-4 font-medium">Déposé le</th>
                                    <th class="px-6 py-4 font-medium">Statut</th>
                                    <th class="px-6 py-4"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($incidents as $incident)
                                    <tr class="border-b border-stroke last:border-0 dark:border-dark-3">
                                        <td class="px-6 py-4 text-xs font-mono text-body-color dark:text-dark-6">{{ $incident->reference }}</td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-medium text-dark dark:text-white">{{ $incident->title }}</p>
                                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">{{ $incident->typeLabel() }} · Gravité {{ mb_strtolower($incident->severityLabel()) }}</p>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-body-color dark:text-dark-6">{{ $incident->zone?->nom ?? '—' }}</td>
                                        <td class="px-6 py-4 text-sm text-body-color dark:text-dark-6">{{ $incident->reported_at?->format('d/m/Y H:i') }}</td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tone($incident->status) }}">{{ $incident->statusLabel() }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="{{ route('citizen.incidents.show', $incident) }}" class="text-sm font-medium text-primary hover:underline">Détail</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-stroke px-6 py-4 dark:border-dark-3">{{ $incidents->links() }}</div>
                @else
                    <div class="px-6 py-14 text-center">
                        <p class="text-base font-medium text-dark dark:text-white">Aucun signalement pour le moment</p>
                        <p class="mx-auto mt-2 max-w-md text-sm text-body-color dark:text-dark-6">
                            Une fuite, une coupure ou un problème de qualité ? Signalez-le pour aider les services techniques à intervenir.
                        </p>
                        <a href="{{ route('citizen.incidents.create') }}" class="mt-5 inline-flex items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-medium text-white hover:bg-blue-dark">Créer un signalement</a>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
