<?php

namespace App\Models;

use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertLog extends Model
{
    /** @use HasFactory<\Database\Factories\AlertLogFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'zone_id',
        'type_alerte',
        'alert_id',
        'titre',
        'description',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
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
}
