@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouvelle planification')

@section('content')
    <x-back.page-header title="Planifier un contrôle" desc="Choisissez la zone, la date du premier contrôle et sa fréquence de récurrence." />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.quality-planifications.store') }}">
            @csrf
            @include('back.quality.planifications._form')
        </form>
    </div>
@endsection
