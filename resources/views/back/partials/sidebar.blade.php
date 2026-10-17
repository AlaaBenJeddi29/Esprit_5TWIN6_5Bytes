@php
    $user = auth()->user();

    $groups = [
        [
            'title' => 'Menu',
            'items' => array_values(array_filter([
                ['label' => 'Tableau de bord', 'route' => 'back.dashboard', 'pattern' => 'back.dashboard', 'icon' => 'dashboard'],
                ['label' => 'Signalements', 'route' => 'back.reports.index', 'pattern' => 'back.reports.*', 'icon' => 'report'],
                $user?->isAdmin()
                    ? ['label' => 'Gestionnaires', 'route' => 'back.gestionnaires.index', 'pattern' => 'back.gestionnaires.*', 'icon' => 'users']
                    : null,
            ])),
        ],
        [
            'title' => 'Gestion 1 — Infrastructures',
            'items' => [
                ['label' => 'Zones', 'route' => 'back.zones.index', 'pattern' => 'back.zones.*', 'icon' => 'zones'],
                ['label' => 'Infrastructures', 'route' => 'back.infrastructures.index', 'pattern' => 'back.infrastructures.*', 'icon' => 'infrastructure'],
                ['label' => 'Maintenances', 'route' => 'back.maintenances.index', 'pattern' => 'back.maintenances.*', 'icon' => 'maintenance'],
            ],
        ],
        [
            'title' => 'Gestion 2 — Incidents',
            'items' => [
                ['label' => 'Incidents', 'route' => 'back.incidents.index', 'pattern' => 'back.incidents.*', 'icon' => 'report'],
            ],
        ],
        [
            'title' => "Gestion 3 — Qualité de l'eau",
            'items' => [
                ['label' => 'Prélèvements', 'route' => 'back.quality-samples.index', 'pattern' => 'back.quality-samples.*', 'icon' => 'quality'],
                ['label' => 'Seuils', 'route' => 'back.quality-thresholds.index', 'pattern' => 'back.quality-thresholds.*', 'icon' => 'threshold'],
                ['label' => 'Alertes', 'route' => 'back.quality-alerts.index', 'pattern' => 'back.quality-alerts.*', 'icon' => 'alert'],
            ],
        ],
        [
            'title' => 'Gestion 4 — Sécheresse',
            'items' => [
                ['label' => 'Niveaux des ressources', 'route' => 'back.water-levels.index', 'pattern' => 'back.water-levels.*', 'icon' => 'drought'],
                ['label' => 'Consommations', 'route' => 'back.consumption-readings.index', 'pattern' => 'back.consumption-readings.index', 'icon' => 'consumption'],
                ['label' => 'Prévisions', 'route' => 'back.consumption-readings.forecast', 'pattern' => 'back.consumption-readings.forecast', 'icon' => 'forecast'],
                ['label' => 'Restrictions', 'route' => 'back.restrictions.index', 'pattern' => 'back.restrictions.*', 'icon' => 'restrictions'],
                ['label' => 'Coupures programmées', 'route' => 'back.scheduled-cuts.index', 'pattern' => 'back.scheduled-cuts.*', 'icon' => 'cuts'],
                ['label' => 'Abonnements alertes', 'route' => 'back.alert-subscriptions.index', 'pattern' => 'back.alert-subscriptions.*', 'icon' => 'users'],
            ],
        ],
        [
            'title' => 'Gestion 5 — Projets & financement',
            'items' => [
                ['label' => 'Projets', 'route' => 'back.projects.index', 'pattern' => 'back.projects.*', 'icon' => 'projects'],
                ['label' => 'Entreprises', 'route' => 'back.contractors.index', 'pattern' => 'back.contractors.*', 'icon' => 'contractors'],
            ],
        ],
    ];

    $icons = [
        'dashboard' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.5 3.25C4.25736 3.25 3.25 4.25736 3.25 5.5V8.99998C3.25 10.2426 4.25736 11.25 5.5 11.25H9C10.2426 11.25 11.25 10.2426 11.25 8.99998V5.5C11.25 4.25736 10.2426 3.25 9 3.25H5.5ZM4.75 5.5C4.75 5.08579 5.08579 4.75 5.5 4.75H9C9.41421 4.75 9.75 5.08579 9.75 5.5V8.99998C9.75 9.41419 9.41421 9.74998 9 9.74998H5.5C5.08579 9.74998 4.75 9.41419 4.75 8.99998V5.5ZM5.5 12.75C4.25736 12.75 3.25 13.7574 3.25 15V18.5C3.25 19.7426 4.25736 20.75 5.5 20.75H9C10.2426 20.75 11.25 19.7427 11.25 18.5V15C11.25 13.7574 10.2426 12.75 9 12.75H5.5ZM4.75 15C4.75 14.5858 5.08579 14.25 5.5 14.25H9C9.41421 14.25 9.75 14.5858 9.75 15V18.5C9.75 18.9142 9.41421 19.25 9 19.25H5.5C5.08579 19.25 4.75 18.9142 4.75 18.5V15ZM12.75 5.5C12.75 4.25736 13.7574 3.25 15 3.25H18.5C19.7426 3.25 20.75 4.25736 20.75 5.5V8.99998C20.75 10.2426 19.7426 11.25 18.5 11.25H15C13.7574 11.25 12.75 10.2426 12.75 8.99998V5.5ZM15 4.75C14.5858 4.75 14.25 5.08579 14.25 5.5V8.99998C14.25 9.41419 14.5858 9.74998 15 9.74998H18.5C18.9142 9.74998 19.25 9.41419 19.25 8.99998V5.5C19.25 5.08579 18.9142 4.75 18.5 4.75H15ZM15 12.75C13.7574 12.75 12.75 13.7574 12.75 15V18.5C12.75 19.7426 13.7574 20.75 15 20.75H18.5C19.7426 20.75 20.75 19.7427 20.75 18.5V15C20.75 13.7574 19.7426 12.75 18.5 12.75H15ZM14.25 15C14.25 14.5858 14.5858 14.25 15 14.25H18.5C18.9142 14.25 19.25 14.5858 19.25 15V18.5C19.25 18.9142 18.9142 19.25 18.5 19.25H15C14.5858 19.25 14.25 18.9142 14.25 18.5V15Z" fill="currentColor"/></svg>',
        'report' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 9v3.75m0 3h.008M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.29 2.25h17.78a1.5 1.5 0 0 0 1.29-2.25L13.71 3.86a1.5 1.5 0 0 0-2.58 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'users' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'zones' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'infrastructure' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3v6m12-6v6M6 9h12M6 9v3a6 6 0 0 0 12 0V9M12 18v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'maintenance' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'projects' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'contractors' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.75 21V6.75A1.5 1.5 0 0 1 5.25 5.25h6a1.5 1.5 0 0 1 1.5 1.5V21M3.75 21h16.5M12.75 21V10.5a1.5 1.5 0 0 1 1.5-1.5h4.5a1.5 1.5 0 0 1 1.5 1.5V21M6.75 8.25h3m-3 3h3m-3 3h3m6.75-1.5h1.5m-1.5 3h1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'quality' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 12.75 11.25 15 15 9.75M21 12c0 4.556-3.72 8.25-8.31 8.25-2.02 0-3.88-.7-5.34-1.87L3 19.5l1.12-4.24A8.07 8.07 0 0 1 3 12c0-4.556 3.72-8.25 8.31-8.25S21 7.444 21 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'drought' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'threshold' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 4v6m0 4v6m12-16v2m0 4v10M3.75 8h4.5m7.5 4h4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        'alert' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'consumption' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625Zm6.75-4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3.375 21 3.879 21 4.5v15.375c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'forecast' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.28m5.94 2.28-2.28 5.941" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'restrictions' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'cuts' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    ];
@endphp

<aside
    id="sidebar"
    x-data="{ get expanded() { return $store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen; } }"
    class="fixed top-0 left-0 z-99999 mt-0 flex h-screen w-[90px] flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out [.sidebar-expanded_&]:min-w-[290px] dark:border-gray-800 dark:bg-gray-900"
    :class="{
        'translate-x-0': $store.sidebar.isMobileOpen,
        'max-xl:-translate-x-full': !$store.sidebar.isMobileOpen
    }"
    @mouseenter="if (!$store.sidebar.isExpanded) $store.sidebar.setHovered(true)"
    @mouseleave="$store.sidebar.setHovered(false)"
>
    {{-- Logo --}}
    <div class="flex items-center pt-8 pb-7" :class="expanded ? 'justify-between' : 'justify-center'">
        <a href="{{ route('back.dashboard') }}" class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-white">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2.5c-3.6 4.2-6.5 7.8-6.5 11.2a6.5 6.5 0 0 0 13 0c0-3.4-2.9-7-6.5-11.2Z" fill="currentColor" />
                </svg>
            </span>
            <span x-show="expanded" x-cloak class="text-lg font-semibold text-gray-900 dark:text-white">
                AquaSecure
            </span>
        </a>
    </div>

    {{-- Navigation --}}
    <div class="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear">
        <nav class="mb-6">
            <div class="flex flex-col gap-4">
                @foreach ($groups as $group)
                    <div>
                        <h2
                            class="mb-4 flex text-xs uppercase leading-[20px] text-gray-400"
                            :class="expanded ? 'justify-start' : 'xl:justify-center'"
                        >
                            <span x-show="expanded" x-cloak>{{ $group['title'] }}</span>
                            <svg x-show="!expanded" x-cloak width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M4.25 12C4.25 11.5858 4.58579 11.25 5 11.25H19C19.4142 11.25 19.75 11.5858 19.75 12C19.75 12.4142 19.4142 12.75 19 12.75H5C4.58579 12.75 4.25 12.4142 4.25 12Z" fill="currentColor" />
                            </svg>
                        </h2>

                        <ul class="flex flex-col gap-1">
                            @foreach ($group['items'] as $item)
                                @php $active = request()->routeIs($item['pattern']); @endphp
                                <li>
                                    <a
                                        href="{{ route($item['route']) }}"
                                        class="menu-item group"
                                        :class="[
                                            '{{ $active ? 'menu-item-active' : 'menu-item-inactive' }}',
                                            expanded ? 'xl:justify-start' : 'xl:justify-center'
                                        ]"
                                        @if ($active) aria-current="page" @endif
                                    >
                                        <span :class="'{{ $active ? 'menu-item-icon-active' : 'menu-item-icon-inactive' }}'">
                                            {!! $icons[$item['icon']] !!}
                                        </span>
                                        <span x-show="expanded" x-cloak class="menu-item-text">{{ $item['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>

        {{-- Rôle connecté --}}
        <div x-show="expanded" x-cloak class="mt-auto border-t border-gray-200 py-5 dark:border-gray-800">
            <p class="text-xs text-gray-400">Rôle actuel</p>
            <p class="mt-0.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $user?->role?->label() ?? '—' }}
            </p>
        </div>
    </div>
</aside>
