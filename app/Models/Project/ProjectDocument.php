<?php

namespace App\Models\Project;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectDocument extends Model
{
    use HasFactory;

    public const TYPES = ['rapport', 'photo', 'facture', 'contrat', 'plan', 'autre'];

    protected $fillable = [
        'project_id',
        'titre',
        'chemin_fichier',
        'type',
        'est_public',
    ];

    protected function casts(): array
    {
        return [
            'est_public' => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function typeLabel(): string
    {
        return ucfirst($this->type);
    }

    public function isPublic(): bool
    {
        return $this->est_public;
    }
}
