<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use App\Support\RoleRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const ATTEMPT_DECAY_SECONDS = 60;

    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        $user = $this->pendingUser($request);

        if ($user === null) {
            $this->clearPending($request);

            return $this->expiredResponse();
        }

        return view('auth.two-factor-challenge', [
            'email' => $user->email,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        $user = $this->pendingUser($request);

        if ($user === null) {
            $this->clearPending($request);

            return $this->expiredResponse();
        }

        $this->ensureIsNotRateLimited($request, $user);

        $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
        ]);

        $usesRecoveryCode = $request->filled('recovery_code');
        $code = (string) ($usesRecoveryCode
            ? $request->input('recovery_code')
            : $request->input('code'));

        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => 'Saisissez un code à six chiffres ou un code de secours.',
            ]);
        }

        $valid = $usesRecoveryCode
            ? $this->twoFactor->verifyRecoveryCode($user, $code)
            : $this->twoFactor->verifyTotp($user->two_factor_secret, $code);

        if (! $valid) {
            RateLimiter::hit($this->throttleKey($request, $user), self::ATTEMPT_DECAY_SECONDS);

            throw ValidationException::withMessages([
                $usesRecoveryCode ? 'recovery_code' : 'code' => $usesRecoveryCode
                    ? 'Ce code de secours est invalide ou a déjà été utilisé.'
                    : 'Ce code est invalide ou a expiré.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request, $user));

        $remember = (bool) $request->session()->pull('two-factor.login.remember', false);
        $this->clearPending($request);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $response = redirect()->to(RoleRedirector::for($request, $user));

        if ($usesRecoveryCode) {
            $response->with('success', 'Code de secours accepté. Il ne peut plus être réutilisé.');
        }

        return $response;
    }

    /**
     * Annule une connexion en attente de second facteur.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        $this->clearPending($request);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Connexion annulée.');
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('two-factor.login.id');
        $startedAt = (int) $request->session()->get('two-factor.login.started_at', 0);

        if ($userId === null || $startedAt <= 0) {
            return null;
        }

        if ($startedAt < now()->subSeconds(TwoFactorAuthenticationService::PENDING_LOGIN_TTL)->timestamp) {
            return null;
        }

        $user = User::query()->find((int) $userId);

        if ($user === null
            || ! $user->is_active
            || ! $user->hasEnabledTwoFactorAuthentication()) {
            return null;
        }

        return $user;
    }

    private function clearPending(Request $request): void
    {
        $request->session()->forget([
            'two-factor.login.id',
            'two-factor.login.remember',
            'two-factor.login.started_at',
        ]);
    }

    private function ensureIsNotRateLimited(Request $request, User $user): void
    {
        $key = $this->throttleKey($request, $user);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        // La session en attente est invalidée : il faut ressaisir les
        // identifiants et le second facteur après la fenêtre de blocage.
        $this->clearPending($request);

        throw ValidationException::withMessages([
            'code' => "Trop de tentatives. Réessayez dans {$seconds} secondes.",
        ]);
    }

    private function throttleKey(Request $request, User $user): string
    {
        return 'two-factor-challenge:'.$user->getKey().'|'.$request->ip();
    }

    private function expiredResponse(): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->withErrors([
                'email' => 'Votre session de vérification a expiré. Veuillez vous reconnecter.',
            ]);
    }
}
