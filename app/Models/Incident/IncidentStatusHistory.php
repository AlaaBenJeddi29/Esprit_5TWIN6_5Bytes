<?php

namespace App\Models\Incident;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentStatusHistory extends Model
{
    /** @use HasFactory<\Database\Factories\Incident\IncidentStatusHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'incident_id',
        'user_id',
        'ancien_statut',
        'nouveau_statut',
        'comment',
        'resolution_note',
        'rejection_reason',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nouveauStatutLabel(): string
    {
        return Incident::STATUSES[$this->nouveau_statut] ?? $this->nouveau_statut;
    }

    public function ancienStatutLabel(): ?string
    {
        return $this->ancien_statut
            ? (Incident::STATUSES[$this->ancien_statut] ?? $this->ancien_statut)
            : null;
    }
}
