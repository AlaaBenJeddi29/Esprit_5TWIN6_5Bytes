{{-- Formulaire partagé création / édition d'un gestionnaire --}}
@php
    $isEdit = $gestionnaire->exists;
    $inputClass = 'mt-1 w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nom complet</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $gestionnaire->name) }}"
            class="{{ $inputClass }} @error('name') border-error-400 @enderror"
        >
        @error('name')
            <p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Adresse email</label>
        <input
            type="text"
            id="email"
            name="email"
            value="{{ old('email', $gestionnaire->email) }}"
            class="{{ $inputClass }} @error('email') border-error-400 @enderror"
        >
        @error('email')
            <p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Mot de passe {{ $isEdit ? '(laisser vide pour ne pas changer)' : '' }}
        </label>
        <input
            type="password"
            id="password"
            name="password"
            class="{{ $inputClass }} @error('password') border-error-400 @enderror"
        >
        @error('password')
            <p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirmation</label>
        <input
            type="password"
            id="password_confirmation"
            name="password_confirmation"
            class="{{ $inputClass }}"
        >
    </div>
</div>

<div class="mt-5">
    <label class="inline-flex items-center gap-2">
        <input type="hidden" name="is_active" value="0">
        <input
            type="checkbox"
            name="is_active"
            value="1"
            @checked(old('is_active', $gestionnaire->is_active ?? true))
            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-800"
        >
        <span class="text-sm text-gray-700 dark:text-gray-300">Compte actif</span>
    </label>
    @error('is_active')
        <p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>
    @enderror
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : 'Créer le compte' }}
    </button>
    <a href="{{ route('back.gestionnaires.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
        Annuler
    </a>
</div>
