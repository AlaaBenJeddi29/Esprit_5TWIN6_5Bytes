<?php

namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    /** @use HasFactory<\Database\Factories\ZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'nom',
        'commune',
        'code_postal',
        'population',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'population' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Infrastructures situées dans cette zone.
     */
    public function infrastructures(): HasMany
    {
        return $this->hasMany(Infrastructure::class);
    }

    /**
     * Projets rattachés à cette zone.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(\App\Models\Project\Project::class, 'zone_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(\App\Models\Incident\Incident::class);
    }

    public function qualitySamples(): HasMany
    {
        return $this->hasMany(\App\Models\Quality\WaterQualitySample::class);
    }

    public function qualityAlerts(): HasMany
    {
        return $this->hasMany(\App\Models\Quality\WaterQualityAlert::class);
    }

    public function alertSubscriptions(): HasMany
    {
        return $this->hasMany(\App\Models\AlertSubscription::class);
    }

    public function waterLevels(): HasMany
    {
        return $this->hasMany(\App\Models\Drought\WaterLevel::class);
    }

    public function consumptionReadings(): HasMany
    {
        return $this->hasMany(\App\Models\Drought\ConsumptionReading::class);
    }

    public function restrictions(): HasMany
    {
        return $this->hasMany(\App\Models\Drought\Restriction::class);
    }

    public function scheduledCuts(): HasMany
    {
        return $this->hasMany(\App\Models\Drought\ScheduledCut::class);
    }
}
