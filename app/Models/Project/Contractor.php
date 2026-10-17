<?php

namespace App\Models\Project;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contractor extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'specialite',
        'email',
        'telephone',
        'adresse',
    ];

    protected function casts(): array
    {
        return [
            'email' => 'string',
        ];
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower($value);
    }

    public function projectPhases()
    {
        return $this->hasMany(ProjectPhase::class, 'contractor_id');
    }

    public function projectsCount(): int
    {
        return $this->projectPhases()->distinct('project_id')->count('project_id');
    }
}
