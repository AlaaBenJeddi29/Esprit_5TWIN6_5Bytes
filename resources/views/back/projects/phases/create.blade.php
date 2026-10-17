@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouvelle phase')

@section('content')

    <x-back.page-header title="Nouvelle phase" :desc="'Projet : '.$project->titre" />

    <div class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form method="POST" action="{{ route('back.projects.phases.store', $project) }}">
            @csrf
            @include('back.projects.phases._form')
        </form>
    </div>

@endsection
