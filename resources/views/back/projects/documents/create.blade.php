@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouveau document')

@section('content')

    <x-back.page-header title="Nouveau document" :desc="'Projet : '.$project->titre" />

    <div class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.projects.documents.store', $project) }}" enctype="multipart/form-data">
            @csrf
            @include('back.projects.documents._form')
        </form>
    </div>

@endsection
