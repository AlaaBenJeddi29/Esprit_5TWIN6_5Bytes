<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint l'accès aux utilisateurs dont le rôle (lu côté serveur) fait
 * partie des rôles autorisés. Le rôle provient toujours de l'utilisateur
 * authentifié, jamais d'une valeur transmise par le navigateur.
 *
 * Usage : ->middleware('role:gestionnaire,admin') ou ->middleware('role:admin')
 */
class EnsureUserRole
{
    /**
     * @param  string  ...$roles  Valeurs de App\Enums\UserRole autorisées.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role?->value, $roles, true)) {
            abort(403, "Accès non autorisé à cet espace.");
        }

        return $next($request);
    }
}
