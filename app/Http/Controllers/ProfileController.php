<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\UpdateProfilePhotoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Affiche le profil dans le gabarit correspondant au rôle connecté.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view(
            $user->isCitoyen() ? 'profile.edit' : 'profile.edit-back',
            ['user' => $user],
        );
    }

    /**
     * Met à jour les informations personnelles autorisées.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Seuls le nom et l'e-mail validés sont appliqués : un rôle ou un
        // indicateur is_active envoyé par le client est ignoré.
        $user->fill($request->safe()->only(['name', 'email']));

        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')
            ->with(
                'success',
                $emailChanged
                    ? 'Vos informations ont été mises à jour. Votre nouvelle adresse e-mail doit être vérifiée.'
                    : 'Vos informations ont été mises à jour.',
            )
            ->with('email_changed', $emailChanged);
    }

    /**
     * Enregistre ou remplace la photo de profil sur le disque public.
     */
    public function updatePhoto(UpdateProfilePhotoRequest $request): RedirectResponse
    {
        $user = $request->user();

        // store() génère un nom aléatoire et déduit l'extension du contenu.
        $newPath = $request->file('photo')->store('avatars', 'public');

        if ($newPath === false) {
            return Redirect::route('profile.edit')
                ->with('error', "La photo n'a pas pu être enregistrée. Veuillez réessayer.");
        }

        $previousPath = $user->avatar_path;

        try {
            $user->forceFill(['avatar_path' => $newPath])->save();
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPath);

            throw $exception;
        }

        if ($previousPath !== null && $previousPath !== $newPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return Redirect::route('profile.edit')
            ->with('success', 'Votre photo de profil a été mise à jour.');
    }

    /**
     * Retire la photo de profil et son fichier.
     */
    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $path = $user->avatar_path;

        if ($path !== null) {
            $user->forceFill(['avatar_path' => null])->save();
            Storage::disk('public')->delete($path);
        }

        return Redirect::route('profile.edit')
            ->with('success', 'Votre photo de profil a été supprimée.');
    }

    /**
     * Supprime le compte après revalidation du mot de passe.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Contrainte FK projects.responsable_id = RESTRICT : on bloque avant
        // que la base ne rejette la suppression.
        if ($user->responsableProjects()->exists()) {
            return Redirect::back()->with(
                'error',
                'Impossible de supprimer votre compte : vous êtes responsable de projets en cours. '
                .'Transférez d\'abord la responsabilité de ces projets à un autre utilisateur.'
            );
        }

        $avatarPath = $user->avatar_path;

        Auth::logout();

        $user->delete();

        if ($avatarPath !== null) {
            Storage::disk('public')->delete($avatarPath);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/')
            ->with('success', 'Votre compte a bien été supprimé.');
    }
}
