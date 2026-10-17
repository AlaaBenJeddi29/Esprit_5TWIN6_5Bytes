<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    /** @use HasFactory<\Database\Factories\ReportFactory> */
    use HasFactory;

    /**
     * Types de signalement (clé stockée => libellé affiché).
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'fuite' => 'Fuite',
        'coupure' => 'Coupure non annoncée',
        'qualite' => 'Problème de qualité',
        'autre' => 'Autre',
    ];

    /**
     * Statuts de traitement (clé stockée => libellé affiché).
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'nouveau' => 'Nouveau',
        'en_cours' => 'En cours',
        'traite' => 'Traité',
        'rejete' => 'Rejeté',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'description',
        'address',
        'status',
        'reported_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
        ];
    }

    /**
     * Citoyen auteur du signalement (facultatif).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Libellé lisible du type.
     */
    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Libellé lisible du statut.
     */
    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
