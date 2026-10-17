<?php

namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Infrastructure extends Model
{
    /** @use HasFactory<\Database\Factories\InfrastructureFactory> */
    use HasFactory;

    /**
     * Types d'infrastructure (repris des données de référence du dépôt groupe).
     *
     * @var list<string>
     */
    public const TYPES = [
        'canalisation',
        'réservoir',
        'station_pompage',
        'compteur',
        'captage',
    ];

    /**
     * Statuts d'exploitation (repris des données de référence du dépôt groupe).
     *
     * @var list<string>
     */
    public const STATUTS = [
        'actif',
        'maintenance',
        'hors_service',
        'en_construction',
    ];

    protected $fillable = [
        'nom',
        'type',
        'materiau',
        'date_installation',
        'capacite',
        'latitude',
        'longitude',
        'statut',
        'score_risque',
        'zone_id',
    ];

    protected function casts(): array
    {
        return [
            'date_installation' => 'date',
            'capacite' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'score_risque' => 'decimal:2',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * Projets liés à cette infrastructure.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(\App\Models\Project\Project::class, 'infrastructure_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(\App\Models\Incident\Incident::class);
    }

    public function typeLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->type));
    }

    public function statutLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->statut));
    }
}
