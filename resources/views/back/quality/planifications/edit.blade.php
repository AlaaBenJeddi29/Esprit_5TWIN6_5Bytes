@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier une planification')

@section('content')
    <x-back.page-header :title="'Modifier le contrôle du '.$planification->date_prevue->format('d/m/Y')" desc="La modification ne s'applique qu'à cette occurrence ; l'historique déjà effectué reste intact." />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.quality-planifications.update', $planification) }}">
            @csrf
            @method('PUT')
            @include('back.quality.planifications._form')
        </form>
    </div>
@endsection
