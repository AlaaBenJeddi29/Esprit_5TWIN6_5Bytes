<?php

namespace App\Http\Controllers\Back;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGestionnaireRequest;
use App\Http\Requests\UpdateGestionnaireRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestion des comptes gestionnaires — réservée aux administrateurs.
 *
 * Le rôle est toujours forcé côté serveur à `gestionnaire` : ce CRUD ne peut
 * ni créer ni promouvoir un administrateur (le premier admin provient du seeder).
 */
class GestionnaireController extends Controller
{
    use ResolvesListingFilters;

    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $actif = $request->string('actif')->toString();
        $perPage = $this->listingPerPage($request, 10);

        $gestionnaires = User::query()
            ->where('role', UserRole::Gestionnaire->value)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(in_array($actif, ['1', '0'], true), fn ($query) => $query->where('is_active', $actif === '1'))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('back.gestionnaires.index', [
            'gestionnaires' => $gestionnaires,
            'search' => $search,
            'currentActif' => $actif,
            'perPage' => $perPage,
        ]);
    }

    public function create(): View
    {
        return view('back.gestionnaires.create', [
            'gestionnaire' => new User([
                'role' => UserRole::Gestionnaire,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(StoreGestionnaireRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $gestionnaire = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::Gestionnaire,
            'is_active' => $data['is_active'] ?? true,
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('back.gestionnaires.index')
            ->with('success', "Le compte gestionnaire « {$gestionnaire->name} » a été créé.");
    }

    public function edit(User $gestionnaire): View
    {
        $this->ensureIsGestionnaire($gestionnaire);

        return view('back.gestionnaires.edit', [
            'gestionnaire' => $gestionnaire,
        ]);
    }

    public function update(UpdateGestionnaireRequest $request, User $gestionnaire): RedirectResponse
    {
        $this->ensureIsGestionnaire($gestionnaire);

        $data = $request->validated();

        $gestionnaire->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $data['is_active'] ?? $gestionnaire->is_active,
        ]);

        if (! empty($data['password'])) {
            $gestionnaire->password = $data['password'];
        }

        $gestionnaire->save();

        return redirect()
            ->route('back.gestionnaires.index')
            ->with('success', "Le compte gestionnaire « {$gestionnaire->name} » a été mis à jour.");
    }

    /**
     * Active ou désactive un compte gestionnaire.
     */
    public function toggle(Request $request, User $gestionnaire): RedirectResponse
    {
        $this->ensureIsGestionnaire($gestionnaire);

        if ($request->user()->is($gestionnaire)) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        $gestionnaire->is_active = ! $gestionnaire->is_active;
        $gestionnaire->save();

        $etat = $gestionnaire->is_active ? 'réactivé' : 'désactivé';

        return back()->with('success', "Le compte de « {$gestionnaire->name} » a été {$etat}.");
    }

    /**
     * Ce CRUD ne manipule que des comptes gestionnaires.
     */
    private function ensureIsGestionnaire(User $user): void
    {
        abort_unless($user->isGestionnaire(), 404);
    }
}
