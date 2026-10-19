@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier une phase')

@php use App\Services\ProjectDeadlineIndicators; @endphp

@section('content')

    <x-back.page-header title="Modifier la phase" :desc="'Projet : '.$project->titre" />

    @php($echeancePhase = ProjectDeadlineIndicators::phaseStatus($phase))
    @if (! ProjectDeadlineIndicators::projectIsClosed($project) && ($echeancePhase === ProjectDeadlineIndicators::ECHEANCE_DEPASSEE || $echeancePhase === ProjectDeadlineIndicators::ECHEANCE_PROCHE))
        <div @class([
            'mb-4 flex items-start gap-3 rounded-2xl border p-4 text-sm',
            'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200' => $echeancePhase === ProjectDeadlineIndicators::ECHEANCE_DEPASSEE,
            'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200' => $echeancePhase === ProjectDeadlineIndicators::ECHEANCE_PROCHE,
        ])>
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <p>{{ ProjectDeadlineIndicators::phaseExplanation($phase) }}</p>
        </div>
    @endif

    <div class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form method="POST" action="{{ route('back.projects.phases.update', [$project, $phase]) }}">
            @csrf
            @method('PUT')
            @include('back.projects.phases._form')
        </form>
    </div>

@endsection
