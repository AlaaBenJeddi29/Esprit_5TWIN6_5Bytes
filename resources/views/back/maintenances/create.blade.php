@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouvelle maintenance')

@section('content')

    <x-back.page-header title="Nouvelle maintenance" desc="Planifier une intervention sur une infrastructure." />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form method="POST" action="{{ route('back.maintenances.store') }}">
            @csrf
            @include('back.maintenances._form')
        </form>
    </div>

@endsection
