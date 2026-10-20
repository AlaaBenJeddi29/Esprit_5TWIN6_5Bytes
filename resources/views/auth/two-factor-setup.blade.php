<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Configurer l’authentification à deux facteurs
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-3xl">
                    @if (session('error'))
                        <div class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-600" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

                    <header>
                        <h3 class="text-lg font-medium text-gray-900">Scanner le QR code</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Ouvrez votre application d’authentification, ajoutez un compte et scannez le code ci-dessous.
                        </p>
                    </header>

                    <div class="mt-6 flex justify-center">
                        <div class="rounded-lg border border-gray-200 bg-white p-4">
                            {!! $qrCodeSvg !!}
                        </div>
                    </div>

                    <div class="mt-6 space-y-2 text-sm text-gray-600">
                        <p>1. Scannez le QR code avec Google Authenticator, Microsoft Authenticator, 1Password ou une application TOTP compatible.</p>
                        <p>2. Saisissez le code à six chiffres affiché par l’application pour confirmer la configuration.</p>
                        <p class="font-medium text-gray-900">La protection ne sera activée qu’après validation d’un code correct.</p>
                    </div>

                    <form novalidate method="POST" action="{{ route('two-factor.confirm') }}" class="mt-6 space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="code" value="Code à six chiffres" />
                            <x-text-input
                                id="code"
                                name="code"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                autocomplete="one-time-code"
                                autofocus
                                class="mt-1 block w-full text-center text-xl tracking-widest"
                            />
                            <x-input-error :messages="$errors->get('code')" class="mt-2" />
                        </div>

                        <div class="flex flex-wrap items-center gap-4">
                            <x-primary-button>Activer l’authentification à deux facteurs</x-primary-button>
                        </div>
                    </form>

                    <form novalidate method="POST" action="{{ route('two-factor.setup.cancel') }}" class="mt-6">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                            Annuler la configuration
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
