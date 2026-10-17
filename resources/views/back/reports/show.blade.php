@extends('back.layouts.app')

@section('title', 'AquaSecure — Détail du signalement')

@section('content')
    @php
        $statusTone = match ($report->status) {
            'nouveau' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
            'en_cours' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
            'traite' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'rejete' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('back.reports.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 transition hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Retour à la liste
            </a>
            <h1 class="mt-2 text-xl font-semibold text-gray-800 dark:text-white/90">{{ $report->title }}</h1>
        </div>

        <a href="{{ route('back.reports.edit', $report) }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Modifier
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03] lg:col-span-2">
            <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Description</h2>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $report->description }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Informations</h2>
            <dl class="mt-4 space-y-4 text-sm">
                <div>
                    <dt class="text-xs uppercase text-gray-400">Type</dt>
                    <dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $report->typeLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-400">Statut</dt>
                    <dd class="mt-1">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusTone }}">
                            {{ $report->statusLabel() }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-400">Adresse</dt>
                    <dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $report->address ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-400">Date de signalement</dt>
                    <dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $report->reported_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-400">Auteur</dt>
                    <dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $report->user?->name ?? 'Anonyme' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-400">Créé le</dt>
                    <dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $report->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-400">Mis à jour le</dt>
                    <dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $report->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>
@endsection
