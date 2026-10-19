<?php

namespace App\Models\Project;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Funding extends Model
{
    use HasFactory;

    public const SOURCES = ['municipal', 'régional', 'fédéral', 'européen', 'privé', 'don'];

    /** Statuts de paiement d'un don en ligne (NULL = financement confirmé hors Stripe). */
    public const PAIEMENT_EN_ATTENTE = 'en_attente';

    public const PAIEMENT_PAYE = 'paye';

    public const PAIEMENT_ANNULE = 'annule';

    public const PAIEMENT_ECHOUE = 'echoue';

    protected $fillable = [
        'project_id',
        'source',
        'donateur_id',
        'montant',
        'date_versement',
        'statut_paiement',
        'stripe_session_id',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_versement' => 'date',
        ];
    }

    /**
     * Financements réellement confirmés : lignes historiques sans statut de
     * paiement et dons Stripe payés. Les dons en attente, annulés ou échoués
     * ne comptent jamais dans les totaux du projet.
     */
    public function scopeConfirmees($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('statut_paiement')
                ->orWhere('statut_paiement', self::PAIEMENT_PAYE);
        });
    }

    public function estConfirmee(): bool
    {
        return $this->statut_paiement === null || $this->statut_paiement === self::PAIEMENT_PAYE;
    }

    public function statutPaiementLabel(): string
    {
        return match ($this->statut_paiement) {
            self::PAIEMENT_PAYE => 'Payé',
            self::PAIEMENT_ANNULE => 'Annulé',
            self::PAIEMENT_ECHOUE => 'Échoué',
            self::PAIEMENT_EN_ATTENTE => 'En attente',
            default => 'Confirmé',
        };
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
