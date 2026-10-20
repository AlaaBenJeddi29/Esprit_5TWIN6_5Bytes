@extends('front.layouts.app')

@section('title', 'AquaSecure — Mon profil')

@section('content')
    @include('front.partials.page-hero', [
        'eyebrow' => 'Mon compte',
        'heading' => 'Mon profil',
        'lead' => 'Gérez vos informations personnelles, votre photo, votre mot de passe et la sécurité de votre compte.',
    ])

    @php
        $inputBase = 'w-full rounded-md border bg-transparent px-4 py-3 text-sm text-dark outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/10 dark:text-white';
        $labelClass = 'mb-2 block text-sm font-semibold text-dark dark:text-white';
        $errorClass = 'mt-1.5 text-xs font-medium text-red-500';
        $cardClass = 'rounded-md bg-white shadow-1 dark:bg-dark-2';
        $sectionHeader = 'flex items-start gap-4 border-b border-stroke px-6 py-5 dark:border-dark-3';
        $sectionIcon = 'flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary dark:bg-primary/15';
        $sectionTitle = 'text-lg font-bold text-dark dark:text-white';
        $sectionDesc = 'mt-1 text-sm text-body-color dark:text-dark-6';
        $primaryButton = 'inline-flex items-center justify-center gap-2 rounded-md bg-primary px-5 py-3 text-sm font-medium text-white transition hover:bg-blue-dark';
        $secondaryButton = 'inline-flex items-center justify-center gap-2 rounded-md border border-stroke bg-white px-5 py-3 text-sm font-medium text-dark transition hover:border-primary hover:text-primary dark:border-dark-3 dark:bg-dark-2 dark:text-white';
        $dangerButton = 'inline-flex items-center justify-center gap-2 rounded-md bg-red-500 px-5 py-3 text-sm font-medium text-white transition hover:bg-red-600';
    @endphp

    <section class="pb-12 pt-10 dark:bg-dark lg:pb-20 lg:pt-14">
        <div class="container px-4 mx-auto">
            @if (session('email_changed'))
                <div class="mb-6 flex items-start gap-3 rounded-md border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-500/30 dark:bg-yellow-500/10" role="alert">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-yellow-600 dark:text-yellow-400" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 9v3.75m0 3h.008M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.29 2.25h17.78a1.5 1.5 0 0 0 1.29-2.25L13.71 3.86a1.5 1.5 0 0 0-2.58 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <p class="text-sm text-yellow-700 dark:text-yellow-400">
                        Votre adresse e-mail a changé. Vérifiez-la pour retrouver l'accès complet à votre espace.
                    </p>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                {{-- Colonne identité --}}
                <aside class="space-y-6 lg:col-span-4">
                    <div class="{{ $cardClass }} overflow-hidden">
                        <div class="relative h-24 overflow-hidden bg-primary">
                            <span class="pointer-events-none absolute -right-6 -top-8 opacity-10">
                                <svg width="150" height="150" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2.5c-3.6 4.2-6.5 7.8-6.5 11.2a6.5 6.5 0 0 0 13 0c0-3.4-2.9-7-6.5-11.2Z" />
                                </svg>
                            </span>
                            <span class="pointer-events-none absolute -bottom-12 -left-8 h-24 w-24 rounded-full border-[14px] border-white/10"></span>
                        </div>

                        <div class="px-6 pb-6">
                            <div class="relative -mt-12 flex items-end justify-between gap-3">
                                <div class="rounded-full border-4 border-white bg-white shadow-2 dark:border-dark-2 dark:bg-dark-2">
                                    <x-user-avatar
                                        :user="$user"
                                        size="h-20 w-20"
                                        text-size="text-2xl"
                                        fallback-class="bg-white text-primary"
                                    />
                                </div>

                                <span class="mb-1 inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary dark:bg-primary/15">
                                    {{ $user->role?->label() }}
                                </span>
                            </div>

                            <h2 class="mt-4 text-xl font-bold text-dark dark:text-white">{{ $user->name }}</h2>
                            <p class="mt-1 truncate text-sm text-body-color dark:text-dark-6" title="{{ $user->email }}">{{ $user->email }}</p>

                            <dl class="mt-6 space-y-3 border-t border-stroke pt-5 text-sm dark:border-dark-3">
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-body-color dark:text-dark-6">Adresse e-mail</dt>
                                    <dd>
                                        @if ($user->hasVerifiedEmail())
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary/10 px-2.5 py-1 text-xs font-semibold text-secondary">
                                                <span class="h-1.5 w-1.5 rounded-full bg-secondary"></span>
                                                Vérifiée
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-400">
                                                <span class="h-1.5 w-1.5 rounded-full bg-yellow-500"></span>
                                                À vérifier
                                            </span>
                                        @endif
                                    </dd>
                                </div>

                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-body-color dark:text-dark-6">Membre depuis</dt>
                                    <dd class="font-medium text-dark dark:text-white">{{ $user->created_at?->format('d/m/Y') ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    {{-- Sécurité du compte --}}
                    <div class="{{ $cardClass }} p-6">
                        <div class="flex items-center gap-3">
                            <span class="{{ $sectionIcon }}">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.96 11.96 0 0 1 3.6 6c-.18.6-.27 1.22-.27 1.86 0 5.1 3.24 9.44 7.79 11.06a12.06 12.06 0 0 0 7.61-11.06c0-.64-.09-1.26-.27-1.86A11.96 11.96 0 0 1 12 2.714Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-base font-bold text-dark dark:text-white">Sécurité du compte</h2>
                                <p class="text-xs text-body-color dark:text-dark-6">Authentification à deux facteurs</p>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3 rounded-md bg-gray-1 px-4 py-3 dark:bg-dark-3">
                            <span class="text-sm font-medium text-dark dark:text-white">Double authentification</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->hasEnabledTwoFactorAuthentication() ? 'bg-secondary/10 text-secondary' : 'bg-gray-2 text-body-color dark:bg-dark-4 dark:text-dark-7' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $user->hasEnabledTwoFactorAuthentication() ? 'bg-secondary' : 'bg-dark-6' }}"></span>
                                {{ $user->hasEnabledTwoFactorAuthentication() ? 'Activée' : 'Désactivée' }}
                            </span>
                        </div>

                        <p class="mt-3 text-sm text-body-color dark:text-dark-6">
                            Ajoutez une seconde vérification à la connexion avec une application d'authentification.
                        </p>

                        <a href="{{ route('two-factor.show') }}" class="{{ $secondaryButton }} mt-4 w-full">
                            {{ $user->hasEnabledTwoFactorAuthentication() ? 'Gérer la double authentification' : 'Activer la double authentification' }}
                        </a>
                    </div>
                </aside>

                {{-- Colonne principale --}}
                <div class="space-y-6 lg:col-span-8">
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

                        <form novalidate method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" class="space-y-4 px-6 py-6">
                            @csrf

                            <div class="rounded-md border-2 border-dashed border-stroke bg-gray-1 px-5 py-6 transition hover:border-primary hover:bg-primary/5 dark:border-dark-3 dark:bg-dark-3/30 dark:hover:border-primary/60">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-primary shadow-1 dark:bg-dark-2">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 7.5 7.5 12m4.5-4.5V21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-dark dark:text-white">
                                            {{ $user->avatar_path ? 'Remplacer votre photo' : 'Ajouter une photo' }}
                                        </p>
                                        <p class="text-xs text-body-color dark:text-dark-6">Une image carrée rend le meilleur résultat.</p>
                                    </div>
                                </div>

                                <input
                                    type="file"
                                    id="photo"
                                    name="photo"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="mt-4 block w-full cursor-pointer text-sm text-body-color file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-white hover:file:bg-blue-dark dark:text-dark-6"
                                />
                            </div>

                            @error('photo')
                                <p class="{{ $errorClass }}">{{ $message }}</p>
                            @enderror

                            <div class="flex flex-wrap items-center gap-3">
                                <button type="submit" class="{{ $primaryButton }}">Enregistrer la photo</button>
                            </div>
                        </form>

                        @if ($user->avatar_path)
                            <form novalidate
                                method="POST"
                                action="{{ route('profile.photo.destroy') }}"
                                onsubmit="return confirm('Retirer votre photo de profil ?')"
                                class="border-t border-stroke px-6 py-4 dark:border-dark-3"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-2 text-sm font-medium text-red-500 transition hover:text-red-600">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    Retirer la photo
                                </button>
                            </form>
                        @endif
                    </div>

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
                                <p class="{{ $sectionDesc }}">Votre rôle et le statut de votre compte sont gérés par les administrateurs et ne peuvent pas être modifiés ici.</p>
                            </div>
                        </div>

                        <form novalidate method="POST" action="{{ route('profile.update') }}" class="space-y-5 px-6 py-6">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label for="name" class="{{ $labelClass }}">Nom complet <span class="text-red-500">*</span></label>
                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    value="{{ old('name', $user->name) }}"
                                    autocomplete="name"
                                    required
                                    class="{{ $inputBase }} @error('name') border-red-500 @else border-stroke dark:border-dark-3 @enderror"
                                />
                                @error('name')
                                    <p class="{{ $errorClass }}">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="email" class="{{ $labelClass }}">Adresse e-mail <span class="text-red-500">*</span></label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="{{ old('email', $user->email) }}"
                                    autocomplete="email"
                                    required
                                    class="{{ $inputBase }} @error('email') border-red-500 @else border-stroke dark:border-dark-3 @enderror"
                                />
                                @error('email')
                                    <p class="{{ $errorClass }}">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-body-color dark:text-dark-6">
                                    Toute modification de l'adresse e-mail nécessite une nouvelle vérification.
                                </p>
                            </div>

                            <button type="submit" class="{{ $primaryButton }}">Enregistrer les modifications</button>
                        </form>

                        @if (! $user->hasVerifiedEmail())
                            <div class="mx-6 mb-6 rounded-md border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-500/30 dark:bg-yellow-500/10">
                                <p class="text-sm text-yellow-700 dark:text-yellow-400">
                                    Votre adresse e-mail n'est pas encore vérifiée. Certaines fonctionnalités restent bloquées jusqu'à la vérification.
                                </p>

                                <form novalidate method="POST" action="{{ route('verification.send') }}" class="mt-2">
                                    @csrf
                                    <button type="submit" class="text-sm font-semibold text-yellow-700 underline hover:no-underline dark:text-yellow-400">
                                        Renvoyer l'e-mail de vérification
                                    </button>
                                </form>

                                @if (session('status') === 'verification-link-sent')
                                    <p class="mt-2 text-sm font-medium text-green-600 dark:text-secondary">
                                        Un nouvel e-mail de vérification vient d'être envoyé.
                                    </p>
                                @endif
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
                                <p class="{{ $sectionDesc }}">Utilisez un mot de passe d'au moins 8 caractères, différent de votre mot de passe actuel.</p>
                            </div>
                        </div>

                        <form novalidate method="POST" action="{{ route('password.update') }}" class="space-y-5 px-6 py-6">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="current_password" class="{{ $labelClass }}">Mot de passe actuel <span class="text-red-500">*</span></label>
                                <input
                                    type="password"
                                    id="current_password"
                                    name="current_password"
                                    autocomplete="current-password"
                                    required
                                    class="{{ $inputBase }} @if ($errors->updatePassword->has('current_password')) border-red-500 @else border-stroke dark:border-dark-3 @endif"
                                />
                                @if ($errors->updatePassword->has('current_password'))
                                    <p class="{{ $errorClass }}">{{ $errors->updatePassword->first('current_password') }}</p>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div>
                                    <label for="new_password" class="{{ $labelClass }}">Nouveau mot de passe <span class="text-red-500">*</span></label>
                                    <input
                                        type="password"
                                        id="new_password"
                                        name="password"
                                        autocomplete="new-password"
                                        required
                                        class="{{ $inputBase }} @if ($errors->updatePassword->has('password')) border-red-500 @else border-stroke dark:border-dark-3 @endif"
                                    />
                                    @if ($errors->updatePassword->has('password'))
                                        <p class="{{ $errorClass }}">{{ $errors->updatePassword->first('password') }}</p>
                                    @endif
                                </div>

                                <div>
                                    <label for="password_confirmation" class="{{ $labelClass }}">Confirmer le nouveau mot de passe <span class="text-red-500">*</span></label>
                                    <input
                                        type="password"
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        autocomplete="new-password"
                                        required
                                        class="{{ $inputBase }} @if ($errors->updatePassword->has('password_confirmation')) border-red-500 @else border-stroke dark:border-dark-3 @endif"
                                    />
                                    @if ($errors->updatePassword->has('password_confirmation'))
                                        <p class="{{ $errorClass }}">{{ $errors->updatePassword->first('password_confirmation') }}</p>
                                    @endif
                                </div>
                            </div>

                            <button type="submit" class="{{ $primaryButton }}">Mettre à jour le mot de passe</button>
                        </form>
                    </div>

                    {{-- Suppression du compte --}}
                    <div class="rounded-md border border-red-200 bg-white shadow-1 dark:border-red-500/30 dark:bg-dark-2">
                        <div class="flex items-start gap-4 border-b border-red-100 px-6 py-5 dark:border-red-500/20">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-red-50 text-red-500 dark:bg-red-500/10">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 9v3.75m0 3h.008M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.29 2.25h17.78a1.5 1.5 0 0 0 1.29-2.25L13.71 3.86a1.5 1.5 0 0 0-2.58 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-lg font-bold text-red-600 dark:text-red-400">Supprimer mon compte</h2>
                                <p class="{{ $sectionDesc }}">Votre compte et vos préférences personnelles seront supprimés. Vos signalements et l'historique nécessaires au service public sont conservés de manière anonymisée.</p>
                            </div>
                        </div>

                        <details class="group px-6 py-5">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-red-600 dark:text-red-400">
                                Je souhaite supprimer mon compte
                                <svg class="h-5 w-5 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="m19.5 8.25-7.5 7.5-7.5-7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </summary>

                            <div class="mt-4 border-t border-red-100 pt-5 dark:border-red-500/20">
                                @if ($errors->userDeletion->isNotEmpty())
                                    <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-500/10 dark:text-red-400" role="alert">
                                        {{ $errors->userDeletion->first('password') }}
                                    </div>
                                @endif

                                <form novalidate
                                    method="POST"
                                    action="{{ route('profile.destroy') }}"
                                    onsubmit="return confirm('Cette action est définitive. Confirmer la suppression de votre compte ?')"
                                    class="space-y-4"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <div>
                                        <label for="delete_password" class="{{ $labelClass }}">
                                            Confirmez avec votre mot de passe <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="password"
                                            id="delete_password"
                                            name="password"
                                            autocomplete="current-password"
                                            required
                                            class="{{ $inputBase }} @if ($errors->userDeletion->has('password')) border-red-500 @else border-stroke dark:border-dark-3 @endif"
                                        />
                                    </div>

                                    <button type="submit" class="{{ $dangerButton }}">
                                        Supprimer définitivement mon compte
                                    </button>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
