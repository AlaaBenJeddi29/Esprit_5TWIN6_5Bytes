<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Authentification à deux facteurs
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-700" role="status">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-600" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @if (session('status'))
                <div class="rounded-md border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-700" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if (is_array($recoveryCodes) && $recoveryCodes !== [])
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-6">
                    <h3 class="text-lg font-semibold text-amber-900">Vos codes de secours</h3>
                    <p class="mt-1 text-sm text-amber-800">
                        Conservez ces codes dans un endroit sûr. Ils ne seront plus affichés et chaque code ne peut être utilisé qu’une seule fois.
                    </p>

                    <ul class="mt-4 grid grid-cols-1 gap-2 font-mono text-sm text-amber-900 sm:grid-cols-2">
                        @foreach ($recoveryCodes as $code)
                            <li class="rounded border border-amber-200 bg-white px-3 py-2">{{ $code }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-3xl">
                    <header class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                État de la protection
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Ajoutez un second facteur à votre connexion pour protéger votre compte même si votre mot de passe est compromis.
                            </p>
                        </div>

                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $enabled ? 'Activée' : 'Désactivée' }}
                        </span>
                    </header>

                    @if (! $enabled)
                        <form novalidate method="POST" action="{{ route('two-factor.setup') }}" class="mt-6 space-y-6">
                            @csrf

                            <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                                Vous serez invité à scanner un QR code avec une application d’authentification (Google Authenticator, Microsoft Authenticator, 1Password, etc.), puis à confirmer avec un code à six chiffres.
                            </div>

                            <div>
                                <x-input-label for="setup_current_password" value="Mot de passe actuel" />
                                <x-text-input id="setup_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                                <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                            </div>

                            <x-primary-button>Configurer l’authentification à deux facteurs</x-primary-button>
                        </form>
                    @else
                        <div class="mt-6 space-y-8">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">Codes de secours</h4>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ $recoveryCodesCount }} code(s) de secours restant(s). Ils permettent de vous connecter si vous n’avez plus accès à votre application d’authentification.
                                </p>

                                <form novalidate method="POST" action="{{ route('two-factor.recovery-codes') }}" class="mt-4 space-y-4">
                                    @csrf

                                    <div>
                                        <x-input-label for="recovery_current_password" value="Mot de passe actuel" />
                                        <x-text-input id="recovery_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                                        <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                                    </div>

                                    <x-secondary-button type="submit">Régénérer les codes de secours</x-secondary-button>
                                </form>
                            </div>

                            <div class="border-t border-gray-200 pt-6">
                                <h4 class="text-sm font-semibold text-gray-900">Désactiver la protection</h4>
                                <p class="mt-1 text-sm text-gray-600">
                                    La désactivation supprime immédiatement le secret et tous les codes de secours. Confirmez avec votre mot de passe.
                                </p>

                                <form novalidate method="POST" action="{{ route('two-factor.disable') }}" class="mt-4 space-y-4" onsubmit="return confirm('Désactiver l’authentification à deux facteurs ?');">
                                    @csrf

                                    <div>
                                        <x-input-label for="disable_current_password" value="Mot de passe actuel" />
                                        <x-text-input id="disable_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                                        <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                                    </div>

                                    <x-danger-button>Désactiver l’authentification à deux facteurs</x-danger-button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <p class="mt-6 text-sm">
                        <a href="{{ route('profile.edit') }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                            Retour au profil
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
