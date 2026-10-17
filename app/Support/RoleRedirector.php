<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Centralise la redirection post-authentification selon le rôle.
 *
 * Sécurité : le rôle est lu côté serveur depuis l'utilisateur authentifié.
 * L'URL mémorisée par `redirect()->intended()` (session `url.intended`) n'est
 * réutilisée que si elle appartient à un espace autorisé pour ce rôle ; sinon
 * l'utilisateur est renvoyé vers l'accueil de son rôle. Un citoyen ne peut donc
 * jamais être déposé dans le Back Office, ni un gestionnaire dans un espace
 * réservé aux administrateurs.
 */
class RoleRedirector
{
    /**
     * Accueil légitime pour un rôle donné.
     */
    public static function homeFor(User $user): string
    {
        return $user->canAccessBackOffice()
            ? route('back.dashboard', absolute: false)
            : route('front.home', absolute: false);
    }

    /**
     * Destination post-login : URL mémorisée si autorisée, sinon accueil du rôle.
     */
    public static function for(Request $request, User $user): string
    {
        $home = self::homeFor($user);
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return $home;
        }

        return self::allows($user, $intended) ? $intended : $home;
    }

    /**
     * Détermine si le rôle de l'utilisateur autorise l'accès au chemin cible.
     */
    public static function allows(User $user, string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        if ($user->isCitoyen()) {
            return ! str_starts_with($path, '/back');
        }

        if ($user->isGestionnaire()) {
            return ! str_starts_with($path, '/back/gestionnaires');
        }

        return true;
    }
}
