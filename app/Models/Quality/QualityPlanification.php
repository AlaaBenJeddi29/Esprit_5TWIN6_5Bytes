<?php

namespace App\Models\Quality;

use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Occurrence planifiée d'un contrôle qualité.
 *
 * Le statut est calculé à partir du lien vers le prélèvement
 * (`water_quality_sample_id`) et de la date prévue : un contrôle ne peut
 * donc jamais être « effectué » sans prélèvement réellement enregistré.
 */
class QualityPlanification extends Model
{
    public const FREQUENCES = [
        'hebdomadaire' => 'Hebdomadaire',
        'mensuelle' => 'Mensuelle',
        'trimestrielle' => 'Trimestrielle',
    ];

    public const STATUTS = [
        'a_venir' => 'À venir',
        'en_retard' => 'En retard',
        'effectue' => 'Effectué',
    ];

    protected $fillable = [
        'zone_id',
        'user_id',
        'water_quality_sample_id',
        'parent_id',
        'date_prevue',
        'frequence',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_prevue' => 'date',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(WaterQualitySample::class, 'water_quality_sample_id');
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function next(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Limite la requête aux zones autorisées pour l'utilisateur.
     * Un administrateur conserve l'accès à toutes les zones.
     */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('zone_id', QualityZoneAssignment::query()
            ->where('user_id', $user->id)
            ->select('zone_id'));
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('water_quality_sample_id');
    }

    public function scopeWithStatut(Builder $query, ?string $statut): Builder
    {
        return match ($statut) {
            'effectue' => $query->whereNotNull('water_quality_sample_id'),
            'en_retard' => $query->whereNull('water_quality_sample_id')
                ->whereDate('date_prevue', '<', today()),
            'a_venir' => $query->whereNull('water_quality_sample_id')
                ->whereDate('date_prevue', '>=', today()),
            default => $query,
        };
    }

    public function getStatutAttribute(): string
    {
        if ($this->estEffectue()) {
            return 'effectue';
        }

        return $this->date_prevue->lt(today()) ? 'en_retard' : 'a_venir';
    }

    public function statutLabel(): string
    {
        return self::STATUTS[$this->statut] ?? ucfirst($this->statut);
    }

    public function frequenceLabel(): string
    {
        return self::FREQUENCES[$this->frequence] ?? ucfirst($this->frequence);
    }

    public function estEffectue(): bool
    {
        return $this->water_quality_sample_id !== null;
    }

    /**
     * Date de la prochaine occurrence, calculée à partir de la date prévue.
     * addMonth(s)NoOverflow évite les glissements de fin de mois
     * (31 janvier → 28/29 février).
     */
    public function prochaineDate(): Carbon
    {
        $date = $this->date_prevue->copy();

        return match ($this->frequence) {
            'hebdomadaire' => $date->addWeek(),
            'trimestrielle' => $date->addMonthsNoOverflow(3),
            default => $date->addMonthNoOverflow(),
        };
    }

    /**
     * Crée l'occurrence suivante et la relie à celle-ci (historique).
     * Appelée uniquement après l'enregistrement effectif d'un prélèvement.
     */
    public function createNextOccurrence(?int $userId = null): self
    {
        return self::create([
            'zone_id' => $this->zone_id,
            'user_id' => $userId ?? $this->user_id,
            'parent_id' => $this->id,
            'date_prevue' => $this->prochaineDate(),
            'frequence' => $this->frequence,
            'notes' => $this->notes,
        ]);
    }
}
