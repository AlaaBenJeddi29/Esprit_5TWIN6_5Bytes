@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouvelle entreprise')

@section('content')

    <x-back.page-header title="Nouvelle entreprise" desc="Enregistrer un prestataire intervenant sur les projets." />

    <div class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form method="POST" action="{{ route('back.contractors.store') }}">
            @csrf
            @include('back.contractors._form')
        </form>
    </div>

@endsection
