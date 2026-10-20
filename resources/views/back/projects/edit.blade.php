@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier un projet')

@section('content')

    <x-back.page-header title="Modifier le projet" :desc="$project->titre" />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.projects.update', $project) }}">
            @csrf
            @method('PUT')
            @include('back.projects._form')
        </form>
    </div>

@endsection
