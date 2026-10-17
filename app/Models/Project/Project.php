<?php

namespace App\Models\Project;

use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    public const TYPES = ['réparation', 'modernisation', 'extension', 'construction'];

    public const STATUTS = ['planifié', 'en_cours', 'suspendu', 'terminé', 'annulé'];

    protected $fillable = [
        'titre',
        'type',
        'description',
        'budget_prevu',
        'date_debut',
        'date_fin_prevue',
        'statut',
        'avancement_pourcentage',
        'zone_id',
        'infrastructure_id',
        'responsable_id',
    ];

    protected function casts(): array
    {
        return [
            'budget_prevu' => 'decimal:2',
            'avancement_pourcentage' => 'integer',
            'date_debut' => 'date',
            'date_fin_prevue' => 'date',
        ];
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function infrastructure()
    {
        return $this->belongsTo(Infrastructure::class, 'infrastructure_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function projectPhases()
    {
        return $this->hasMany(ProjectPhase::class, 'project_id');
    }

    public function fundings()
    {
        return $this->hasMany(Funding::class, 'project_id');
    }

    public function projectDocuments()
    {
        return $this->hasMany(ProjectDocument::class, 'project_id');
    }

    public function publicDocuments()
    {
        return $this->hasMany(ProjectDocument::class, 'project_id')->where('est_public', true);
    }

    public function budgetTotal(): float
    {
        return (float) $this->projectPhases()->sum('cout');
    }

    public function fundingTotal(): float
    {
        return (float) $this->fundings()->sum('montant');
    }

    public function fundingRate(): float
    {
        $budget = (float) $this->budget_prevu;

        return $budget > 0 ? ($this->fundingTotal() / $budget) * 100 : 0;
    }

    public function statutLabel(): string
    {
        return str_replace('_', ' ', $this->statut);
    }
}
