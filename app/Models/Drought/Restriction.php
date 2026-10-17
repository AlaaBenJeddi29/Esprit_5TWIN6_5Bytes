<?php

namespace App\Models\Drought;

use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Restriction extends Model
{
    /** @use HasFactory<\Database\Factories\Drought\RestrictionFactory> */
    use HasFactory;

    public const TYPES = [
        'vigilance' => 'Vigilance',
        'alerte' => 'Alerte',
        'alerte_renforcee' => 'Alerte renforcée',
        'crise' => 'Crise',
    ];

    public const STATUTS = [
        'planifiee' => 'Planifiée',
        'active' => 'Active',
        'levee' => 'Levée',
    ];

    protected $fillable = [
        'zone_id',
        'type',
        'titre',
        'description',
        'usages_interdits',
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

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function statutLabel(): string
    {
        return self::STATUTS[$this->statut] ?? ucfirst($this->statut);
    }

    public function isActive(): bool
    {
        return $this->statut === 'active';
    }
}
