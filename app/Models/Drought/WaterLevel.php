<?php

namespace App\Models\Drought;

use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterLevel extends Model
{
    /** @use HasFactory<\Database\Factories\Drought\WaterLevelFactory> */
    use HasFactory;

    public const TYPES = [
        'barrage' => 'Barrage',
        'nappe' => 'Nappe phréatique',
        'reservoir' => 'Réservoir',
        'riviere' => 'Rivière',
    ];

    protected $fillable = [
        'nom_source',
        'zone_id',
        'type_source',
        'niveau',
        'unite',
        'seuil_alerte',
        'seuil_critique',
        'date_mesure',
    ];

    protected function casts(): array
    {
        return [
            'niveau' => 'decimal:2',
            'seuil_alerte' => 'decimal:2',
            'seuil_critique' => 'decimal:2',
            'date_mesure' => 'datetime',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type_source] ?? ucfirst($this->type_source);
    }

    public function niveauStatut(): string
    {
        if ((float) $this->niveau <= (float) $this->seuil_critique) {
            return 'critique';
        }

        if ((float) $this->niveau <= (float) $this->seuil_alerte) {
            return 'alerte';
        }

        return 'normal';
    }

    public function niveauStatutLabel(): string
    {
        return match ($this->niveauStatut()) {
            'critique' => 'Critique',
            'alerte' => 'Alerte',
            default => 'Normal',
        };
    }
}
