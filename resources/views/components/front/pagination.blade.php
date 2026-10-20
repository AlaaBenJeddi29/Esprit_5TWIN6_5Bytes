{{-- Pied de liste public : plage affichée, taille de page et pagination.
     Props : paginator (requis), label, showPerPage. --}}
@props([
    'paginator',
    'label' => 'résultats',
    'showPerPage' => true,
])

@php
    $perPageId = 'front-per-page-'.uniqid();
    $querySansPage = collect(request()->query())->except(['page', 'per_page']);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 border-t border-stroke px-6 py-4 dark:border-dark-3 lg:flex-row lg:items-center lg:justify-between']) }}>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <p class="text-sm text-body-color dark:text-dark-6">
            @if ($paginator->total() > 0)
                <span class="font-medium text-dark dark:text-white">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
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

                <label for="{{ $perPageId }}" class="text-sm text-body-color dark:text-dark-6">Par page</label>
                <select id="{{ $perPageId }}" name="per_page" onchange="this.form.submit()"
                        class="rounded-md border border-stroke bg-white px-3 py-1.5 text-sm text-dark outline-none focus:border-primary dark:border-dark-3 dark:bg-dark-2 dark:text-white">
                    @foreach ([10, 15, 25, 50] as $option)
                        <option value="{{ $option }}" @selected($paginator->perPage() === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                <noscript>
                    <button type="submit" class="text-xs font-medium text-primary hover:underline">Appliquer</button>
                </noscript>
            </form>
        @endif
    </div>

    <div class="shrink-0">
        {{ $paginator->links('vendor.pagination.aquasecure-front') }}
    </div>
</div>
