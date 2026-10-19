<?php

namespace App\Models\Incident;

use App\Models\User;
use Database\Factories\Incident\IncidentFeedbackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Évaluation citoyenne d'un incident traité.
 *
 * Une ligne représente l'évaluation initiale d'un couple (incident, citoyen).
 * Lorsque le problème persiste, la même ligne porte la réclamation et la
 * demande de réouverture. Le workflow de la réclamation est indépendant du
 * statut de l'incident : seule une décision de gestionnaire peut déclencher
 * la réouverture effective via IncidentWorkflowService.
 */
class IncidentFeedback extends Model
{
    /** @use HasFactory<IncidentFeedbackFactory> */
    use HasFactory;

    /**
     * « feedback » étant uncountable pour l'inflecteur, la table doit être
     * déclarée explicitement pour correspondre à la migration.
     */
    protected $table = 'incident_feedbacks';

    public const COMPLAINT_STATUSES = [
        'en_attente' => 'En attente',
        'en_examen' => 'En examen',
        'acceptee' => 'Acceptée',
        'rejetee' => 'Refusée',
        'cloturee' => 'Clôturée',
    ];

    public const COMPLAINT_DECISIONS = [
        'acceptee' => 'Acceptée',
        'rejetee' => 'Refusée',
    ];

    protected $fillable = [
        'incident_id',
        'user_id',
        'rating',
        'comment',
        'resolution_confirmed',
        'complaint_description',
        'reopen_requested',
        'complaint_status',
        'complaint_decision',
        'manager_response',
        'decision_reason',
        'reopen_decision_reason',
        'reviewed_by',
        'reviewed_at',
        'reopened_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'resolution_confirmed' => 'boolean',
            'reopen_requested' => 'boolean',
            'reviewed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(IncidentFeedbackHistory::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function hasComplaint(): bool
    {
        return $this->complaint_status !== null;
    }

    public function isComplaintOpen(): bool
    {
        return in_array($this->complaint_status, ['en_attente', 'en_examen'], true);
    }

    public function isDecided(): bool
    {
        return in_array($this->complaint_status, ['acceptee', 'rejetee', 'cloturee'], true);
    }

    public function complaintStatusLabel(): string
    {
        if ($this->complaint_status === null) {
            return 'Aucune réclamation';
        }

        return self::COMPLAINT_STATUSES[$this->complaint_status] ?? ucfirst($this->complaint_status);
    }

    public function complaintDecisionLabel(): ?string
    {
        if ($this->complaint_decision === null) {
            return null;
        }

        return self::COMPLAINT_DECISIONS[$this->complaint_decision] ?? ucfirst($this->complaint_decision);
    }
}
