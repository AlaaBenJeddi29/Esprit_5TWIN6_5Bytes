<?php

namespace App\Models\Incident;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentFeedbackHistory extends Model
{
    use HasFactory;

    public const ACTIONS = [
        'depot' => 'Dépôt de l\'évaluation',
        'examen' => 'Prise en examen',
        'reponse' => 'Réponse du gestionnaire',
        'acceptation' => 'Réclamation acceptée',
        'refus' => 'Réclamation refusée',
        'cloture' => 'Dossier clôturé',
        'reouverture' => 'Incident réouvert',
    ];

    protected $fillable = [
        'incident_feedback_id',
        'user_id',
        'action',
        'ancien_statut',
        'nouveau_statut',
        'comment',
    ];

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(IncidentFeedback::class, 'incident_feedback_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst($this->action);
    }

    public function ancienStatutLabel(): ?string
    {
        return $this->ancien_statut
            ? (IncidentFeedback::COMPLAINT_STATUSES[$this->ancien_statut] ?? $this->ancien_statut)
            : null;
    }

    public function nouveauStatutLabel(): ?string
    {
        return $this->nouveau_statut
            ? (IncidentFeedback::COMPLAINT_STATUSES[$this->nouveau_statut] ?? $this->nouveau_statut)
            : null;
    }
}
