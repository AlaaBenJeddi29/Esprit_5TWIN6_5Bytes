@php
    $isEdit = $document->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="titre" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Titre <span class="text-error-500">*</span></label>
        <input type="text" id="titre" name="titre" value="{{ old('titre', $document->titre) }}"
               class="{{ $inputClass }} @error('titre') border-error-400 dark:border-error-500 @enderror">
        @error('titre')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="type" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Type <span class="text-error-500">*</span></label>
        <select id="type" name="type" class="{{ $inputClass }} @error('type') border-error-400 dark:border-error-500 @enderror">
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('type', $document->type) === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>
        @error('type')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="fichier" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Fichier @if (! $isEdit)<span class="text-error-500">*</span>@endif
        </label>
        <input type="file" id="fichier" name="fichier"
               class="mt-1 block w-full text-sm text-gray-800 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-500 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-600 dark:text-white/90 @error('fichier') text-error-600 dark:text-error-400 @enderror">
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Formats : PDF, Word, Excel, images — 10 Mo maximum.
            @if ($isEdit)
                Laissez vide pour conserver le fichier actuel.
            @endif
        </p>
        @if ($isEdit && $document->chemin_fichier)
            <p class="mt-1 break-all text-xs text-gray-500 dark:text-gray-400">Fichier actuel : {{ $document->chemin_fichier }}</p>
        @endif
        @error('fichier')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <input type="hidden" name="est_public" value="0">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="est_public" value="1"
                   class="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20"
                   @checked((bool) old('est_public', $document->est_public))>
            Document public (visible sur le Front Office)
        </label>
        @error('est_public')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit"
            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : 'Ajouter le document' }}
    </button>
    <a href="{{ route('back.projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
        Annuler
    </a>
</div>
