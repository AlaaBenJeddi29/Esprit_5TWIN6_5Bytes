<?php

namespace App\Services;

use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rapport global PDF de la Gestion 1 (Infrastructures et cartographie du réseau).
 *
 * Calcule des indicateurs 100 % agrégés à partir des tables zones,
 * infrastructures et maintenances (aucune requête par ligne), puis rédige
 * l'analyse et les recommandations via une IA locale (Ollama). Si Ollama est
 * éteint, absent ou trop lent, un texte de secours calculé par règles est
 * pris en charge : le rapport se génère toujours.
 */
class GestionReportService
{
    /**
     * Nombre de mois d'historique de maintenance analysés (même valeur que la
     * page Zones pour que les chiffres du rapport soient identiques).
     */
    private const MOIS_HISTORIQUE = 6;

    /**
     * Score au-delà duquel une infrastructure est considérée comme critique.
     */
    private const SEUIL_CRITIQUE = 80;

    /**
     * Statut d'une maintenance terminée (seules celles-ci sont comptabilisées
     * dans les coûts, comme sur la page Zones).
     */
    private const STATUT_TERMINEE = 'terminee';

    /**
     * Durée de mise en cache du texte généré par l'IA (en secondes).
     */
    private const CACHE_DUREE_IA = 3600;

    /**
     * Calcule l'ensemble des données agrégées du rapport.
     *
     * @return array{
     *     indicateurs: array<string, float|int|null>,
     *     zones: list<array<string, mixed>>,
     *     top_infrastructures: list<array<string, mixed>>,
     *     repartitions: array<string, list<array<string, mixed>>>,
     *     maintenances: array<string, mixed>,
     *     anomalies: list<array<string, mixed>>,
     * }
     */
    public function donnees(): array
    {
        return [
            'indicateurs'           => $this->indicateursGlobaux(),
            'zones'                 => $this->classementZones(),
            'top_infrastructures'   => $this->topInfrastructures(),
            'repartitions'          => $this->repartitions(),
            'maintenances'          => $this->statistiquesMaintenances(),
            'anomalies'             => $this->anomaliesCouts(),
        ];
    }

    /**
     * a) Indicateurs globaux du réseau — une seule requête d'agrégats.
     *
     * @return array<string, float|int|null>
     */
    private function indicateursGlobaux(): array
    {
        $infraStats = DB::table('infrastructures')
            ->selectRaw('COUNT(*) AS nb_infrastructures')
            ->selectRaw('AVG(score_risque) AS risque_moyen')
            ->selectRaw('SUM(CASE WHEN score_risque > ? THEN 1 ELSE 0 END) AS nb_critiques', [self::SEUIL_CRITIQUE])
            ->selectRaw('SUM(CASE WHEN score_risque IS NULL THEN 1 ELSE 0 END) AS nb_sans_score')
            ->first();

        $populationTotale = (int) DB::table('zones')->selectRaw('COALESCE(SUM(population), 0) AS total')->value('total');

        // Historique mensuel global des maintenances terminées des 6 derniers mois
        $historiqueMensuel = $this->historiqueMensuelGlobal();

        // Même méthode de prévision que la page Zones (moyenne des 3 derniers mois × 3)
        $prevision = (new Zone())->previsionCoutMaintenance($historiqueMensuel);

        $nbInfrastructures = (int) ($infraStats->nb_infrastructures ?? 0);
        $nbCritiques       = (int) ($infraStats->nb_critiques ?? 0);

        return [
            'nb_zones'           => (int) DB::table('zones')->count(),
            'nb_infrastructures' => $nbInfrastructures,
            'population_totale'  => $populationTotale,
            // null si aucune infrastructure n'a de score renseigné
            'risque_moyen'       => $infraStats->risque_moyen !== null ? round((float) $infraStats->risque_moyen, 1) : null,
            'nb_critiques'       => $nbCritiques,
            // Pourcentage sur l'ensemble des infrastructures (0 si réseau vide)
            'pct_critiques'      => $nbInfrastructures > 0 ? round($nbCritiques / $nbInfrastructures * 100, 1) : 0.0,
            'nb_sans_score'      => (int) ($infraStats->nb_sans_score ?? 0),
            'cout_total_6_mois'  => round($this->coutTotalMaintenancesTerminees(), 2),
            'prevision_3_mois'   => $prevision,
        ];
    }

    /**
     * b) Classement de toutes les zones, trié par score de zone décroissant.
     *
     * Agrégats chargés en une requête (withCount/withAvg), coûts et prévisions
     * récupérés en deux requêtes groupées : aucun N+1.
     *
     * @return list<array<string, mixed>>
     */
    private function classementZones(): array
    {
        $zones = Zone::query()
            ->withCount('infrastructures')
            ->withCount([
                'infrastructures as critiques_count' => fn ($q) => $q->where('score_risque', '>', self::SEUIL_CRITIQUE),
            ])
            ->withAvg('infrastructures as risque_moyen', 'score_risque')
            ->get();

        $depuisMois = now()->subMonths(self::MOIS_HISTORIQUE)->startOfMonth()->toDateString();

        // Coût des maintenances terminées : total 6 mois + historique mensuel par zone
        $coutsBruts = DB::table('maintenances')
            ->join('infrastructures', 'infrastructures.id', '=', 'maintenances.infrastructure_id')
            ->select([
                'infrastructures.zone_id',
                DB::raw("DATE_FORMAT(maintenances.date_intervention, '%Y-%m') AS mois"),
                DB::raw('SUM(maintenances.cout) AS total_cout'),
            ])
            ->where('maintenances.statut', self::STATUT_TERMINEE)
            ->where('maintenances.date_intervention', '>=', $depuisMois)
            ->whereNotNull('maintenances.cout')
            ->groupBy('infrastructures.zone_id', 'mois')
            ->get();

        $historiqueParZone = [];
        $coutParZone       = [];

        foreach ($coutsBruts as $ligne) {
            $historiqueParZone[$ligne->zone_id][$ligne->mois] = (float) $ligne->total_cout;
            $coutParZone[$ligne->zone_id] = ($coutParZone[$ligne->zone_id] ?? 0.0) + (float) $ligne->total_cout;
        }

        $zoneModele = new Zone();

        $classement = $zones->map(function (Zone $zone) use ($historiqueParZone, $coutParZone, $zoneModele) {
            // Compléter le calendrier (mois sans maintenance = 0) pour une prévision
            // identique à celle de la page Zones.
            $historique = $this->completerMoisSansDonnees($historiqueParZone[$zone->id] ?? []);

            return [
                'nom'               => $zone->nom,
                'commune'           => $zone->commune,
                'population'        => (int) $zone->population,
                'infrastructures'   => (int) $zone->infrastructures_count,
                'risque_moyen'      => $zone->risque_moyen !== null ? round((float) $zone->risque_moyen, 1) : null,
                'critiques'         => (int) $zone->critiques_count,
                // Accessor du modèle : 0,6 × risque moyen + 0,4 × % critiques
                'score_zone'        => $zone->score_zone,
                'niveau'            => $zone->niveau_risque,
                'cout_6_mois'       => round($coutParZone[$zone->id] ?? 0.0, 2),
                'prevision_3_mois'  => $zoneModele->previsionCoutMaintenance($historique),
            ];
        })
            // Tri par score de zone décroissant ; les zones sans score (null) finissent en dernier
            ->sortByDesc('score_zone')
            ->values()
            ->all();

        return $classement;
    }

    /**
     * c) Les 10 infrastructures les plus risquées du réseau.
     *
     * @return list<array<string, mixed>>
     */
    private function topInfrastructures(): array
    {
        return Infrastructure::query()
            ->with('zone:id,nom')
            ->whereNotNull('score_risque')
            ->orderByDesc('score_risque')
            ->limit(10)
            ->get()
            ->map(fn (Infrastructure $infra) => [
                'nom'      => $infra->nom,
                'zone'     => $infra->zone?->nom ?? '—',
                'type'     => $infra->typeLabel(),
                'materiau' => $infra->materiau,
                'age'      => $infra->date_installation ? $infra->date_installation->diffInYears(now()) : null,
                'statut'   => $infra->statutLabel(),
                'score'    => round((float) $infra->score_risque, 1),
            ])
            ->all();
    }

    /**
     * d) Répartitions des infrastructures : niveau de risque, type, matériau, statut.
     * Chaque entrée = effectif + pourcentage sur le total du réseau.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function repartitions(): array
    {
        $total = (int) DB::table('infrastructures')->count();

        // Niveau de risque : une seule requête à conditions agrégées
        $niveaux = DB::table('infrastructures')
            ->selectRaw('SUM(CASE WHEN score_risque IS NOT NULL AND score_risque <= 30 THEN 1 ELSE 0 END) AS faible')
            ->selectRaw('SUM(CASE WHEN score_risque > 30 AND score_risque <= 60 THEN 1 ELSE 0 END) AS modere')
            ->selectRaw('SUM(CASE WHEN score_risque > 60 AND score_risque <= 80 THEN 1 ELSE 0 END) AS eleve')
            ->selectRaw('SUM(CASE WHEN score_risque > ? THEN 1 ELSE 0 END) AS critique', [self::SEUIL_CRITIQUE])
            ->selectRaw('SUM(CASE WHEN score_risque IS NULL THEN 1 ELSE 0 END) AS sans_score')
            ->first();

        $repartitionNiveaux = collect([
            'Faible (0 – 30)'      => (int) $niveaux->faible,
            'Modéré (31 – 60)'     => (int) $niveaux->modere,
            'Élevé (61 – 80)'      => (int) $niveaux->eleve,
            'Critique (> 80)'      => (int) $niveaux->critique,
            'Sans score'           => (int) $niveaux->sans_score,
        ])
            ->map(fn (int $nb, string $libelle) => [
                'label'       => $libelle,
                'nombre'      => $nb,
                'pourcentage' => $this->pourcentage($nb, $total),
            ])
            ->values()
            ->all();

        return [
            'niveaux'    => $repartitionNiveaux,
            'types'      => $this->repartitionParColonne('type', $total),
            'materiaux'  => $this->repartitionParColonne('materiau', $total),
            'statuts'    => $this->repartitionParColonne('statut', $total),
        ];
    }

    /**
     * Répartition des infrastructures groupée par une colonne native
     * (type, materiau ou statut), triée par effectif décroissant.
     *
     * @return list<array<string, mixed>>
     */
    private function repartitionParColonne(string $colonne, int $total): array
    {
        return DB::table('infrastructures')
            ->select($colonne, DB::raw('COUNT(*) AS nb'))
            ->groupBy($colonne)
            ->orderByDesc('nb')
            ->get()
            ->map(fn ($ligne) => [
                'label'       => $this->libelleTechnique((string) $ligne->{$colonne}),
                'nombre'      => (int) $ligne->nb,
                'pourcentage' => $this->pourcentage((int) $ligne->nb, $total),
            ])
            ->all();
    }

    /**
     * e) Statistiques de maintenance sur les 6 derniers mois
     * (maintenances terminées uniquement, comme la page Zones).
     *
     * @return array<string, mixed>
     */
    private function statistiquesMaintenances(): array
    {
        $depuisMois = now()->subMonths(self::MOIS_HISTORIQUE)->startOfMonth()->toDateString();

        // Nombre et coût total par type (preventive / corrective)
        $parType = DB::table('maintenances')
            ->selectRaw('type, COUNT(*) AS nb, COALESCE(SUM(cout), 0) AS cout_total')
            ->where('statut', self::STATUT_TERMINEE)
            ->where('date_intervention', '>=', $depuisMois)
            ->groupBy('type')
            ->get()
            ->mapWithKeys(fn ($ligne) => [$ligne->type => [
                'nombre' => (int) $ligne->nb,
                'cout'   => round((float) $ligne->cout_total, 2),
            ]])
            ->all();

        // Coût mensuel sur 6 mois (calendrier complété avec les mois à 0)
        $coutsMensuels = $this->completerMoisSansDonnees($this->historiqueMensuelBrut());

        // Coût par zone, trié décroissant
        $coutsParZone = DB::table('maintenances')
            ->join('infrastructures', 'infrastructures.id', '=', 'maintenances.infrastructure_id')
            ->join('zones', 'zones.id', '=', 'infrastructures.zone_id')
            ->select('zones.nom', DB::raw('SUM(maintenances.cout) AS total_cout'))
            ->where('maintenances.statut', self::STATUT_TERMINEE)
            ->where('maintenances.date_intervention', '>=', $depuisMois)
            ->whereNotNull('maintenances.cout')
            ->groupBy('zones.nom')
            ->orderByDesc('total_cout')
            ->get()
            ->map(fn ($ligne) => [
                'zone' => $ligne->nom,
                'cout' => round((float) $ligne->total_cout, 2),
            ])
            ->all();

        // Les valeurs du champ `type` sont libres (ex : 'préventive', 'preventive',
        // 'corrective', 'curative'…). On regroupe de façon souple : tout ce qui
        // contient 'pr' (préventive / preventive) est considéré préventif ; tout
        // le reste est considéré correctif / curatif.
        $nbPreventives  = 0;
        $nbCorrectives  = 0;
        $coutPreventif  = 0.0;
        $coutCorrectif  = 0.0;

        foreach ($parType as $type => $stats) {
            // Normaliser : retirer accents + minuscules pour comparer sans ambiguïté
            $typeNorm = mb_strtolower(
                iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $type) ?: $type
            );

            if (str_starts_with($typeNorm, 'pr')) {
                $nbPreventives += $stats['nombre'];
                $coutPreventif += $stats['cout'];
            } else {
                $nbCorrectives += $stats['nombre'];
                $coutCorrectif += $stats['cout'];
            }
        }

        return [
            'preventives'    => $nbPreventives,
            'correctives'    => $nbCorrectives,
            'cout_preventif' => round($coutPreventif, 2),
            'cout_correctif' => round($coutCorrectif, 2),
            'couts_mensuels' => $coutsMensuels,
            'couts_par_zone' => $coutsParZone,
        ];
    }

    /**
     * f) Anomalies de coûts : maintenances terminées dont le coût dépasse
     * moyenne + 2 × écart-type des maintenances terminées du même type.
     * Aucun calcul sous un seuil de 5 maintenances du type (écart-type peu
     * significatif) ; liste limitée aux 10 anomalies les plus élevées.
     *
     * @return list<array<string, mixed>>
     */
    private function anomaliesCouts(): array
    {
        // Une seule requête : toutes les maintenances terminées avec leur infrastructure et zone
        $lignes = DB::table('maintenances')
            ->join('infrastructures', 'infrastructures.id', '=', 'maintenances.infrastructure_id')
            ->join('zones', 'zones.id', '=', 'infrastructures.zone_id')
            ->select([
                'maintenances.id',
                'maintenances.type',
                'maintenances.cout',
                'maintenances.date_intervention',
                'infrastructures.nom as infrastructure_nom',
                'zones.nom as zone_nom',
            ])
            ->where('maintenances.statut', self::STATUT_TERMINEE)
            ->whereNotNull('maintenances.cout')
            ->get();

        // Regrouper par type pour calculer moyenne et écart-type dans chaque groupe
        $parType = $lignes->groupBy('type');

        $anomalies = [];

        foreach ($parType as $type => $groupe) {
            // Moins de 5 valeurs : écart-type non significatif → aucune anomalie
            if ($groupe->count() < 5) {
                continue;
            }

            $couts   = $groupe->map(fn ($l) => (float) $l->cout)->all();
            $moyenne = array_sum($couts) / count($couts);

            // Écart-type (population) — nul si tous les coûts sont identiques
            $variance = array_sum(array_map(fn ($c) => ($c - $moyenne) ** 2, $couts)) / count($couts);
            $ecart    = sqrt($variance);

            // Écart-type nul ou seuil invalide : aucune anomalie détectable
            if ($ecart <= 0.0) {
                continue;
            }

            $seuil = $moyenne + 2 * $ecart;

            foreach ($groupe as $ligne) {
                if ((float) $ligne->cout > $seuil) {
                    $anomalies[] = [
                        'infrastructure' => $ligne->infrastructure_nom,
                        'zone'           => $ligne->zone_nom,
                        'date'           => $ligne->date_intervention,
                        'cout'           => round((float) $ligne->cout, 2),
                        'type'           => $this->libelleTechnique((string) $type),
                        'seuil'          => round($seuil, 2),
                    ];
                }
            }
        }

        // Les 10 anomalies au coût le plus élevé
        usort($anomalies, fn ($a, $b) => $b['cout'] <=> $a['cout']);

        return array_slice($anomalies, 0, 10);
    }

    /**
     * Historique mensuel global (toutes zones) des maintenances terminées.
     *
     * @return array<string, float> ['YYYY-MM' => coût]
     */
    private function historiqueMensuelGlobal(): array
    {
        return $this->completerMoisSansDonnees($this->historiqueMensuelBrut());
    }

    /**
     * Somme des maintenances terminées des 6 derniers mois par mois,
     * sans complétude du calendrier (les mois absents sont simplement manquants).
     *
     * @return array<string, float>
     */
    private function historiqueMensuelBrut(): array
    {
        $depuisMois = now()->subMonths(self::MOIS_HISTORIQUE)->startOfMonth()->toDateString();

        return DB::table('maintenances')
            ->selectRaw("DATE_FORMAT(date_intervention, '%Y-%m') AS mois")
            ->selectRaw('SUM(cout) AS total_cout')
            ->where('statut', self::STATUT_TERMINEE)
            ->where('date_intervention', '>=', $depuisMois)
            ->whereNotNull('cout')
            ->groupBy('mois')
            ->get()
            ->mapWithKeys(fn ($ligne) => [$ligne->mois => (float) $ligne->total_cout])
            ->all();
    }

    /**
     * Total des maintenances terminées des 6 derniers mois.
     */
    private function coutTotalMaintenancesTerminees(): float
    {
        $depuisMois = now()->subMonths(self::MOIS_HISTORIQUE)->startOfMonth()->toDateString();

        return (float) DB::table('maintenances')
            ->where('statut', self::STATUT_TERMINEE)
            ->where('date_intervention', '>=', $depuisMois)
            ->whereNotNull('cout')
            ->sum('cout');
    }

    /**
     * Complète un historique mensuel avec les mois du calendrier des
     * MOIS_HISTORIQUE derniers mois qui n'ont aucune maintenance (= 0).
     * Un historique vide reste vide (aucune prévision possible).
     *
     * @param  array<string, float>  $historique
     * @return array<string, float>
     */
    private function completerMoisSansDonnees(array $historique): array
    {
        if ($historique === []) {
            return [];
        }

        $complet = [];
        for ($i = self::MOIS_HISTORIQUE - 1; $i >= 0; $i--) {
            $mois = now()->subMonths($i)->format('Y-m');
            $complet[$mois] = $historique[$mois] ?? 0.0;
        }

        return $complet;
    }

    /**
     * Pourcentage sécurisé (division par zéro → 0).
     */
    private function pourcentage(int $valeur, int $total): float
    {
        return $total > 0 ? round($valeur / $total * 100, 1) : 0.0;
    }

    /**
     * Rend un libellé technique lisible : 'station_pompage' → 'Station pompage'.
     */
    private function libelleTechnique(string $valeur): string
    {
        return $valeur === '' ? 'Non renseigné' : ucfirst(str_replace('_', ' ', $valeur));
    }

    /**
     * Rédige l'analyse et les recommandations du rapport via l'IA locale Ollama.
     *
     * Le prompt ne contient que des chiffres agrégés et des noms de zones /
     * infrastructures — aucune donnée personnelle. Le résultat est mis en cache
     * 1 heure (clé basée sur le hash du prompt). Si Ollama est éteint, absent,
     * trop lent ou renvoie une réponse vide, un texte de secours calculé par
     * règles est retourné à la place.
     *
     * @param  array<string, mixed>  $donnees  Résultat de donnees()
     * @return array{texte: string, source: string}  source = 'ollama' | 'secours'
     */
    public function texteIA(array $donnees): array
    {
        $prompt = $this->construirePrompt($donnees);
        $cle    = 'gestion1:rapport-texte-ia:'.sha1($prompt);

        // Texte déjà généré pour ces chiffres exactement ?
        $texte = Cache::get($cle);

        if (\is_string($texte) && $texte !== '') {
            return ['texte' => $texte, 'source' => 'ollama'];
        }

        $config = config('services.ollama');
        $url    = rtrim($config['url'], '/');
        $url    = str_starts_with($url, 'http') ? $url : 'http://'.$url;

        try {
            $reponse = Http::timeout((int) $config['timeout'])
                ->post($url.'/api/generate', [
                    'model'   => $config['model'],
                    'prompt'  => $prompt,
                    'stream'  => false,
                    'options' => ['temperature' => 0.3],
                ]);

            if (! $reponse->successful()) {
                throw new \RuntimeException("Ollama a répondu HTTP {$reponse->status()}.");
            }

            $texte = trim((string) $reponse->json('response'));

            if ($texte === '') {
                throw new \RuntimeException('Ollama a renvoyé une réponse vide.');
            }

            // Succès : mise en cache 1 heure pour ne pas relancer l'IA à chaque clic
            Cache::put($cle, $texte, now()->addHour());

            return ['texte' => $texte, 'source' => 'ollama'];
        } catch (Throwable $e) {
            // Le rapport doit sortir même sans IA : on journalise et on bascule sur le secours
            report($e);

            return ['texte' => $this->texteSecours($donnees), 'source' => 'secours'];
        }
    }

    /**
     * Construit le prompt envoyé à Ollama : instructions en français +
     * uniquement des chiffres agrégés au format JSON.
     */
    private function construirePrompt(array $donnees): string
    {
        $i = $donnees['indicateurs'];

        $chiffres = [
            'indicateurs_globaux' => [
                'nombre_zones'           => $i['nb_zones'],
                'nombre_infrastructures' => $i['nb_infrastructures'],
                'population_totale'      => $i['population_totale'],
                'risque_moyen_reseau'    => $i['risque_moyen'],
                'infrastructures_critiques' => $i['nb_critiques'],
                'pourcentage_critiques'  => $i['pct_critiques'],
                'infrastructures_sans_score' => $i['nb_sans_score'],
                'cout_maintenance_6_mois' => $i['cout_total_6_mois'],
                'prevision_3_mois'       => $i['prevision_3_mois'],
            ],
            // 5 zones les plus prioritaires seulement
            'zones_prioritaires' => array_slice(array_map(fn (array $z) => [
                'zone'        => $z['nom'],
                'score_zone'  => $z['score_zone'],
                'risque_moyen' => $z['risque_moyen'],
                'critiques'   => $z['critiques'],
                'prevision_3_mois' => $z['prevision_3_mois'],
            ], $donnees['zones']), 0, 5),
            // 5 infrastructures les plus risquées seulement
            'infrastructures_a_risque' => array_slice(array_map(fn (array $inf) => [
                'infrastructure' => $inf['nom'],
                'zone'           => $inf['zone'],
                'type'           => $inf['type'],
                'score'          => $inf['score'],
            ], $donnees['top_infrastructures']), 0, 5),
            'repartition_niveaux_risque' => array_map(fn (array $r) => [
                'niveau'  => $r['label'],
                'nombre'  => $r['nombre'],
            ], $donnees['repartitions']['niveaux']),
            'maintenances' => [
                'preventives'    => $donnees['maintenances']['preventives'],
                'correctives'    => $donnees['maintenances']['correctives'],
                'cout_preventif' => $donnees['maintenances']['cout_preventif'],
                'cout_correctif' => $donnees['maintenances']['cout_correctif'],
            ],
            'anomalies_cout' => array_map(fn (array $a) => [
                'infrastructure' => $a['infrastructure'],
                'zone'           => $a['zone'],
                'cout'           => $a['cout'],
                'type'           => $a['type'],
            ], $donnees['anomalies']),
        ];

        return "Tu es analyste d'un service des eaux. À partir des chiffres JSON ci-dessous (seule source d'information, montant en DT), rédige en français l'analyse d'un rapport global sur les infrastructures d'eau potable. Structure ta réponse en exactement quatre parties, dans cet ordre, avec ces titres sur leur propre ligne :\n"
            ."Synthèse globale\n(4 phrases maximum)\n"
            ."Analyse des risques par zone prioritaire\n(une phrase par zone, 5 zones maximum)\n"
            ."Analyse des coûts de maintenance\n(4 phrases maximum)\n"
            ."Recommandations\n(5 à 7 actions priorisées, une par ligne, chaque ligne commençant par un tiret)\n"
            ."N'invente aucun chiffre : utilise uniquement ceux du JSON. Pas de markdown, pas de tableaux, pas de titres supplémentaires. Si une information manque, reste général.\n\n"
            .'CHIFFRES : '.json_encode($chiffres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Texte de secours calculé par règles, utilisé quand Ollama est indisponible.
     * Reprend la même structure en quatre parties que le texte de l'IA.
     *
     * @param  array<string, mixed>  $donnees
     */
    private function texteSecours(array $donnees): string
    {
        $i = $donnees['indicateurs'];

        $montant = fn ($v) => $v === null ? 'indisponible' : number_format((float) $v, 2, ',', ' ').' DT';
        $risque  = $i['risque_moyen'] !== null ? number_format((float) $i['risque_moyen'], 1, ',', ' ') : 'indisponible';

        // 1) Synthèse globale (chiffrée)
        $lignes = [];
        $lignes[] = 'Synthèse globale';
        $lignes[] = sprintf(
            'Le réseau couvre %d zone(s) et %d infrastructure(s) pour une population desservie de %s habitants.',
            $i['nb_zones'],
            $i['nb_infrastructures'],
            number_format($i['population_totale'], 0, ',', ' '),
        );
        $lignes[] = sprintf('Le risque moyen du réseau est de %s sur 100.', $risque);
        $lignes[] = sprintf(
            '%d infrastructure(s) présentent un score critique (supérieur à %d), soit %s %% du réseau.',
            $i['nb_critiques'],
            self::SEUIL_CRITIQUE,
            number_format((float) $i['pct_critiques'], 1, ',', ' '),
        );
        $lignes[] = sprintf(
            'Les 6 derniers mois ont généré %s de maintenance ; la prévision sur les 3 prochains mois est de %s.',
            $montant($i['cout_total_6_mois']),
            $montant($i['prevision_3_mois']),
        );

        // 2) Analyse des risques par zone prioritaire (une phrase par zone, 5 max)
        $lignes[] = '';
        $lignes[] = 'Analyse des risques par zone prioritaire';

        $zonesPrioritaires = array_slice(array_values(array_filter($donnees['zones'], fn (array $z) => $z['score_zone'] !== null)), 0, 5);

        if ($zonesPrioritaires === []) {
            $lignes[] = 'Aucune zone ne dispose encore d\'un score de risque calculé : faites évaluer les infrastructures pour activer cette analyse.';
        }

        foreach ($zonesPrioritaires as $zone) {
            $lignes[] = sprintf(
                '%s : score de zone %s, risque moyen %s, %d infrastructure(s) critique(s) sur %d.',
                $zone['nom'],
                number_format((float) $zone['score_zone'], 1, ',', ' '),
                $zone['risque_moyen'] !== null ? number_format((float) $zone['risque_moyen'], 1, ',', ' ') : 'non évalué',
                $zone['critiques'],
                $zone['infrastructures'],
            );
        }

        // 3) Analyse des coûts de maintenance (4 phrases max)
        $m = $donnees['maintenances'];

        $lignes[] = '';
        $lignes[] = 'Analyse des coûts de maintenance';
        $lignes[] = sprintf(
            '%d interventions préventives (%s) et %d interventions correctives (%s) ont été réalisées sur les 6 derniers mois.',
            $m['preventives'],
            $montant($m['cout_preventif']),
            $m['correctives'],
            $montant($m['cout_correctif']),
        );

        $totalTypes = $m['cout_preventif'] + $m['cout_correctif'];
        $lignes[] = $totalTypes > 0 && $m['cout_correctif'] > $m['cout_preventif']
            ? 'Les corrections représentent la part majoritaire du budget, signe d\'un réseau qui subit davantage ses pannes qu\'il ne les anticipe.'
            : 'La part préventive domine ou équilibre les corrections, signal d\'un entretien anticipé du réseau.';

        $lignes[] = count($donnees['anomalies']) > 0
            ? sprintf('%d maintenance(s) affichent un coût anormal au regard des moyennes constatées pour leur type.', count($donnees['anomalies']))
            : 'Aucun coût de maintenance anormal n\'a été détecté par rapport aux moyennes par type.';

        $lignes[] = sprintf('La prévision indicative sur 3 mois (moyenne des 3 derniers mois) est de %s.', $montant($i['prevision_3_mois']));

        // 4) Recommandations conditionnelles (une par ligne, tiret)
        $lignes[] = '';
        $lignes[] = 'Recommandations';

        $recos = [];

        if ($i['nb_critiques'] > 0) {
            $recos[] = sprintf(
                '- Inspecter sans délai les %d infrastructure(s) dont le score dépasse %d, en commençant par les zones les mieux classées en risque.',
                $i['nb_critiques'],
                self::SEUIL_CRITIQUE,
            );
        }

        if ($i['nb_sans_score'] > 0) {
            $recos[] = sprintf('Planifier l\'évaluation du score de risque des %d infrastructure(s) non notées.', $i['nb_sans_score']);
        }

        if ($donnees['maintenances']['correctives'] > $donnees['maintenances']['preventives']) {
            $recos[] = 'Développer le programme de maintenance préventive pour réduire le volume d\'interventions correctives, plus coûteuses.';
        }

        if (count($donnees['anomalies']) > 0) {
            $recos[] = sprintf('Auditer les %d maintenance(s) au coût anormal listées en fin de rapport.', count($donnees['anomalies']));
        }

        if ($donnees['zones'] !== []) {
            $recos[] = sprintf(
                'Prioriser les investissements sur %s, zone au score de zone le plus élevé.',
                $donnees['zones'][0]['nom'],
            );
        }

        if ($i['prevision_3_mois'] !== null) {
            $recos[] = sprintf('Bloquer une enveloppe budgétaire d\'au moins %s pour le prochain trimestre.', $montant($i['prevision_3_mois']));
        }

        $recos[] = 'Mettre à jour les scores de risque après chaque intervention afin de fiabiliser le classement des zones.';

        // 5 à 7 actions priorisées
        foreach (array_slice($recos, 0, 7) as $rec) {
            $lignes[] = $rec;
        }

        return implode("\n", $lignes);
    }

    /**
     * Tente de démarrer Ollama en arrière-plan si le serveur ne répond pas.
     * Ne fait jamais planter l'application en cas d'échec (le texte de secours
     * prend le relais).
     */
    public function demarrerOllamaSiNecessaire(): void
    {
        $url = rtrim((string) config('services.ollama.url'), '/');

        // Le serveur répond déjà ? Rien à faire.
        try {
            if (Http::timeout(3)->get($url.'/api/tags')->successful()) {
                return;
            }
        } catch (Throwable) {
            // Serveur éteint ou absent : on tente de le démarrer ci-dessous.
        }

        if (! \function_exists('exec')) {
            return;
        }

        $bin = $this->cheminOllama();
        if ($bin === null) {
            Log::warning('Démarrage automatique d\'Ollama impossible : exécutable introuvable sur ce poste.');

            return;
        }

        try {
            match (PHP_OS_FAMILY) {
                'Windows' => exec(sprintf('start /B "" "%s" serve', str_replace('"', '""', $bin))),
                'Darwin', 'Linux' => exec(sprintf('nohup %s serve > /dev/null 2>&1 &', escapeshellarg($bin))),
                default => null,
            };

            // Laisser au serveur le temps d'écouter le port
            sleep(4);
        } catch (Throwable $e) {
            Log::warning('Démarrage automatique d\'Ollama impossible : '.$e->getMessage());
        }
    }

    /**
     * Cherche un exécutable Ollama installé localement, notamment sur Windows.
     */
    private function cheminOllama(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $bin = trim((string) shell_exec('where.exe ollama 2>nul'));
            if ($bin !== '') {
                $lines = preg_split('/\r\n|\r|\n/', $bin, -1, PREG_SPLIT_NO_EMPTY);
                if (is_array($lines) && $lines !== []) {
                    return trim((string) $lines[0]);
                }
            }

            $candidats = [
                getenv('LOCALAPPDATA') ? rtrim((string) getenv('LOCALAPPDATA'), "\\/") . '\\Programs\\Ollama\\ollama.exe' : null,
                getenv('ProgramFiles') ? rtrim((string) getenv('ProgramFiles'), "\\/") . '\\Ollama\\ollama.exe' : null,
                getenv('ProgramW6432') ? rtrim((string) getenv('ProgramW6432'), "\\/") . '\\Ollama\\ollama.exe' : null,
            ];

            foreach ($candidats as $candidat) {
                if (is_string($candidat) && is_file($candidat)) {
                    return $candidat;
                }
            }

            return is_executable('ollama') ? 'ollama' : null;
        }

        $bin = trim((string) shell_exec('command -v ollama 2>/dev/null || which ollama 2>/dev/null'));
        if ($bin !== '') {
            return $bin;
        }

        $candidats = [
            '/usr/local/bin/ollama',
            '/opt/homebrew/bin/ollama',
            '/usr/bin/ollama',
        ];

        foreach ($candidats as $candidat) {
            if (is_file($candidat) && is_executable($candidat)) {
                return $candidat;
            }
        }

        return null;
    }
}
