<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
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
        return $this->hasMany(\App\Models\Project\Project::class, 'responsable_id');
    }

    /**
     * Financements (dons) apportés par cet utilisateur.
     */
    public function donations(): HasMany
    {
        return $this->hasMany(\App\Models\Project\Funding::class, 'donateur_id');
    }

    /**
     * Incidents signalés par cet utilisateur.
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(\App\Models\Incident\Incident::class);
    }

    /**
     * Incidents affectés à cet utilisateur (Back Office).
     */
    public function assignedIncidents(): HasMany
    {
        return $this->hasMany(\App\Models\Incident\Incident::class, 'assigned_to');
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
        return $this->hasMany(\App\Models\Quality\WaterQualitySample::class);
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
}
