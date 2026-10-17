@php
    $isEdit = $maintenance->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="infrastructure_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Infrastructure <span class="text-error-500">*</span></label>
        <select id="infrastructure_id" name="infrastructure_id" class="{{ $inputClass }} @error('infrastructure_id') border-error-400 dark:border-error-500 @enderror">
            <option value="">— Sélectionner —</option>
            @foreach ($infrastructures as $infrastructure)
                <option value="{{ $infrastructure->id }}" @selected((int) old('infrastructure_id', $maintenance->infrastructure_id) === $infrastructure->id)>{{ $infrastructure->nom }}</option>
            @endforeach
        </select>
        @error('infrastructure_id')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="technicien_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Technicien (facultatif)</label>
        <select id="technicien_id" name="technicien_id" class="{{ $inputClass }} @error('technicien_id') border-error-400 dark:border-error-500 @enderror">
            <option value="">— Non assigné —</option>
            @foreach ($techniciens as $technicien)
                <option value="{{ $technicien->id }}" @selected((int) old('technicien_id', $maintenance->technicien_id) === $technicien->id)>{{ $technicien->name }}</option>
            @endforeach
        </select>
        @error('technicien_id')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="type" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Type d'intervention <span class="text-error-500">*</span></label>
        <input type="text" id="type" name="type" value="{{ old('type', $maintenance->type) }}"
               class="{{ $inputClass }} @error('type') border-error-400 dark:border-error-500 @enderror">
        @error('type')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_intervention" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date d'intervention <span class="text-error-500">*</span></label>
        <input type="date" id="date_intervention" name="date_intervention"
               value="{{ old('date_intervention', $maintenance->date_intervention?->format('Y-m-d')) }}"
               class="{{ $inputClass }} @error('date_intervention') border-error-400 dark:border-error-500 @enderror">
        @error('date_intervention')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="cout" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Coût</label>
        <input type="text" id="cout" name="cout" value="{{ old('cout', $maintenance->cout) }}"
               class="{{ $inputClass }} @error('cout') border-error-400 dark:border-error-500 @enderror">
        @error('cout')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="statut" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Statut <span class="text-error-500">*</span></label>
        <input type="text" id="statut" name="statut" value="{{ old('statut', $maintenance->statut ?? 'planifiee') }}"
               class="{{ $inputClass }} @error('statut') border-error-400 dark:border-error-500 @enderror">
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Valeur par défaut : « planifiee ». Cycle de vie des statuts non confirmé.</p>
        @error('statut')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
        <textarea id="description" name="description" rows="4"
                  class="{{ $inputClass }} @error('description') border-error-400 dark:border-error-500 @enderror">{{ old('description', $maintenance->description) }}</textarea>
        @error('description')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit"
            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : 'Créer la maintenance' }}
    </button>
    <a href="{{ route('back.maintenances.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
        Annuler
    </a>
</div>
