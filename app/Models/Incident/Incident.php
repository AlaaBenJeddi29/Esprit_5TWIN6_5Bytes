<?php

namespace App\Models\Incident;

use App\Exceptions\InvalidIncidentTransitionException;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use App\Services\IncidentWorkflowService;
use Database\Factories\Incident\IncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    public const TYPES = [
        'fuite' => 'Fuite',
        'coupure' => 'Coupure',
        'qualite' => 'Qualité de l\'eau',
        'pression' => 'Pression',
        'refoulement' => 'Refoulement / inondation',
        'autre' => 'Autre',
    ];

    public const STATUSES = [
        'nouveau' => 'Nouveau',
        'en_cours' => 'En cours',
        'en_attente' => 'En attente',
        'traite' => 'Traité',
        'rejete' => 'Rejeté',
    ];

    public const SEVERITIES = [
        'faible' => 'Faible',
        'moyenne' => 'Moyenne',
        'haute' => 'Haute',
        'critique' => 'Critique',
    ];

    public const PRIORITIES = [
        'basse' => 'Basse',
        'normale' => 'Normale',
        'haute' => 'Haute',
        'urgente' => 'Urgente',
    ];

    protected $fillable = [
        'reference',
        'user_id',
        'zone_id',
        'infrastructure_id',
        'type',
        'title',
        'description',
        'address',
        'latitude',
        'longitude',
        'severity',
        'status',
        'priority',
        'ai_category',
        'ai_confidence',
        'ai_summary',
        'is_duplicate',
        'duplicate_of_id',
        'assigned_to',
        'reported_at',
        'resolved_at',
        'resolution_note',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'ai_confidence' => 'decimal:2',
            'is_duplicate' => 'boolean',
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Incident $incident) {
            if (blank($incident->reference)) {
                $incident->reference = self::nextReference();
            }

            $incident->reported_at ??= now();
        });
    }

    public static function nextReference(): string
    {
        do {
            $reference = 'INC-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function infrastructure(): BelongsTo
    {
        return $this->belongsTo(Infrastructure::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicate_of_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(IncidentPhoto::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IncidentComment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(IncidentStatusHistory::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(IncidentFeedback::class);
    }

    /**
     * Suggestions d'incidents similaires émises pour cet incident.
     */
    public function similaritySuggestions(): HasMany
    {
        return $this->hasMany(IncidentSimilarity::class);
    }

    /**
     * Suggestions dans lesquelles cet incident est le candidat similaire.
     */
    public function similarityMentions(): HasMany
    {
        return $this->hasMany(IncidentSimilarity::class, 'similar_incident_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['traite', 'rejete']);
    }

    public function scopeForCitizen(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function severityLabel(): string
    {
        return self::SEVERITIES[$this->severity] ?? ucfirst($this->severity);
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst($this->priority);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['traite', 'rejete'], true);
    }

    public function belongsToCitizen(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Applique un nouveau statut via le workflow centralisé.
     *
     * @throws InvalidIncidentTransitionException
     */
    public function changeStatus(
        string $status,
        ?User $author = null,
        ?string $comment = null,
        ?string $resolutionNote = null,
        ?string $rejectionReason = null,
    ): void {
        if ($author === null) {
            throw InvalidIncidentTransitionException::actorRequired();
        }

        $updated = app(IncidentWorkflowService::class)->transition(
            $this,
            $status,
            $author,
            $comment,
            $resolutionNote,
            $rejectionReason,
        );

        $this->setRawAttributes($updated->getAttributes(), true);
        $this->setRelations([]);
    }
}
