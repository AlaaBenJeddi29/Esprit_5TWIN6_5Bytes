<?php

namespace App\Models\Quality;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterQualityParameter extends Model
{
    /** @use HasFactory<\Database\Factories\Quality\WaterQualityParameterFactory> */
    use HasFactory;

    protected $fillable = [
        'sample_id',
        'parametre',
        'valeur',
        'unite',
        'seuil_min',
        'seuil_max',
        'est_conforme',
    ];

    protected function casts(): array
    {
        return [
            'valeur' => 'decimal:4',
            'seuil_min' => 'decimal:4',
            'seuil_max' => 'decimal:4',
            'est_conforme' => 'boolean',
        ];
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(WaterQualitySample::class, 'sample_id');
    }

    public function parametreLabel(): string
    {
        return QualityThreshold::PARAMETRES[$this->parametre] ?? ucfirst($this->parametre);
    }
}
