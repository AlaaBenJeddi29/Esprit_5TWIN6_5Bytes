{{-- Bouton de suppression + modale de confirmation (Alpine).
     Props : action (URL DELETE), message?, label? --}}
@props([
    'action',
    'message' => 'Cette action est définitive et ne peut pas être annulée.',
    'label' => 'Supprimer',
])

<div x-data="{ open: false }" class="inline">
    <button type="button" @click="open = true" class="text-xs font-medium text-error-600 hover:underline dark:text-error-400">
        {{ $label }}
    </button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[100] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="absolute inset-0 bg-gray-900/50" @click="open = false"></div>

            <div class="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xl dark:border-gray-800 dark:bg-gray-dark">
                <div class="flex items-start gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-error-50 text-error-500 dark:bg-error-500/15">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 9v3.75m0 3h.008M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.29 2.25h17.78a1.5 1.5 0 0 0 1.29-2.25L13.71 3.86a1.5 1.5 0 0 0-2.58 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Confirmer la suppression</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $message }}</p>
                    </div>
                </div>

                {{ $slot }}

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="open = false"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300"
                    >
                        Annuler
                    </button>
                    <form novalidate method="POST" action="{{ $action }}">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-error-600"
                        >
                            Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
