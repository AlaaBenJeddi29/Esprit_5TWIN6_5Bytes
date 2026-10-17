<?php

namespace App\Models\Quality;

use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterQualityAlert extends Model
{
    /** @use HasFactory<\Database\Factories\Quality\WaterQualityAlertFactory> */
    use HasFactory;

    public const NIVEAUX = [
        'info' => 'Information',
        'avertissement' => 'Avertissement',
        'critique' => 'Critique',
    ];

    public const STATUTS = [
        'active' => 'Active',
        'resolue' => 'Résolue',
    ];

    protected $fillable = [
        'zone_id',
        'sample_id',
        'parametre',
        'niveau',
        'message',
        'statut',
        'resolue_at',
    ];

    protected function casts(): array
    {
        return [
            'resolue_at' => 'datetime',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(WaterQualitySample::class, 'sample_id');
    }

    public function niveauLabel(): string
    {
        return self::NIVEAUX[$this->niveau] ?? ucfirst($this->niveau);
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
