<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Incident\Incident;
use App\Models\Incident\IncidentFeedback;
use App\Models\Project\Funding;
use App\Models\Project\Project;
use App\Models\Quality\WaterQualitySample;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Signalements créés par cet utilisateur.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Projets dont cet utilisateur est le responsable.
     */
    public function responsableProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'responsable_id');
    }

    /**
     * Financements (dons) apportés par cet utilisateur.
     */
    public function donations(): HasMany
    {
        return $this->hasMany(Funding::class, 'donateur_id');
    }

    /**
     * Incidents signalés par cet utilisateur.
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * Incidents affectés à cet utilisateur (Back Office).
     */
    public function assignedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'assigned_to');
    }

    /**
     * Évaluations et réclamations déposées par cet utilisateur.
     */
    public function incidentFeedbacks(): HasMany
    {
        return $this->hasMany(IncidentFeedback::class);
    }

    /**
     * Réclamations examinées par cet utilisateur.
     */
    public function reviewedIncidentFeedbacks(): HasMany
    {
        return $this->hasMany(IncidentFeedback::class, 'reviewed_by');
    }

    /**
     * Abonnements aux alertes de zone.
     */
    public function alertSubscriptions(): HasMany
    {
        return $this->hasMany(AlertSubscription::class);
    }

    /**
     * Prélèvements qualité réalisés par cet utilisateur.
     */
    public function qualitySamples(): HasMany
    {
        return $this->hasMany(WaterQualitySample::class);
    }

    /**
     * L'utilisateur est-il administrateur ?
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * L'utilisateur est-il gestionnaire ?
     */
    public function isGestionnaire(): bool
    {
        return $this->role === UserRole::Gestionnaire;
    }

    /**
     * L'utilisateur est-il citoyen ?
     */
    public function isCitoyen(): bool
    {
        return $this->role === UserRole::Citoyen;
    }

    /**
     * L'utilisateur peut-il accéder au Back Office ?
     */
    public function canAccessBackOffice(): bool
    {
        return $this->isAdmin() || $this->isGestionnaire();
    }

    /**
     * L'authentification à deux facteurs est-elle active et confirmée ?
     */
    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return $this->two_factor_confirmed_at !== null
            && is_string($this->two_factor_secret)
            && $this->two_factor_secret !== '';
    }

    /**
     * URL publique de la photo de profil, ou null si aucune photo.
     */
    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        // La version (nom du fichier) invalide le cache du navigateur
        // lorsque l'utilisateur remplace sa photo.
        return route('profile.photo.show', [
            'user' => $this,
            'v' => basename($this->avatar_path),
        ]);
    }
}
