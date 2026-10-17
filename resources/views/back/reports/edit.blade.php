@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier un signalement')

@section('content')
    <x-back.page-header
        title="Modifier le signalement"
        :desc="$report->title"
    />

    <div class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        @include('back.reports._form', [
            'report' => $report,
            'types' => $types,
            'statuses' => $statuses,
            'action' => route('back.reports.update', $report),
            'method' => 'PUT',
            'submitLabel' => 'Enregistrer les modifications',
            'isEdit' => true,
        ])
    </div>
@endsection
