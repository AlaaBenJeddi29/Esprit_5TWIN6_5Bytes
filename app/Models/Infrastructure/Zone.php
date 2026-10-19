<?php

namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    /** @use HasFactory<\Database\Factories\ZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'nom',
        'commune',
        'code_postal',
        'population',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'population' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Niveau de risque calculé à partir du score moyen des infrastructures.
     *
     * Utilisé pour l'affichage en liste (agrégat passé via withAvg ou calculé
     * localement). La valeur attendue est l'attribut dynamique `risque_moyen`
     * injecté par la requête du contrôleur.
     *
     * Retourne : 'aucun' | 'faible' | 'modere' | 'eleve' | 'critique'
     */
    public function getNiveauRisqueAttribute(): string
    {
        // $this->risque_moyen est l'alias d'agrégat injecté par withAvg()
        $score = $this->risque_moyen;

        if ($score === null) {
            return 'aucun';
        }

        $score = (float) $score;

        if ($score <= 30) {
            return 'faible';
        }

        if ($score <= 60) {
            return 'modere';
        }

        if ($score <= 80) {
            return 'eleve';
        }

        return 'critique';
    }

    /**
     * Score de zone composite, calculé à partir des agrégats déjà chargés
     * par withAvg / withCount dans le contrôleur (aucune requête supplémentaire).
     *
     * Formule : score_zone = 0,6 × risque_moyen + 0,4 × pourcentage_critiques
     *   - risque_moyen      : AVG(score_risque) des infrastructures de la zone
     *   - pourcentage_critiques : (critiques_count / infrastructures_count) × 100
     *
     * Retourne null si la zone n'a aucune infrastructure ou si aucun score n'est renseigné.
     * Résultat arrondi à 1 décimale.
     */
    public function getScoreZoneAttribute(): ?float
    {
        // Pas d'infrastructure ou risque_moyen non calculé → score indéfini
        if (
            empty($this->infrastructures_count)
            || $this->risque_moyen === null
        ) {
            return null;
        }

        $risqueMoyen          = (float) $this->risque_moyen;
        $pourcentageCritiques = ($this->critiques_count / $this->infrastructures_count) * 100;

        return round(0.6 * $risqueMoyen + 0.4 * $pourcentageCritiques, 1);
    }

    /**
     * Raison lisible du score de zone, destinée au bloc "Zones prioritaires".
     *
     * Exemple : "2 infrastructures critiques, risque moyen 85,0"
     * Retourne une chaîne vide si le score est indéfini.
     */
    public function getRaisonRisqueAttribute(): string
    {
        if ($this->score_zone === null) {
            return '';
        }

        $nbCritiques    = (int) ($this->critiques_count ?? 0);
        $libelleCritiqs = $nbCritiques === 1
            ? '1 infrastructure critique'
            : "{$nbCritiques} infrastructures critiques";

        $risque = number_format((float) $this->risque_moyen, 1, ',', ' ');

        return "{$libelleCritiqs}, risque moyen {$risque}";
    }

    /**
     * Prévision du coût de maintenance pour les 3 prochains mois.
     *
     * Méthode : moyenne mobile des 3 derniers mois de l'historique fourni,
     * multipliée par 3. Les mois sans données comptent 0. Cette méthode est
     * conçue pour être remplacée par une régression linéaire sans modifier
     * l'interface : il suffit de changer le corps de cette fonction.
     *
     * @param  array<string, float>  $historique  Tableau ['YYYY-MM' => coût, ...]
     *                                             trié du plus ancien au plus récent
     * @return float|null  null si aucun historique n'est disponible
     */
    public function previsionCoutMaintenance(array $historique): ?float
    {
        if (empty($historique)) {
            return null;
        }

        // Extraire les 3 derniers mois (ou moins) de l'historique
        $derniersMois = array_slice(array_values($historique), -3);

        // Compléter avec des 0 pour les mois manquants jusqu'à atteindre 3 mois
        while (count($derniersMois) < 3) {
            array_unshift($derniersMois, 0.0);
        }

        // Moyenne mobile sur 3 mois × 3 pour la prévision trimestrielle
        $moyenne = array_sum($derniersMois) / 3;

        return round($moyenne * 3, 2);
    }

    /**
     * Infrastructures situées dans cette zone.
     */
    public function infrastructures(): HasMany
    {
        return $this->hasMany(Infrastructure::class);
    }

    /**
     * Projets rattachés à cette zone.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(\App\Models\Project\Project::class, 'zone_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(\App\Models\Incident\Incident::class);
    }

    public function qualitySamples(): HasMany
    {
        return $this->hasMany(\App\Models\Quality\WaterQualitySample::class);
    }

    public function qualityAlerts(): HasMany
    {
        return $this->hasMany(\App\Models\Quality\WaterQualityAlert::class);
    }

    public function alertSubscriptions(): HasMany
    {
        return $this->hasMany(\App\Models\AlertSubscription::class);
    }

    public function waterLevels(): HasMany
    {
        return $this->hasMany(\App\Models\Drought\WaterLevel::class);
    }

    public function consumptionReadings(): HasMany
    {
        return $this->hasMany(\App\Models\Drought\ConsumptionReading::class);
    }

    public function consumptionForecasts(): HasMany
    {
        return $this->hasMany(\App\Models\Drought\ConsumptionForecast::class);
    }

    public function restrictions(): HasMany
    {
        return $this->hasMany(\App\Models\Drought\Restriction::class);
    }

    public function scheduledCuts(): HasMany
    {
        return $this->hasMany(\App\Models\Drought\ScheduledCut::class);
    }
}
