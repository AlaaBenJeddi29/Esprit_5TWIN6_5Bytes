<?php

namespace App\Models\Drought;

use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumptionForecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'forecast_month',
        'prediction_m3',
        'lower_bound',
        'upper_bound',
        'model_used',
        'mae',
    ];

    protected function casts(): array
    {
        return [
            'forecast_month' => 'date',
            'prediction_m3' => 'decimal:2',
            'lower_bound' => 'decimal:2',
            'upper_bound' => 'decimal:2',
            'mae' => 'decimal:2',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
