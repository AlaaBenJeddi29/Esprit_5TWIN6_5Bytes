<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>@yield('title', 'AquaSecure — L\'eau potable, surveillée pour vous')</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('front/images/logo/favicon.svg') }}" />

    <link rel="stylesheet" href="{{ asset('front/css/swiper-bundle.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('front/css/animate.css') }}" />

    @vite(['resources/css/front.css'])
    @stack('styles')
</head>
<body class="bg-white dark:bg-dark">

    @include('front.partials.header')

    <main>
        @yield('content')
    </main>

    @include('front.partials.footer')

    {{-- Messages flash --}}
    @if (session('success') || session('error'))
        <div class="fixed bottom-5 left-5 z-50 max-w-sm rounded-md border-l-4 bg-white p-4 shadow-2 dark:bg-dark-2 {{ session('error') ? 'border-red-500' : 'border-secondary' }}" role="status">
            <p class="text-sm font-medium text-dark dark:text-white">{{ session('error') ?? session('success') }}</p>
        </div>
    @endif

    {{-- Back to top --}}
    <a
        href="javascript:void(0)"
        class="items-center justify-center hidden w-10 h-10 text-white rounded back-to-top bg-primary hover:bg-blue-dark fixed right-[15px] bottom-[15px] z-50"
    >
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M6 11V1M6 1L1 6M6 1L11 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </a>

    @include('front.partials.scripts')
</body>
</html>
