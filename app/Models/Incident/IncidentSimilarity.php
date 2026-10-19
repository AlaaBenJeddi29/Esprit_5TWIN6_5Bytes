<?php

namespace App\Models\Incident;

use App\Models\User;
use Database\Factories\Incident\IncidentSimilarityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentSimilarity extends Model
{
    /** @use HasFactory<IncidentSimilarityFactory> */
    use HasFactory;

    public const STATUS_SUGGESTED = 'suggeree';

    public const STATUS_DUPLICATE = 'confirmee_doublon';

    public const STATUS_LINKED = 'confirmee_lie';

    public const STATUS_REJECTED = 'rejetee';

    public const STATUSES = [
        self::STATUS_SUGGESTED => 'Suggérée',
        self::STATUS_DUPLICATE => 'Doublon confirmé',
        self::STATUS_LINKED => 'Incident connexe',
        self::STATUS_REJECTED => 'Ignorée',
    ];

    /**
     * Décisions acceptées par l'interface, mappées vers un statut.
     *
     * @var array<string, string>
     */
    public const DECISIONS = [
        'doublon' => self::STATUS_DUPLICATE,
        'lie' => self::STATUS_LINKED,
        'ignorer' => self::STATUS_REJECTED,
    ];

    /**
     * Statuts qui comptent dans le signalement d'un problème récurrent.
     *
     * @var list<string>
     */
    public const COUNTED_STATUSES = [
        self::STATUS_SUGGESTED,
        self::STATUS_DUPLICATE,
        self::STATUS_LINKED,
    ];

    public const METHOD_LOCAL = 'local';

    /** Réponse IA exploitable : même problème probable. */
    public const METHOD_LOCAL_AI = 'local_ai';

    /** Réponse IA exploitable : problèmes probablement différents. */
    public const METHOD_LOCAL_AI_NEGATIVE = 'local_ai_negative';

    /** Appel IA tenté sans réponse exploitable ; la suggestion locale est conservée. */
    public const METHOD_LOCAL_AI_UNAVAILABLE = 'local_ai_unavailable';

    protected $fillable = [
        'incident_id',
        'similar_incident_id',
        'score',
        'method',
        'ai_justification',
        'status',
        'reviewed_by',
        'reviewed_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function similarIncident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'similar_incident_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_SUGGESTED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }
}
