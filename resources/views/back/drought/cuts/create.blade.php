@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouvelle coupure programmée')

@section('content')
    <x-back.page-header title="Nouvelle coupure programmée" desc="Planifier une interruption du service." />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.scheduled-cuts.store') }}">
            @csrf
            @include('back.drought.cuts._form')
        </form>
    </div>
@endsection
