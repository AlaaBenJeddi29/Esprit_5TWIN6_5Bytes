@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier une coupure')

@section('content')
    <x-back.page-header title="Modifier la coupure" :desc="$cut->motif" />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.scheduled-cuts.update', $cut) }}">
            @csrf
            @method('PUT')
            @include('back.drought.cuts._form')
        </form>
    </div>
@endsection
