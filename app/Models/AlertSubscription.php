<?php

namespace App\Models;

use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertSubscription extends Model
{
    /** @use HasFactory<\Database\Factories\AlertSubscriptionFactory> */
    use HasFactory;

    public const TYPES = [
        'tous' => 'Toutes les alertes',
        'qualite' => 'Qualité de l\'eau',
        'restriction' => 'Restrictions',
        'coupure' => 'Coupures programmées',
    ];

    protected $fillable = [
        'user_id',
        'zone_id',
        'type_alerte',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type_alerte] ?? ucfirst($this->type_alerte);
    }
}
