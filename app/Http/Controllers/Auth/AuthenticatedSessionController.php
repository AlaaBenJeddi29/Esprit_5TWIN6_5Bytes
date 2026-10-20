<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\RoleRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->session()->forget([
            'two-factor.login.id',
            'two-factor.login.remember',
            'two-factor.login.started_at',
        ]);

        $user = $request->authenticate();

        // Aucune session authentifiée n'est ouverte tant que le second
        // facteur n'a pas été validé.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->regenerate();
            $request->session()->put([
                'two-factor.login.id' => $user->id,
                'two-factor.login.remember' => $request->boolean('remember'),
                'two-factor.login.started_at' => now()->timestamp,
            ]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        // Redirection selon le rôle, en s'assurant que l'URL mémorisée par
        // redirect()->intended() ne dépose pas l'utilisateur dans un espace
        // auquel son rôle ne donne pas accès.
        return redirect()->to(RoleRedirector::for($request, $user));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
