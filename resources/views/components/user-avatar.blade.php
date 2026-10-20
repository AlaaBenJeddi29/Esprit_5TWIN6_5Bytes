@props([
    'user' => null,
    'size' => 'h-10 w-10',
    'textSize' => 'text-sm',
    'fallbackClass' => 'bg-brand-500 text-white',
])

@php
    $user ??= auth()->user();
    $initial = mb_strtoupper(mb_substr($user?->name ?? 'U', 0, 1));
@endphp

@if ($user?->avatar_path)
    <img
        src="{{ $user->avatarUrl() }}"
        alt="Photo de profil de {{ $user->name }}"
        {{ $attributes->merge(['class' => $size.' shrink-0 rounded-full object-cover']) }}
    />
@else
    <span
        aria-hidden="true"
        {{ $attributes->merge(['class' => $size.' '.$textSize.' '.$fallbackClass.' flex shrink-0 items-center justify-center rounded-full font-semibold']) }}
    >{{ $initial }}</span>
@endif
