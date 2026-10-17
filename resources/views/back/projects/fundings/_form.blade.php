@php
    $isEdit = $funding->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="source" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Source <span class="text-error-500">*</span></label>
        <select id="source" name="source" class="{{ $inputClass }} @error('source') border-error-400 dark:border-error-500 @enderror">
            @foreach ($sources as $source)
                <option value="{{ $source }}" @selected(old('source', $funding->source) === $source)>{{ ucfirst($source) }}</option>
            @endforeach
        </select>
        @error('source')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="donateur_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Donateur <span class="font-normal text-gray-400">(si don)</span></label>
        <select id="donateur_id" name="donateur_id" class="{{ $inputClass }} @error('donateur_id') border-error-400 dark:border-error-500 @enderror">
            <option value="">— Aucun —</option>
            @foreach ($donateurs as $donateur)
                <option value="{{ $donateur->id }}" @selected((int) old('donateur_id', $funding->donateur_id) === $donateur->id)>{{ $donateur->name }}</option>
            @endforeach
        </select>
        @error('donateur_id')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="montant" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Montant (€) <span class="text-error-500">*</span></label>
        <input type="text" id="montant" name="montant" value="{{ old('montant', $funding->montant) }}"
               class="{{ $inputClass }} @error('montant') border-error-400 dark:border-error-500 @enderror">
        @error('montant')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_versement" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date de versement <span class="text-error-500">*</span></label>
        <input type="date" id="date_versement" name="date_versement" value="{{ old('date_versement', $funding->date_versement?->format('Y-m-d')) }}"
               class="{{ $inputClass }} @error('date_versement') border-error-400 dark:border-error-500 @enderror">
        @error('date_versement')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit"
            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : 'Ajouter le financement' }}
    </button>
    <a href="{{ route('back.projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
        Annuler
    </a>
</div>
