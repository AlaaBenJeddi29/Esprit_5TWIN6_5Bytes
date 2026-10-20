<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class TwoFactorSettingsController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('auth.two-factor-settings', [
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'recoveryCodesCount' => count((array) ($user->two_factor_recovery_codes ?? [])),
            'recoveryCodes' => $this->flashedRecoveryCodes($request),
        ]);
    }

    /**
     * Démarre une configuration : le secret reste en session tant que
     * l'utilisateur n'a pas prouvé qu'il maîtrise l'application TOTP.
     */
    public function setup(Request $request): RedirectResponse
    {
        $this->validatePassword($request);

        if ($request->user()->hasEnabledTwoFactorAuthentication()) {
            return redirect()
                ->route('two-factor.show')
                ->with('error', "L'authentification à deux facteurs est déjà activée.");
        }

        // Le secret en attente est chiffré dans la session, comme il le sera
        // en base une fois la configuration confirmée.
        $request->session()->put([
            'two-factor.setup.secret' => Crypt::encryptString($this->twoFactor->generateSecret()),
            'two-factor.setup.started_at' => now()->timestamp,
        ]);

        return redirect()->route('two-factor.setup.show');
    }

    public function showSetup(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasEnabledTwoFactorAuthentication()) {
            $this->forgetSetup($request);

            return redirect()
                ->route('two-factor.show')
                ->with('error', "L'authentification à deux facteurs est déjà activée.");
        }

        $secret = $this->pendingSetupSecret($request);

        if ($secret === null) {
            return redirect()
                ->route('two-factor.show')
                ->with('error', 'La configuration a expiré. Recommencez pour générer un nouveau QR code.');
        }

        return view('auth.two-factor-setup', [
            'qrCodeSvg' => $this->twoFactor->qrCodeSvg($request->user(), $secret),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        if ($request->user()->hasEnabledTwoFactorAuthentication()) {
            $this->forgetSetup($request);

            return redirect()
                ->route('two-factor.show')
                ->with('error', "L'authentification à deux facteurs est déjà activée.");
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $secret = $this->pendingSetupSecret($request);

        if ($secret === null) {
            return redirect()
                ->route('two-factor.show')
                ->with('error', 'La configuration a expiré. Recommencez pour générer un nouveau QR code.');
        }

        if (! $this->twoFactor->verifyTotp($secret, (string) $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => "Ce code est invalide. Vérifiez l'heure de votre appareil puis réessayez.",
            ]);
        }

        $codes = $this->twoFactor->enable($request->user(), $secret);
        $this->forgetSetup($request);

        return redirect()
            ->route('two-factor.show')
            ->with('two-factor.recovery-codes-encrypted', $this->encryptRecoveryCodes($codes))
            ->with('success', "L'authentification à deux facteurs est maintenant activée.");
    }

    public function cancelSetup(Request $request): RedirectResponse
    {
        $this->forgetSetup($request);

        return redirect()
            ->route('two-factor.show')
            ->with('status', 'Configuration annulée.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->validatePassword($request);

        if (! $request->user()->hasEnabledTwoFactorAuthentication()) {
            return redirect()
                ->route('two-factor.show')
                ->with('error', "L'authentification à deux facteurs n'est pas activée.");
        }

        $this->twoFactor->disable($request->user());

        return redirect()
            ->route('two-factor.show')
            ->with('success', "L'authentification à deux facteurs a été désactivée.");
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $this->validatePassword($request);

        if (! $request->user()->hasEnabledTwoFactorAuthentication()) {
            return redirect()
                ->route('two-factor.show')
                ->with('error', "Activez d'abord l'authentification à deux facteurs.");
        }

        $codes = $this->twoFactor->regenerateRecoveryCodes($request->user());

        return redirect()
            ->route('two-factor.show')
            ->with('two-factor.recovery-codes-encrypted', $this->encryptRecoveryCodes($codes))
            ->with('success', 'De nouveaux codes de secours ont été générés. Les anciens sont désormais invalides.');
    }

    private function validatePassword(Request $request): void
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);
    }

    private function pendingSetupSecret(Request $request): ?string
    {
        $encryptedSecret = $request->session()->get('two-factor.setup.secret');
        $startedAt = (int) $request->session()->get('two-factor.setup.started_at', 0);

        if (! is_string($encryptedSecret)
            || $startedAt <= 0
            || $startedAt < now()->subSeconds(TwoFactorAuthenticationService::PENDING_SETUP_TTL)->timestamp) {
            $this->forgetSetup($request);

            return null;
        }

        try {
            $secret = Crypt::decryptString($encryptedSecret);
        } catch (Throwable) {
            $this->forgetSetup($request);

            return null;
        }

        return $secret !== '' ? $secret : null;
    }

    /**
     * @param  list<string>  $codes
     */
    private function encryptRecoveryCodes(array $codes): string
    {
        return Crypt::encryptString((string) json_encode($codes));
    }

    /**
     * @return list<string>|null
     */
    private function flashedRecoveryCodes(Request $request): ?array
    {
        $encrypted = $request->session()->get('two-factor.recovery-codes-encrypted');

        if (! is_string($encrypted)) {
            return null;
        }

        try {
            $codes = json_decode(Crypt::decryptString($encrypted), true);
        } catch (Throwable) {
            return null;
        }

        return is_array($codes) ? array_values($codes) : null;
    }

    private function forgetSetup(Request $request): void
    {
        $request->session()->forget([
            'two-factor.setup.secret',
            'two-factor.setup.started_at',
        ]);
    }
}
