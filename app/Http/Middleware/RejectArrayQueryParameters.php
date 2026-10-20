<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Les listes et filtres n'acceptent que des paramètres scalaires. Un tableau
 * transmis dans l'URL (?search[]=...) est refusé avant tout cast PHP.
 */
class RejectArrayQueryParameters
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->query() as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if ($request->expectsJson()) {
                throw ValidationException::withMessages([
                    $key => 'Ce paramètre de requête est invalide.',
                ]);
            }

            abort(400, 'Paramètre de requête invalide.');
        }

        return $next($request);
    }
}
