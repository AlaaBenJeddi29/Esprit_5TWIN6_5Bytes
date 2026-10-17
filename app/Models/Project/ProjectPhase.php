<?php

namespace App\Models\Project;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectPhase extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'contractor_id',
        'nom',
        'date_debut',
        'date_fin',
        'cout',
        'avancement',
    ];

    protected function casts(): array
    {
        return [
            'cout' => 'decimal:2',
            'avancement' => 'integer',
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class, 'contractor_id');
    }

    public function isCompleted(): bool
    {
        return $this->avancement >= 100;
    }
}
