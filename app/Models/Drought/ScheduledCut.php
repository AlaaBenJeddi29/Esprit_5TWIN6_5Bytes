<?php

namespace App\Models\Drought;

use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledCut extends Model
{
    /** @use HasFactory<\Database\Factories\Drought\ScheduledCutFactory> */
    use HasFactory;

    public const STATUTS = [
        'planifiee' => 'Planifiée',
        'en_cours' => 'En cours',
        'terminee' => 'Terminée',
        'annulee' => 'Annulée',
    ];

    protected $fillable = [
        'zone_id',
        'infrastructure_id',
        'motif',
        'date_debut',
        'date_fin',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function infrastructure(): BelongsTo
    {
        return $this->belongsTo(Infrastructure::class);
    }

    public function statutLabel(): string
    {
        return self::STATUTS[$this->statut] ?? ucfirst($this->statut);
    }

    public function isUpcoming(): bool
    {
        return $this->statut === 'planifiee' && $this->date_debut?->isFuture();
    }
}
