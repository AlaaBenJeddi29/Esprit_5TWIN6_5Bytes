<?php

namespace App\Models\Quality;

use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityThreshold extends Model
{
    /** @use HasFactory<\Database\Factories\Quality\QualityThresholdFactory> */
    use HasFactory;

    public const PARAMETRES = [
        'ph' => 'pH',
        'turbidite' => 'Turbidité',
        'chlore' => 'Chlore résiduel',
        'nitrates' => 'Nitrates',
        'plomb' => 'Plomb',
        'bacteries' => 'Bactéries coliformes',
        'conductivite' => 'Conductivité',
    ];

    public const UNITES = [
        'ph' => 'pH',
        'turbidite' => 'NTU',
        'chlore' => 'mg/L',
        'nitrates' => 'mg/L',
        'plomb' => 'µg/L',
        'bacteries' => 'UFC/100 mL',
        'conductivite' => 'µS/cm',
    ];

    protected $fillable = [
        'zone_id',
        'parametre',
        'unite',
        'valeur_min',
        'valeur_max',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'valeur_min' => 'decimal:4',
            'valeur_max' => 'decimal:4',
            'actif' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function parametreLabel(): string
    {
        return self::PARAMETRES[$this->parametre] ?? ucfirst($this->parametre);
    }

    public function conformiteLabel(): string
    {
        $min = $this->valeur_min !== null ? rtrim(rtrim((string) $this->valeur_min, '0'), '.') : null;
        $max = $this->valeur_max !== null ? rtrim(rtrim((string) $this->valeur_max, '0'), '.') : null;

        return match (true) {
            $min !== null && $max !== null => "{$min} – {$max} {$this->unite}",
            $min !== null => "≥ {$min} {$this->unite}",
            $max !== null => "≤ {$max} {$this->unite}",
            default => '—',
        };
    }

    public function isConforme(float $valeur): bool
    {
        if ($this->valeur_min !== null && $valeur < (float) $this->valeur_min) {
            return false;
        }

        if ($this->valeur_max !== null && $valeur > (float) $this->valeur_max) {
            return false;
        }

        return true;
    }
}
