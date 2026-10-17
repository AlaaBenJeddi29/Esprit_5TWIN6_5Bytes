@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouveau signalement')

@section('content')
    <x-back.page-header
        title="Nouveau signalement"
        desc="Renseignez les informations de l'incident."
    />

    <div class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        @include('back.reports._form', [
            'report' => $report,
            'types' => $types,
            'statuses' => $statuses,
            'action' => route('back.reports.store'),
            'method' => 'POST',
            'submitLabel' => 'Créer le signalement',
            'isEdit' => false,
        ])
    </div>
@endsection
