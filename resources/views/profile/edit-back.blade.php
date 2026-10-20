@extends('back.layouts.app')

@section('title', 'AquaSecure — Mon profil')

@section('content')
    <x-back.page-header
        title="Mon profil"
        desc="Gérez vos informations personnelles, votre photo, votre mot de passe et la sécurité de votre compte."
    />

    @php
        $inputBase = 'mt-1.5 block w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs outline-none transition focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:text-white/90 dark:focus:border-brand-800';
        $labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
        $errorClass = 'mt-1.5 text-xs font-medium text-error-600 dark:text-error-400';
        $cardClass = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
        $sectionHeader = 'flex items-start gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:px-6';
        $sectionIcon = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400';
        $sectionTitle = 'text-base font-medium text-gray-800 dark:text-white/90';
        $sectionDesc = 'mt-0.5 text-sm text-gray-500 dark:text-gray-400';
        $primaryButton = 'inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600';
        $secondaryButton = 'inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300';
        $dangerButton = 'inline-flex items-center justify-center gap-2 rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-error-600';
    @endphp

    @if (session('email_changed'))
        <div class="mb-4 flex items-start gap-3 rounded-xl border border-warning-500/30 bg-warning-50 p-4 dark:bg-warning-500/15" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0 fill-warning-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 3.75a.75.75 0 0 1 .75.75v6a.75.75 0 0 1-1.5 0v-6A.75.75 0 0 1 12 3.75Zm0 12a.75.75 0 0 1 .75.75v.75a.75.75 0 0 1-1.5 0v-.75a.75.75 0 0 1 .75-.75ZM1.8 12a10.2 10.2 0 1 1 20.4 0 10.2 10.2 0 0 1-20.4 0Z" />
            </svg>
            <p class="text-sm text-warning-700 dark:text-warning-400">
                Votre adresse e-mail a changé. Vérifiez-la pour retrouver l'accès complet à votre espace.
            </p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Colonne principale --}}
        <div class="space-y-6 xl:col-span-2">
            {{-- Informations personnelles --}}
            <div class="{{ $cardClass }}">
                <div class="{{ $sectionHeader }}">
                    <span class="{{ $sectionIcon }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="{{ $sectionTitle }}">Informations personnelles</h2>
                        <p class="{{ $sectionDesc }}">Votre rôle et le statut de votre compte sont gérés par les administrateurs.</p>
                    </div>
                </div>

                <form novalidate method="POST" action="{{ route('profile.update') }}" class="px-5 py-5 md:px-6">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="name" class="{{ $labelClass }}">Nom complet <span class="text-error-500">*</span></label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="{{ old('name', $user->name) }}"
                                autocomplete="name"
                                required
                                class="{{ $inputBase }} @error('name') border-error-400 dark:border-error-500 @else border-gray-300 dark:border-gray-700 @enderror"
                            />
                            @error('name')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="email" class="{{ $labelClass }}">Adresse e-mail <span class="text-error-500">*</span></label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email', $user->email) }}"
                                autocomplete="email"
                                required
                                class="{{ $inputBase }} @error('email') border-error-400 dark:border-error-500 @else border-gray-300 dark:border-gray-700 @enderror"
                            />
                            @error('email')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror
                            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                Toute modification nécessite une nouvelle vérification.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <button type="submit" class="{{ $primaryButton }}">Enregistrer les modifications</button>

                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $user->role->badgeClass() }}">
                            {{ $user->role->label() }}
                        </span>
                    </div>
                </form>

                @if (! $user->hasVerifiedEmail())
                    <div class="mx-5 mb-5 rounded-xl border border-warning-500/30 bg-warning-50 p-4 dark:bg-warning-500/15 md:mx-6 md:mb-6">
                        <p class="text-sm text-warning-700 dark:text-warning-400">
                            Votre adresse e-mail n'est pas encore vérifiée. Certaines fonctionnalités restent bloquées jusqu'à la vérification.
                        </p>

                        <form novalidate method="POST" action="{{ route('verification.send') }}" class="mt-2">
                            @csrf
                            <button type="submit" class="text-sm font-semibold text-warning-700 underline hover:no-underline dark:text-warning-400">
                                Renvoyer l'e-mail de vérification
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            {{-- Mot de passe --}}
            <div class="{{ $cardClass }}">
                <div class="{{ $sectionHeader }}">
                    <span class="{{ $sectionIcon }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="{{ $sectionTitle }}">Changer le mot de passe</h2>
                        <p class="{{ $sectionDesc }}">8 caractères minimum, différent de votre mot de passe actuel.</p>
                    </div>
                </div>

                <form novalidate method="POST" action="{{ route('password.update') }}" class="px-5 py-5 md:px-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="current_password" class="{{ $labelClass }}">Mot de passe actuel <span class="text-error-500">*</span></label>
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            autocomplete="current-password"
                            required
                            class="{{ $inputBase }} @if ($errors->updatePassword->has('current_password')) border-error-400 dark:border-error-500 @else border-gray-300 dark:border-gray-700 @endif"
                        />
                        @if ($errors->updatePassword->has('current_password'))
                            <p class="{{ $errorClass }}">{{ $errors->updatePassword->first('current_password') }}</p>
                        @endif
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="new_password" class="{{ $labelClass }}">Nouveau mot de passe <span class="text-error-500">*</span></label>
                            <input
                                type="password"
                                id="new_password"
                                name="password"
                                autocomplete="new-password"
                                required
                                class="{{ $inputBase }} @if ($errors->updatePassword->has('password')) border-error-400 dark:border-error-500 @else border-gray-300 dark:border-gray-700 @endif"
                            />
                            @if ($errors->updatePassword->has('password'))
                                <p class="{{ $errorClass }}">{{ $errors->updatePassword->first('password') }}</p>
                            @endif
                        </div>

                        <div>
                            <label for="password_confirmation" class="{{ $labelClass }}">Confirmer le nouveau mot de passe <span class="text-error-500">*</span></label>
                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                autocomplete="new-password"
                                required
                                class="{{ $inputBase }} @if ($errors->updatePassword->has('password_confirmation')) border-error-400 dark:border-error-500 @else border-gray-300 dark:border-gray-700 @endif"
                            />
                            @if ($errors->updatePassword->has('password_confirmation'))
                                <p class="{{ $errorClass }}">{{ $errors->updatePassword->first('password_confirmation') }}</p>
                            @endif
                        </div>
                    </div>

                    <button type="submit" class="{{ $primaryButton }} mt-5">Mettre à jour le mot de passe</button>
                </form>
            </div>

            {{-- Suppression du compte --}}
            <div
                class="rounded-2xl border border-error-500/30 bg-white dark:border-error-500/30 dark:bg-white/[0.03]"
                x-data="{ deleteOpen: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }"
            >
                <div class="flex items-start gap-3 border-b border-error-500/20 px-5 py-4 dark:border-error-500/20 md:px-6">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-error-50 text-error-500 dark:bg-error-500/15">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 9v3.75m0 3h.008M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.29 2.25h17.78a1.5 1.5 0 0 0 1.29-2.25L13.71 3.86a1.5 1.5 0 0 0-2.58 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-medium text-error-600 dark:text-error-400">Supprimer mon compte</h2>
                        <p class="{{ $sectionDesc }}">Votre compte et vos préférences personnelles seront supprimés. Les données nécessaires au service public sont conservées de manière anonymisée.</p>
                    </div>
                </div>

                <div class="px-5 py-5 md:px-6">
                    <button
                        type="button"
                        @click="deleteOpen = true"
                        class="{{ $dangerButton }}"
                    >
                        Supprimer définitivement mon compte
                    </button>
                </div>

                <template x-teleport="body">
                    <div
                        x-show="deleteOpen"
                        x-cloak
                        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="delete-account-title"
                        @keydown.escape.window="deleteOpen = false"
                    >
                        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="deleteOpen = false"></div>

                        <div class="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xl dark:border-gray-800 dark:bg-gray-dark">
                            <button
                                type="button"
                                @click="deleteOpen = false"
                                class="absolute right-4 top-4 flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
                                aria-label="Fermer"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6 18 18 6M6 6l12 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>

                            <div class="flex items-start gap-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-error-50 text-error-500 dark:bg-error-500/15">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 9v3.75m0 3h.008M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.29 2.25h17.78a1.5 1.5 0 0 0 1.29-2.25L13.71 3.86a1.5 1.5 0 0 0-2.58 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <div>
                                    <h2 id="delete-account-title" class="text-lg font-semibold text-gray-800 dark:text-white/90">Supprimer définitivement votre compte ?</h2>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        Cette action est irréversible. Saisissez votre mot de passe pour confirmer.
                                    </p>
                                </div>
                            </div>

                            @if ($errors->userDeletion->has('password'))
                                <p class="mt-4 rounded-lg bg-error-50 p-3 text-sm text-error-600 dark:bg-error-500/15 dark:text-error-400">
                                    {{ $errors->userDeletion->first('password') }}
                                </p>
                            @endif

                            <form novalidate method="POST" action="{{ route('profile.destroy') }}" class="mt-5">
                                @csrf
                                @method('DELETE')

                                <div>
                                    <label for="delete_password" class="{{ $labelClass }}">Mot de passe</label>
                                    <input
                                        type="password"
                                        id="delete_password"
                                        name="password"
                                        autocomplete="current-password"
                                        required
                                        class="{{ $inputBase }} @if ($errors->userDeletion->has('password')) border-error-400 dark:border-error-500 @else border-gray-300 dark:border-gray-700 @endif"
                                    />
                                </div>

                                <div class="mt-6 flex justify-end gap-3">
                                    <button type="button" @click="deleteOpen = false" class="{{ $secondaryButton }}">
                                        Annuler
                                    </button>

                                    <button type="submit" class="{{ $dangerButton }}">
                                        Supprimer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Colonne latérale --}}
        <aside class="order-first space-y-6 xl:order-none">
            {{-- Identité --}}
            <div class="{{ $cardClass }} overflow-hidden">
                <div class="relative h-24 overflow-hidden bg-brand-500">
                    <span class="pointer-events-none absolute -right-6 -top-8 opacity-10">
                        <svg width="150" height="150" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2.5c-3.6 4.2-6.5 7.8-6.5 11.2a6.5 6.5 0 0 0 13 0c0-3.4-2.9-7-6.5-11.2Z" />
                        </svg>
                    </span>
                    <span class="pointer-events-none absolute -bottom-12 -left-8 h-24 w-24 rounded-full border-[14px] border-white/10"></span>
                </div>

                <div class="px-5 pb-5">
                    <div class="relative -mt-10 flex items-end justify-between gap-3">
                        <div class="rounded-full border-4 border-white bg-white shadow-theme-xs dark:border-gray-800 dark:bg-gray-800">
                            <x-user-avatar
                                :user="$user"
                                size="h-20 w-20"
                                text-size="text-2xl"
                                fallback-class="bg-white text-brand-500"
                            />
                        </div>

                        <span class="mb-1 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $user->role->badgeClass() }}">
                            {{ $user->role->label() }}
                        </span>
                    </div>

                    <h2 class="mt-4 text-lg font-semibold text-gray-800 dark:text-white/90">{{ $user->name }}</h2>
                    <p class="mt-0.5 truncate text-sm text-gray-500 dark:text-gray-400" title="{{ $user->email }}">{{ $user->email }}</p>

                    <dl class="mt-5 space-y-3 border-t border-gray-100 pt-4 text-sm dark:border-gray-800">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Adresse e-mail</dt>
                            <dd>
                                @if ($user->hasVerifiedEmail())
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                                        Vérifiée
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-warning-500"></span>
                                        À vérifier
                                    </span>
                                @endif
                            </dd>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">Membre depuis</dt>
                            <dd class="font-medium text-gray-800 dark:text-white/90">{{ $user->created_at?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Photo de profil --}}
            <div class="{{ $cardClass }}">
                <div class="{{ $sectionHeader }}">
                    <span class="{{ $sectionIcon }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 19.5h16.5A1.5 1.5 0 0 0 21.75 18V6A1.5 1.5 0 0 0 20.25 4.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="{{ $sectionTitle }}">Photo de profil</h2>
                        <p class="{{ $sectionDesc }}">JPEG, PNG ou WebP — 2 Mo maximum.</p>
                    </div>
                </div>

                <form novalidate method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" class="space-y-3 px-5 py-5 md:px-6">
                    @csrf

                    <div class="rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-5 transition hover:border-brand-400 hover:bg-brand-25 dark:border-gray-700 dark:bg-gray-800/40 dark:hover:border-brand-500/60 dark:hover:bg-brand-500/5">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-brand-500 shadow-theme-xs dark:bg-gray-800">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 7.5 7.5 12m4.5-4.5V21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                    {{ $user->avatar_path ? 'Remplacer votre photo' : 'Ajouter une photo' }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Une image carrée rend le meilleur résultat.</p>
                            </div>
                        </div>

                        <input
                            type="file"
                            id="photo"
                            name="photo"
                            accept="image/jpeg,image/png,image/webp"
                            class="mt-3 block w-full cursor-pointer text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-500 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-600 dark:text-gray-400"
                        />
                    </div>

                    @error('photo')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror

                    <button type="submit" class="{{ $primaryButton }} w-full">Enregistrer la photo</button>
                </form>

                @if ($user->avatar_path)
                    <form novalidate
                        method="POST"
                        action="{{ route('profile.photo.destroy') }}"
                        onsubmit="return confirm('Retirer votre photo de profil ?')"
                        class="border-t border-gray-100 px-5 py-3.5 dark:border-gray-800 md:px-6"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-2 text-sm font-medium text-error-600 transition hover:text-error-700 dark:text-error-400">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Retirer la photo
                        </button>
                    </form>
                @endif
            </div>

            {{-- Sécurité --}}
            <div class="{{ $cardClass }}">
                <div class="{{ $sectionHeader }}">
                    <span class="{{ $sectionIcon }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.96 11.96 0 0 1 3.6 6c-.18.6-.27 1.22-.27 1.86 0 5.1 3.24 9.44 7.79 11.06a12.06 12.06 0 0 0 7.61-11.06c0-.64-.09-1.26-.27-1.86A11.96 11.96 0 0 1 12 2.714Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="{{ $sectionTitle }}">Sécurité</h2>
                        <p class="{{ $sectionDesc }}">Authentification à deux facteurs.</p>
                    </div>
                </div>

                <div class="px-5 py-5 md:px-6">
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-4 py-3 dark:bg-gray-800/60">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Statut</span>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $user->hasEnabledTwoFactorAuthentication() ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $user->hasEnabledTwoFactorAuthentication() ? 'bg-success-500' : 'bg-gray-400' }}"></span>
                            {{ $user->hasEnabledTwoFactorAuthentication() ? 'Activée' : 'Désactivée' }}
                        </span>
                    </div>

                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        Protégez votre compte avec un code généré par une application d'authentification.
                    </p>

                    <a href="{{ route('two-factor.show') }}" class="{{ $secondaryButton }} mt-4 w-full">
                        {{ $user->hasEnabledTwoFactorAuthentication() ? 'Gérer la double authentification' : 'Activer la double authentification' }}
                    </a>
                </div>
            </div>
        </aside>
    </div>
@endsection
