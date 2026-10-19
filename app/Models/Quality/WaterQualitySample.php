<?php

namespace App\Models\Quality;

use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class WaterQualitySample extends Model
{
    /** @use HasFactory<\Database\Factories\Quality\WaterQualitySampleFactory> */
    use HasFactory;

    public const STATUTS = [
        'en_attente' => 'En attente',
        'conforme' => 'Conforme',
        'non_conforme' => 'Non conforme',
    ];

    protected $fillable = [
        'reference',
        'zone_id',
        'infrastructure_id',
        'user_id',
        'date_prelevement',
        'source',
        'statut',
        'score',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_prelevement' => 'datetime',
            'score' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WaterQualitySample $sample) {
            if (blank($sample->reference)) {
                $sample->reference = self::nextReference();
            }
        });
    }

    public static function nextReference(): string
    {
        do {
            $reference = 'AQ-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function infrastructure(): BelongsTo
    {
        return $this->belongsTo(Infrastructure::class);
    }

    public function preleveur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(WaterQualityParameter::class, 'sample_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(WaterQualityAlert::class, 'sample_id');
    }

    /**
     * Contrôle planifié que ce prélèvement a permis d'effectuer.
     */
    public function planification(): HasOne
    {
        return $this->hasOne(QualityPlanification::class, 'water_quality_sample_id');
    }

    public function statutLabel(): string
    {
        return self::STATUTS[$this->statut] ?? ucfirst($this->statut);
    }

    public function isConforme(): bool
    {
        return $this->statut === 'conforme';
    }
}
