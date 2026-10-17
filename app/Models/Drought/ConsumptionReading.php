<?php

namespace App\Models\Drought;

use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ConsumptionReading extends Model
{
    /** @use HasFactory<\Database\Factories\Drought\ConsumptionReadingFactory> */
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'periode',
        'consommation_m3',
        'nombre_abonnes',
    ];

    protected function casts(): array
    {
        return [
            'periode' => 'date',
            'consommation_m3' => 'decimal:2',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function periodeLabel(): string
    {
        return Carbon::parse($this->periode)->translatedFormat('F Y');
    }
}
