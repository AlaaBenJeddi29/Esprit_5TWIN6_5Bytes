<?php

namespace App\Models\Project;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Funding extends Model
{
    use HasFactory;

    public const SOURCES = ['municipal', 'régional', 'fédéral', 'européen', 'privé', 'don'];

    protected $fillable = [
        'project_id',
        'source',
        'donateur_id',
        'montant',
        'date_versement',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_versement' => 'date',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function donateur()
    {
        return $this->belongsTo(User::class, 'donateur_id');
    }

    public function isDonation(): bool
    {
        return $this->source === 'don';
    }

    public function sourceLabel(): string
    {
        return $this->source === 'don' ? 'Don' : ucfirst($this->source);
    }
}
