<?php

namespace App\Models\Incident;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentPhoto extends Model
{
    /** @use HasFactory<\Database\Factories\Incident\IncidentPhotoFactory> */
    use HasFactory;

    protected $fillable = [
        'incident_id',
        'chemin_fichier',
        'nom_original',
        'mime_type',
        'taille',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
