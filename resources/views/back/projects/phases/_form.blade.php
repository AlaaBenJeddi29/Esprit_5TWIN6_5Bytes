@php
    $isEdit = $phase->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="nom" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nom de la phase <span class="text-error-500">*</span></label>
        <input type="text" id="nom" name="nom" value="{{ old('nom', $phase->nom) }}"
               class="{{ $inputClass }} @error('nom') border-error-400 dark:border-error-500 @enderror">
        @error('nom')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="contractor_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Entreprise <span class="text-error-500">*</span></label>
        <select id="contractor_id" name="contractor_id" class="{{ $inputClass }} @error('contractor_id') border-error-400 dark:border-error-500 @enderror">
            <option value="">— Sélectionner —</option>
            @foreach ($contractors as $contractor)
                <option value="{{ $contractor->id }}" @selected((int) old('contractor_id', $phase->contractor_id) === $contractor->id)>{{ $contractor->nom }}</option>
            @endforeach
        </select>
        @error('contractor_id')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="cout" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Coût (€) <span class="text-error-500">*</span></label>
        <input type="text" id="cout" name="cout" value="{{ old('cout', $phase->cout) }}"
               class="{{ $inputClass }} @error('cout') border-error-400 dark:border-error-500 @enderror">
        @error('cout')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_debut" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date de début <span class="text-error-500">*</span></label>
        <input type="date" id="date_debut" name="date_debut" value="{{ old('date_debut', $phase->date_debut?->format('Y-m-d')) }}"
               class="{{ $inputClass }} @error('date_debut') border-error-400 dark:border-error-500 @enderror">
        @error('date_debut')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_fin" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date de fin <span class="text-error-500">*</span></label>
        <input type="date" id="date_fin" name="date_fin" value="{{ old('date_fin', $phase->date_fin?->format('Y-m-d')) }}"
               class="{{ $inputClass }} @error('date_fin') border-error-400 dark:border-error-500 @enderror">
        @error('date_fin')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="avancement" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Avancement (%) <span class="text-error-500">*</span></label>
        <input type="text" id="avancement" name="avancement" value="{{ old('avancement', $phase->avancement ?? 0) }}"
               class="{{ $inputClass }} @error('avancement') border-error-400 dark:border-error-500 @enderror">
        @error('avancement')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit"
            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : 'Ajouter la phase' }}
    </button>
    <a href="{{ route('back.projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
        Annuler
    </a>
</div>
