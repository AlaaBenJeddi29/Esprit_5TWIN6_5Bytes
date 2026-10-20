@extends('front.layouts.app')

@section('title', 'AquaSecure — Signaler un incident')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Signalement',
        'heading' => 'Signaler un incident sur le réseau',
        'lead' => 'Décrivez la situation le plus précisément possible. Votre signalement est analysé automatiquement pour être orienté vers le bon service.',
    ])

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            <div class="mx-auto max-w-3xl">
                <a href="{{ route('citizen.incidents.index') }}" class="mb-6 inline-block text-sm font-medium text-primary hover:underline">← Mes signalements</a>

                @php
                    $inputClass = 'w-full rounded-md border border-stroke bg-transparent px-5 py-3 text-sm text-dark outline-none focus:border-primary dark:border-dark-3 dark:text-white';
                @endphp

                <form novalidate method="POST" action="{{ route('citizen.incidents.store') }}" class="rounded-md bg-white p-6 shadow-1 dark:bg-dark-2 md:p-8">
                    @csrf

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="type" class="mb-2 block text-sm font-medium text-dark dark:text-white">Type d'incident <span class="text-red-500">*</span></label>
                            <select id="type" name="type" class="{{ $inputClass }} @error('type') border-red-500 @enderror">
                                <option value="">— Sélectionner —</option>
                                @foreach ($types as $key => $label)
                                    <option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('type')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="severity" class="mb-2 block text-sm font-medium text-dark dark:text-white">Gravité estimée</label>
                            <select id="severity" name="severity" class="{{ $inputClass }} @error('severity') border-red-500 @enderror">
                                <option value="">— Non précisée —</option>
                                @foreach ($severities as $key => $label)
                                    <option value="{{ $key }}" @selected(old('severity') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('severity')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="title" class="mb-2 block text-sm font-medium text-dark dark:text-white">Objet <span class="text-red-500">*</span></label>
                            <input type="text" id="title" name="title" value="{{ old('title') }}"
                                   class="{{ $inputClass }} @error('title') border-red-500 @enderror"
                                   placeholder="Ex. : fuite importante devant l'école">
                            @error('title')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="description" class="mb-2 block text-sm font-medium text-dark dark:text-white">Description <span class="text-red-500">*</span></label>
                            <textarea id="description" name="description" rows="5"
                                      class="{{ $inputClass }} @error('description') border-red-500 @enderror"
                                      placeholder="Décrivez ce que vous observez, depuis quand, et tout élément utile.">{{ old('description') }}</textarea>
                            @error('description')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="address" class="mb-2 block text-sm font-medium text-dark dark:text-white">Adresse ou repère</label>
                            <input type="text" id="address" name="address" value="{{ old('address') }}"
                                   class="{{ $inputClass }} @error('address') border-red-500 @enderror"
                                   placeholder="Rue, quartier, point de repère…">
                            @error('address')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="incident-map-search" class="mb-2 block text-sm font-medium text-dark dark:text-white">Localiser sur la carte</label>
                            <div class="relative">
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <input type="text" id="incident-map-search" data-zone-map-search autocomplete="off"
                                           placeholder="Adresse, commune, lieu…"
                                           class="{{ $inputClass }} min-w-0 flex-1">
                                    <button type="button" data-zone-map-search-button
                                            class="inline-flex shrink-0 items-center justify-center rounded-md bg-primary px-5 py-3 text-sm font-medium text-white hover:bg-blue-dark">
                                        Rechercher
                                    </button>
                                </div>
                                <ul data-zone-map-results role="listbox"
                                    class="absolute z-20 mt-1 hidden max-h-60 w-full overflow-y-auto rounded-md border border-stroke bg-white shadow-2 dark:border-dark-3 dark:bg-dark-2"></ul>
                            </div>
                            <p data-zone-map-status class="mt-2 text-xs text-body-color dark:text-dark-6" aria-live="polite">
                                Cliquez sur la carte ou recherchez une adresse pour renseigner automatiquement la latitude et la longitude.
                            </p>
                            <div id="incident-map" data-zone-map data-lat-input="latitude" data-lng-input="longitude"
                                 class="mt-3 h-72 w-full rounded-md border border-stroke dark:border-dark-3"
                                 aria-label="Carte de localisation de l'incident"></div>
                        </div>

                        <div>
                            <label for="zone_id" class="mb-2 block text-sm font-medium text-dark dark:text-white">Zone</label>
                            <select id="zone_id" name="zone_id" class="{{ $inputClass }} @error('zone_id') border-red-500 @enderror">
                                <option value="">— Non précisée —</option>
                                @foreach ($zones as $zone)
                                    <option value="{{ $zone->id }}" @selected((int) old('zone_id') === $zone->id)>{{ $zone->nom }}</option>
                                @endforeach
                            </select>
                            @error('zone_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="infrastructure_id" class="mb-2 block text-sm font-medium text-dark dark:text-white">Infrastructure concernée</label>
                            <select id="infrastructure_id" name="infrastructure_id" class="{{ $inputClass }} @error('infrastructure_id') border-red-500 @enderror">
                                <option value="">— Non précisée —</option>
                                @foreach ($infrastructures as $infrastructure)
                                    <option value="{{ $infrastructure->id }}" @selected((int) old('infrastructure_id') === $infrastructure->id)>{{ $infrastructure->nom }}</option>
                                @endforeach
                            </select>
                            @error('infrastructure_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="latitude" class="mb-2 block text-sm font-medium text-dark dark:text-white">Latitude</label>
                            <input type="text" id="latitude" name="latitude" value="{{ old('latitude') }}"
                                   autocomplete="off"
                                   class="{{ $inputClass }} @error('latitude') border-red-500 @enderror" placeholder="Ex. : 48.8566">
                            @error('latitude')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">Renseignée par la carte ou saisie manuellement.</p>
                        </div>

                        <div>
                            <label for="longitude" class="mb-2 block text-sm font-medium text-dark dark:text-white">Longitude</label>
                            <input type="text" id="longitude" name="longitude" value="{{ old('longitude') }}"
                                   autocomplete="off"
                                   class="{{ $inputClass }} @error('longitude') border-red-500 @enderror" placeholder="Ex. : 2.3522">
                            @error('longitude')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                            <p class="mt-1 text-xs text-body-color dark:text-dark-6">Renseignée par la carte ou saisie manuellement.</p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-md bg-gray-1 p-4 text-xs text-body-color dark:bg-dark-3 dark:text-dark-7">
                        Une analyse locale automatique peut ajuster la catégorie, la priorité et détecter un éventuel doublon.
                        Aucun fournisseur d'intelligence artificielle externe n'est utilisé.
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-4">
                        <button type="submit" class="inline-flex items-center justify-center rounded-md bg-primary px-7 py-3 text-sm font-medium text-white hover:bg-blue-dark">
                            Envoyer le signalement
                        </button>
                        <a href="{{ route('citizen.dashboard') }}" class="text-sm text-body-color hover:text-primary dark:text-dark-6">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    @vite('resources/js/zone-map.js')
@endpush
