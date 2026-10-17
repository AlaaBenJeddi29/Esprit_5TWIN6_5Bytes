@php
    $isEdit = $contractor->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="nom" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nom de l'entreprise <span class="text-error-500">*</span></label>
        <input type="text" id="nom" name="nom" value="{{ old('nom', $contractor->nom) }}"
               class="{{ $inputClass }} @error('nom') border-error-400 dark:border-error-500 @enderror">
        @error('nom')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="specialite" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Spécialité <span class="text-error-500">*</span></label>
        <input type="text" id="specialite" name="specialite" value="{{ old('specialite', $contractor->specialite) }}"
               class="{{ $inputClass }} @error('specialite') border-error-400 dark:border-error-500 @enderror">
        @error('specialite')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email <span class="text-error-500">*</span></label>
        <input type="text" id="email" name="email" value="{{ old('email', $contractor->email) }}"
               class="{{ $inputClass }} @error('email') border-error-400 dark:border-error-500 @enderror">
        @error('email')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="telephone" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Téléphone <span class="text-error-500">*</span></label>
        <input type="text" id="telephone" name="telephone" value="{{ old('telephone', $contractor->telephone) }}"
               class="{{ $inputClass }} @error('telephone') border-error-400 dark:border-error-500 @enderror">
        @error('telephone')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="adresse" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Adresse <span class="text-error-500">*</span></label>
        <textarea id="adresse" name="adresse" rows="2"
                  class="{{ $inputClass }} @error('adresse') border-error-400 dark:border-error-500 @enderror">{{ old('adresse', $contractor->adresse) }}</textarea>
        @error('adresse')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit"
            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : "Créer l'entreprise" }}
    </button>
    <a href="{{ route('back.contractors.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
        Annuler
    </a>
</div>
