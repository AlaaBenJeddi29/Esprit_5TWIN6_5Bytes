@php
    $user = auth()->user();
    $space = $user?->isAdmin() ? 'administrateur' : 'gestionnaire';
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="h-full bg-gray-50 dark:bg-gray-900"
    @if ($user?->isGestionnaire()) data-back-office-theme="gestionnaire" @endif
>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>@yield('title', 'AquaSecure — Espace ' . $space)</title>

    <link rel="icon" type="image/png" href="{{ asset('back/favicon.png') }}" />

    @vite(['resources/css/back.css', 'resources/js/app.js'])

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const savedTheme = localStorage.getItem('theme');
                    this.theme = savedTheme === 'dark' ? 'dark' : 'light';
                    this.updateTheme();
                },
                theme: 'light',
                resolvedTheme: 'light',
                set(value) {
                    value = value === 'dark' ? 'dark' : 'light';
                    this.theme = value;
                    localStorage.setItem('theme', value);
                    this.updateTheme();
                },
                toggle() {
                    this.set(this.resolvedTheme === 'dark' ? 'light' : 'dark');
                },
                updateTheme() {
                    const html = document.documentElement;
                    const isDark = this.theme === 'dark';
                    html.classList.toggle('dark', isDark);

                    this.resolvedTheme = isDark ? 'dark' : 'light';
                    html.setAttribute('data-color-scheme', this.resolvedTheme);
                    html.style.colorScheme = this.resolvedTheme;
                    if (document.body) {
                        document.body.dataset['theme'] = this.resolvedTheme;
                        document.body.style.colorScheme = this.resolvedTheme;
                    }
                }
            });

            Alpine.store('sidebar', {
                isExpanded: false,
                isMobileOpen: false,
                isHovered: false,

                init() {
                    const savedState = localStorage.getItem('sidebarExpanded');
                    if (window.innerWidth >= 1280) {
                        this.isExpanded = savedState === null ? true : savedState === 'true';
                    }

                    window.addEventListener('resize', () => {
                        if (window.innerWidth < 1280) {
                            this.isMobileOpen = false;
                        } else {
                            const saved = localStorage.getItem('sidebarExpanded');
                            this.isExpanded = saved === null ? true : saved === 'true';
                            this.isHovered = false;
                        }
                    });
                },

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    this.isMobileOpen = false;

                    if (window.innerWidth >= 1280) {
                        localStorage.setItem('sidebarExpanded', this.isExpanded);
                    }
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                },

                setMobileOpen(value) {
                    this.isMobileOpen = value;
                },

                setHovered(value) {
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = value;
                    }
                }
            });
        });

        (function () {
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.setAttribute('data-color-scheme', 'dark');
            } else {
                document.documentElement.setAttribute('data-color-scheme', 'light');
            }
        })();
    </script>
</head>

<body>
    <div
        class="min-h-screen xl:flex"
        x-data
        :class="{ 'sidebar-expanded': $store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen }"
    >
        @include('back.partials.backdrop')
        @include('back.partials.sidebar')

        <div class="flex-1 ml-0 ltr:xl:ml-[90px] [.sidebar-expanded_&]:ltr:xl:ml-[290px] transition-all duration-300 ease-in-out">
            @include('back.partials.header')

            <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
                @include('back.partials.flash')
                @yield('content')
                @include('back.partials.footer')
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
