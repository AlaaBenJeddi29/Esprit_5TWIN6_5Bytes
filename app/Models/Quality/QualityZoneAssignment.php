<?php

namespace App\Models\Quality;

use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Affectation d'une zone au périmètre qualité d'un gestionnaire.
 * Cette table est propre au module Qualité et ne modifie pas les rôles
 * ni les permissions existants.
 */
class QualityZoneAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'zone_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
