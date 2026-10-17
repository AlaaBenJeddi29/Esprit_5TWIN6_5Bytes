{{-- En-tête de page du Back Office : <x-back.page-header title="..." desc="...">[action]</x-back.page-header> --}}
@props(['title' => '', 'desc' => null])

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h1>

        @if ($desc)
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $desc }}</p>
        @endif
    </div>

    @isset($action)
        <div class="flex flex-wrap items-center gap-2">{{ $action }}</div>
    @endisset
</div>
