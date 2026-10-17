{{-- Carte d'indicateur réutilisable.
     Props : label, value, hint? ; slots optionnels : icon, action. --}}
@props(['label' => '', 'value' => '', 'hint' => null])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6']) }}>
    @isset($icon)
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
            {{ $icon }}
        </div>
    @endisset

    <div class="mt-5 flex items-end justify-between gap-3">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
            <p class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">{{ $value }}</p>

            @if ($hint)
                <p class="mt-1 text-theme-xs text-gray-400 dark:text-gray-500">{{ $hint }}</p>
            @endif
        </div>

        @isset($action)
            <div class="shrink-0">{{ $action }}</div>
        @endisset
    </div>
</div>
