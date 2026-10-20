@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-between gap-2 sm:justify-end">
        {{-- Mobile : précédent / page courante / suivant --}}
        <div class="flex w-full items-center justify-between gap-2 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center rounded-md border border-stroke px-3 py-1.5 text-sm font-medium text-body-color/50 dark:border-dark-3 dark:text-dark-6/50">Précédent</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center rounded-md border border-stroke bg-white px-3 py-1.5 text-sm font-medium text-body-color hover:border-primary hover:text-primary dark:border-dark-3 dark:bg-dark-2 dark:text-dark-6">Précédent</a>
            @endif

            <span class="text-sm text-body-color dark:text-dark-6">
                Page {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center rounded-md border border-stroke bg-white px-3 py-1.5 text-sm font-medium text-body-color hover:border-primary hover:text-primary dark:border-dark-3 dark:bg-dark-2 dark:text-dark-6">Suivant</a>
            @else
                <span class="inline-flex items-center rounded-md border border-stroke px-3 py-1.5 text-sm font-medium text-body-color/50 dark:border-dark-3 dark:text-dark-6/50">Suivant</span>
            @endif
        </div>

        {{-- Desktop : numéros de page --}}
        <div class="hidden items-center gap-1 sm:flex">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="Précédent" class="flex h-9 w-9 items-center justify-center rounded-md border border-stroke text-body-color/40 dark:border-dark-3 dark:text-dark-6/40">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Précédent" class="flex h-9 w-9 items-center justify-center rounded-md border border-stroke bg-white text-body-color hover:border-primary hover:text-primary dark:border-dark-3 dark:bg-dark-2 dark:text-dark-6">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="flex h-9 min-w-9 items-center justify-center px-2 text-sm text-body-color dark:text-dark-6">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="flex h-9 min-w-9 items-center justify-center rounded-md border border-primary bg-primary px-2 text-sm font-medium text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="flex h-9 min-w-9 items-center justify-center rounded-md border border-stroke bg-white px-2 text-sm text-body-color hover:border-primary hover:text-primary dark:border-dark-3 dark:bg-dark-2 dark:text-dark-6">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Suivant" class="flex h-9 w-9 items-center justify-center rounded-md border border-stroke bg-white text-body-color hover:border-primary hover:text-primary dark:border-dark-3 dark:bg-dark-2 dark:text-dark-6">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                </a>
            @else
                <span aria-disabled="true" aria-label="Suivant" class="flex h-9 w-9 items-center justify-center rounded-md border border-stroke text-body-color/40 dark:border-dark-3 dark:text-dark-6/40">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
