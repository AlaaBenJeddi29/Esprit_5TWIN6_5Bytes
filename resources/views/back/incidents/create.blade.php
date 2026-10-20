@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouvel incident')

@section('content')
    <x-back.page-header title="Nouvel incident" desc="Créer un incident depuis le Back Office." />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.incidents.store') }}">
            @csrf
            @include('back.incidents._form')
        </form>
    </div>
@endsection
