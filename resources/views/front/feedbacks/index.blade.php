@extends('front.layouts.app')

@section('title', 'AquaSecure — Mes évaluations et réclamations')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Espace citoyen',
        'heading' => 'Mes évaluations et réclamations',
        'lead' => 'Évaluez les incidents traités et suivez l\'état de vos réclamations auprès des services techniques.',
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <a href="{{ route('citizen.dashboard') }}" class="text-sm font-medium text-primary hover:underline">← Retour à mon espace</a>
                <a href="{{ route('citizen.incidents.index') }}" class="inline-flex items-center justify-center rounded-md border border-stroke bg-white px-5 py-3 text-sm font-medium text-dark hover:border-primary hover:text-primary dark:border-dark-3 dark:bg-dark-2 dark:text-white">Mes signalements</a>
            </div>

            @php
                $complaintTone = fn (string $status) => match ($status) {
                    'en_attente' => 'bg-yellow-50 text-yellow-600',
                    'en_examen' => 'bg-blue-50 text-primary',
                    'acceptee' => 'bg-green-50 text-secondary',
                    'rejetee' => 'bg-red-50 text-red-500',
                    'cloturee' => 'bg-gray-1 text-body-color dark:bg-dark-3 dark:text-dark-7',
                    default => 'bg-gray-1 text-body-color',
                };
                $inputClass = 'rounded-md border border-stroke bg-white px-4 py-2.5 text-sm text-dark outline-none focus:border-primary dark:border-dark-3 dark:bg-dark-2 dark:text-white';
                $hasFilters = $search !== '' || $currentComplaintStatus !== '';
            @endphp

            <form method="GET" class="mb-6 flex flex-wrap gap-3">
                <input type="search" name="search" value="{{ $search }}" placeholder="Référence ou objet…" class="{{ $inputClass }} max-w-xs">
                <select name="complaint_status" class="{{ $inputClass }} max-w-xs">
                    <option value="">Toutes les réclamations</option>
                    @foreach ($complaintStatuses as $key => $label)
                        <option value="{{ $key }}" @selected($currentComplaintStatus === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <button type="submit" class="rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-dark">Filtrer</button>
                @if ($hasFilters)
                    <a href="{{ route('citizen.feedbacks.index') }}" class="rounded-md border border-stroke px-5 py-2.5 text-sm text-body-color hover:border-primary hover:text-primary dark:border-dark-3 dark:text-dark-6">Réinitialiser</a>
                @endif
            </form>

            <div class="overflow-hidden rounded-md bg-white shadow-1 dark:bg-dark-2">
                @if ($incidents->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-stroke text-left text-xs uppercase text-body-color dark:border-dark-3 dark:text-dark-6">
                                    <th class="px-6 py-4 font-medium">Signalement</th>
                                    <th class="px-6 py-4 font-medium">Zone</th>
                                    <th class="px-6 py-4 font-medium">Traité le</th>
                                    <th class="px-6 py-4 font-medium">Note</th>
                                    <th class="px-6 py-4 font-medium">Réclamation</th>
                                    <th class="px-6 py-4"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($incidents as $incident)
                                    @php $feedback = $incident->feedbacks->first(); @endphp
                                    <tr class="border-b border-stroke last:border-0 dark:border-dark-3">
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-medium text-dark dark:text-white">{{ $incident->title }}</p>
                                            <p class="mt-1 text-xs font-mono text-body-color dark:text-dark-6">{{ $incident->reference }}</p>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-body-color dark:text-dark-6">{{ $incident->zone?->nom ?? '—' }}</td>
                                        <td class="px-6 py-4 text-sm text-body-color dark:text-dark-6">{{ $incident->resolved_at?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="px-6 py-4">
                                            @if ($feedback)
                                                <span class="text-sm font-semibold text-dark dark:text-white">{{ $feedback->rating }}/5</span>
                                                <span class="ml-1 text-sm text-yellow-400">{{ str_repeat('★', $feedback->rating) }}</span>
                                            @else
                                                <span class="text-xs text-body-color dark:text-dark-6">Non évalué</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            @if ($feedback?->hasComplaint())
                                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $complaintTone($feedback->complaint_status) }}">{{ $feedback->complaintStatusLabel() }}</span>
                                                @if ($feedback->reopen_requested)
                                                    <span class="mt-1 block text-xs text-body-color dark:text-dark-6">Réouverture demandée</span>
                                                @endif
                                            @elseif ($feedback)
                                                <span class="text-xs text-body-color dark:text-dark-6">Aucune réclamation</span>
                                            @else
                                                <span class="text-xs text-body-color dark:text-dark-6">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="{{ route('citizen.incidents.show', $incident) }}#avis" class="text-sm font-medium text-primary hover:underline">
                                                {{ $feedback ? 'Consulter' : 'Évaluer' }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <x-front.pagination :paginator="$incidents" label="incidents traités" />
                @else
                    <div class="px-6 py-14 text-center">
                        <p class="text-base font-medium text-dark dark:text-white">{{ $hasFilters ? 'Aucun résultat pour ces filtres' : 'Aucun incident traité pour le moment' }}</p>
                        <p class="mx-auto mt-2 max-w-md text-sm text-body-color dark:text-dark-6">
                            @if ($hasFilters)
                                Modifiez ou réinitialisez les filtres pour retrouver vos évaluations.
                            @else
                                Vous pourrez évaluer un signalement dès que les services techniques l'auront traité.
                            @endif
                        </p>
                        <a href="{{ $hasFilters ? route('citizen.feedbacks.index') : route('citizen.incidents.index') }}" class="mt-5 inline-flex items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-medium text-white hover:bg-blue-dark">{{ $hasFilters ? 'Réinitialiser les filtres' : 'Voir mes signalements' }}</a>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
