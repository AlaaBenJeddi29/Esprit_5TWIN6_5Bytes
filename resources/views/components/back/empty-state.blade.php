{{-- État vide réutilisable : <x-back.empty-state title="..." message="...">[action]</x-back.empty-state> --}}
@props(['title' => 'Aucune donnée', 'message' => null])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-12 text-center dark:border-gray-700 dark:bg-white/[0.02]']) }}>
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white text-gray-400 shadow-theme-xs dark:bg-gray-800 dark:text-gray-500">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </div>

    <p class="mt-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $title }}</p>

    @if ($message)
        <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">{{ $message }}</p>
    @endif

    @isset($action)
        <div class="mt-4 flex justify-center">{{ $action }}</div>
    @endisset
</div>
