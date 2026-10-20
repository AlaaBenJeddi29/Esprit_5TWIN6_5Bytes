{{-- Pied de liste réutilisable : plage affichée, taille de page et pagination.
     Props : paginator (requis), label, showPerPage. --}}
@props([
    'paginator',
    'label' => 'résultats',
    'showPerPage' => true,
])

@php
    $perPageId = 'per-page-'.uniqid();
    $querySansPage = collect(request()->query())->except(['page', 'per_page']);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 border-t border-gray-100 px-5 py-4 dark:border-gray-800 lg:flex-row lg:items-center lg:justify-between']) }}>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @if ($paginator->total() > 0)
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                sur {{ $paginator->total() }} {{ $label }}
            @else
                Aucun résultat
            @endif
        </p>

        @if ($showPerPage)
            <form method="GET" action="{{ url()->current() }}" class="flex items-center gap-2">
                @foreach ($querySansPage as $key => $value)
                    @if (is_array($value))
                        @foreach ($value as $item)
                            @if (is_scalar($item))
                                <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                            @endif
                        @endforeach
                    @elseif (is_scalar($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach

                <label for="{{ $perPageId }}" class="text-sm text-gray-500 dark:text-gray-400">Par page</label>
                <select id="{{ $perPageId }}" name="per_page" onchange="this.form.submit()"
                        class="rounded-lg border-gray-300 bg-white py-1.5 pl-2.5 pr-8 text-sm text-gray-700 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    @foreach ([10, 15, 25, 50] as $option)
                        <option value="{{ $option }}" @selected($paginator->perPage() === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                <noscript>
                    <button type="submit" class="text-xs font-medium text-brand-500 hover:underline">Appliquer</button>
                </noscript>
            </form>
        @endif
    </div>

    <div class="shrink-0">
        {{ $paginator->links('vendor.pagination.aquasecure') }}
    </div>
</div>
