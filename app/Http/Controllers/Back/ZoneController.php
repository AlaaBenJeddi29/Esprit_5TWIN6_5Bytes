<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Infrastructure\StoreZoneRequest;
use App\Http\Requests\Infrastructure\UpdateZoneRequest;
use App\Mail\RapportGestionMailable;
use App\Models\Infrastructure\Maintenance;
use App\Models\Infrastructure\Zone;
use App\Services\GestionReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ZoneController extends Controller
{
    use ResolvesListingFilters;

    /**
     * Colonnes autorisées pour le tri (liste blanche contre injection SQL).
     *
     * @var array<string, string>
     */
    private const COLONNES_TRI = [
        'nom'             => 'nom',
        'population'      => 'population',
        'risque_moyen'    => 'risque_moyen',
        'critiques_count' => 'critiques_count',
        'score_zone'      => 'score_zone',
    ];

    /**
     * Alias d'agrégats calculés (ORDER BY via orderByRaw, HAVING si filtre).
     *
     * @var list<string>
     */
    private const ALIAS_AGREGATS = ['risque_moyen', 'critiques_count', 'score_zone'];

    /**
     * Nombre de mois d'historique utilisés pour la prévision des coûts.
     */
    private const MOIS_HISTORIQUE = 6;

    /**
     * Statut indiquant qu'une maintenance est terminée.
     * Correspond à la valeur utilisée dans le formulaire et le DemoDataSeeder.
     */
    private const STATUT_TERMINEE = 'terminee';

    public function index(Request $request): View
    {
        // --- Paramètres de recherche / filtre / tri ---
        $search  = $request->string('search')->toString();
        $risque  = $request->string('risque')->toString();   // faible | modere | eleve | critique
        $tri     = self::COLONNES_TRI[$request->string('tri')->toString()] ?? 'nom';
        $sens    = $request->string('sens')->toString() === 'desc' ? 'desc' : 'asc';
        $perPage = $this->listingPerPage($request);

        // --- Requête principale (agrégats via sous-requêtes corrélées, zéro N+1) ---
        // Pas de jointure ni de GROUP BY : compatible MySQL ET MariaDB (XAMPP),
        // et le count de la pagination reste valide.
        $zones = Zone::query()
            // Nombre total d'infrastructures de la zone
            ->withCount('infrastructures')
            // Nombre d'infrastructures à risque critique (score > 80)
            ->withCount([
                'infrastructures as critiques_count' => fn ($q) => $q->where('score_risque', '>', 80),
            ])
            // Score de risque moyen de la zone
            ->withAvg('infrastructures as risque_moyen', 'score_risque')
            // Recherche textuelle : nom, commune, code postal
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('zones.nom', 'like', "%{$search}%")
                        ->orWhere('zones.commune', 'like', "%{$search}%")
                        ->orWhere('zones.code_postal', 'like', "%{$search}%");
                });
            })
            // Filtre par niveau de risque : sous-requête corrélée (pas de HAVING nécessaire)
            ->when($risque !== '', function ($query) use ($risque) {
                $avg = '(select avg(infrastructures.score_risque) from infrastructures where infrastructures.zone_id = zones.id)';
                match ($risque) {
                    'faible'   => $query->whereRaw("{$avg} is not null")->whereRaw("{$avg} <= 30"),
                    'modere'   => $query->whereRaw("{$avg} > 30")->whereRaw("{$avg} <= 60"),
                    'eleve'    => $query->whereRaw("{$avg} > 60")->whereRaw("{$avg} <= 80"),
                    'critique' => $query->whereRaw("{$avg} > 80"),
                    default    => null,
                };
            })
            // Tri : alias d'agrégat ou expression du score de zone → orderByRaw ; colonne native → orderBy
            ->when(
                in_array($tri, self::ALIAS_AGREGATS, true),
                fn ($q) => $q->orderByRaw(self::expressionTri($tri, $sens)),
                fn ($q) => $q->orderBy("zones.{$tri}", $sens),
            )
            ->paginate($perPage)
            ->withQueryString();

        // --- Bloc "Zones prioritaires" : top 3 toutes zones confondues (hors pagination) ---
        // score_zone est recalculé par l'accessor du modèle à partir des agrégats chargés.
        $zonesPrioritaires = Zone::query()
            ->withCount('infrastructures')
            ->withCount([
                'infrastructures as critiques_count' => fn ($q) => $q->where('score_risque', '>', 80),
            ])
            ->withAvg('infrastructures as risque_moyen', 'score_risque')
            ->get()
            ->filter(fn (Zone $zone) => $zone->score_zone !== null)
            ->sortByDesc('score_zone')
            ->take(3)
            ->values();

        // --- Prévision des coûts de maintenance : une seule requête pour toutes les zones ---
        // On récupère le total mensuel des maintenances terminées des 6 derniers mois,
        // regroupées par zone et par mois (DATE_FORMAT : syntaxe MySQL/XAMPP).
        $depuisMois = now()->subMonths(self::MOIS_HISTORIQUE)->startOfMonth()->toDateString();

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

        // Structurer en [ zone_id => ['YYYY-MM' => float, ...] ]
        $historiqueParZone = [];
        foreach ($coutsBruts as $ligne) {
            $historiqueParZone[$ligne->zone_id][$ligne->mois] = (float) $ligne->total_cout;
        }

        // Calculer la prévision pour chaque zone (délégué au modèle Zone)
        $previsionParZone = [];
        $zoneModele = new Zone();
        foreach ($historiqueParZone as $zoneId => $historique) {
            // Compléter avec les mois du calendrier sans maintenance (= 0)
            $previsionParZone[$zoneId] = $zoneModele->previsionCoutMaintenance(
                $this->completerMoisSansDonnees($historique),
            );
        }

        return view('back.zones.index', compact(
            'zones',
            'search',
            'risque',
            'tri',
            'sens',
            'perPage',
            'zonesPrioritaires',
            'previsionParZone',
        ));
    }

    /**
     * Expression SQL de tri pour les alias d'agrégats.
     *
     * risque_moyen et critiques_count sont des alias de sous-requêtes déjà
     * présentés dans le SELECT (autorisés dans l'ORDER BY en MySQL/MariaDB).
     * score_zone n'étant pas sélectionné explicitement, on répète l'expression
     * complète du score : 0,6 × risque moyen + 0,4 × pourcentage de critiques.
     * NULLIF évite la division par zéro pour une zone sans infrastructure.
     *
     * @param  string  $tri   Alias d'agrégat (déjà validé par la liste blanche)
     * @param  string  $sens  'asc' ou 'desc'
     */
    private static function expressionTri(string $tri, string $sens): string
    {
        if ($tri !== 'score_zone') {
            return "{$tri} {$sens}";
        }

        $scoreZoneSql = '(0.6 * (select avg(infrastructures.score_risque) from infrastructures where infrastructures.zone_id = zones.id)'
            .' + 0.4 * ((select count(*) from infrastructures where infrastructures.zone_id = zones.id and infrastructures.score_risque > 80)'
            .' / nullif((select count(*) from infrastructures where infrastructures.zone_id = zones.id), 0)) * 100)';

        return "round({$scoreZoneSql}, 1) {$sens}";
    }

    public function create(): View
    {
        return view('back.zones.create', ['zone' => new Zone()]);
    }

    /**
     * Rapport global PDF de la Gestion 1 : indicateurs, analyse IA locale
     * (Ollama, avec texte de secours), classement des zones, top 10 des
     * infrastructures à risque, répartitions, coûts et anomalies.
     *
     * Le PDF généré est envoyé par email au gestionnaire ou administrateur
     * actif qui le demande, puis téléchargé.
     */
    public function rapportGlobal(GestionReportService $service): Response
    {
        // La génération IA sans carte graphique peut dépasser les 30 s PHP par défaut
        set_time_limit(240);

        // Si Ollama ne répond pas, tenter de le démarrer en arrière-plan (échec silencieux)
        $service->demarrerOllamaSiNecessaire();

        $donnees = $service->donnees();
        $texte   = $service->texteIA($donnees);

        $nomFichier = 'rapport-gestion-1-'.now()->format('Y-m-d').'.pdf';

        $pdf = Pdf::loadView('back.rapports.gestion-1-pdf', [
            'donnees' => $donnees,
            'texte'   => $texte['texte'],
            'source'  => $texte['source'],
        ])->setPaper('a4');

        $contenuPdf = $pdf->output();

        // Envoi automatique par email (avant le téléchargement)
        $this->envoyerRapportParEmail($contenuPdf, $nomFichier);

        return response($contenuPdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nomFichier.'"',
        ]);
    }

    /**
     * Envoie le PDF uniquement au gestionnaire ou administrateur actif qui l'a
     * demandé.
     *
     * Les échecs d'envoi sont journalisés sans interrompre le téléchargement.
     *
     * @param  string  $contenuPdf  Contenu binaire du PDF (sortie de DomPDF)
     * @param  string  $nomFichier  Nom de fichier affiché dans la pièce jointe
     */
    private function envoyerRapportParEmail(string $contenuPdf, string $nomFichier): void
    {
        $user = request()->user();
        if ($user === null || ! $user->is_active || ! $user->canAccessBackOffice() || blank($user->email)) {
            Log::warning('Rapport Gestion 1 : aucun demandeur actif avec une adresse email valide.');

            return;
        }

        try {
            Mail::to($user->email)->send(
                new RapportGestionMailable($contenuPdf, $nomFichier),
            );
        } catch (\Throwable $e) {
            // L'envoi échoue (SMTP non configuré, réseau, etc.) : on journalise
            // sans bloquer le téléchargement du rapport par l'utilisateur.
            report($e);
            Log::error('Rapport Gestion 1 : échec d\'envoi email — '.$e->getMessage());
        }
    }

    public function store(StoreZoneRequest $request): RedirectResponse
    {
        $zone = Zone::create($request->validated());

        return redirect()
            ->route('back.zones.show', $zone)
            ->with('success', "La zone « {$zone->nom} » a été créée.");
    }

    public function show(Request $request, Zone $zone): View
    {
        $zone->loadCount('infrastructures');

        $infrastructures = $zone->infrastructures()
            ->orderBy('nom')
            ->paginate($this->listingPerPage($request), ['*'], 'infra_page')
            ->withQueryString();

        // --- Historique des coûts de maintenance des 6 derniers mois pour cette zone ---
        $depuisMois = now()->subMonths(self::MOIS_HISTORIQUE)->startOfMonth()->toDateString();

        $historiqueBrut = DB::table('maintenances')
            ->join('infrastructures', 'infrastructures.id', '=', 'maintenances.infrastructure_id')
            ->select([
                DB::raw("DATE_FORMAT(maintenances.date_intervention, '%Y-%m') AS mois"),
                DB::raw('SUM(maintenances.cout) AS total_cout'),
            ])
            ->where('infrastructures.zone_id', $zone->id)
            ->where('maintenances.statut', self::STATUT_TERMINEE)
            ->where('maintenances.date_intervention', '>=', $depuisMois)
            ->whereNotNull('maintenances.cout')
            ->groupBy('mois')
            ->orderBy('mois')
            ->pluck('total_cout', 'mois')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        // Compléter le calendrier des 6 derniers mois (mois sans maintenance = 0)
        $historiqueBrut = $this->completerMoisSansDonnees($historiqueBrut);

        // Calculer la prévision via la méthode du modèle
        $prevision = $zone->previsionCoutMaintenance($historiqueBrut);

        // Générer les 3 mois prévus avec leur étiquette pour l'affichage
        $moisPrevus = [];
        for ($i = 1; $i <= 3; $i++) {
            $moisPrevus[now()->addMonths($i)->format('Y-m')] = $prevision !== null
                ? round($prevision / 3, 2)  // coût moyen mensuel pour affichage
                : null;
        }

        return view('back.zones.show', compact('zone', 'infrastructures', 'historiqueBrut', 'prevision', 'moisPrevus'));
    }

    public function edit(Zone $zone): View
    {
        return view('back.zones.edit', compact('zone'));
    }

    public function update(UpdateZoneRequest $request, Zone $zone): RedirectResponse
    {
        $zone->update($request->validated());

        return redirect()
            ->route('back.zones.show', $zone)
            ->with('success', "La zone « {$zone->nom} » a été mise à jour.");
    }

    public function destroy(Zone $zone): RedirectResponse
    {
        // Refuser la suppression si la zone contient des infrastructures
        if ($zone->infrastructures()->exists()) {
            return redirect()
                ->route('back.zones.index')
                ->with('error', "Impossible de supprimer la zone « {$zone->nom} » : elle contient des infrastructures. Supprimez ou déplacez celles-ci avant de supprimer la zone.");
        }

        $nom = $zone->nom;
        $zone->delete();

        return redirect()
            ->route('back.zones.index')
            ->with('success', "La zone « {$nom} » a été supprimée.");
    }

    /**
     * Compléter un historique mensuel ['YYYY-MM' => coût] avec les mois du
     * calendrier des MOIS_HISTORIQUE derniers mois qui n'ont aucune maintenance
     * (ils valent 0). Retourne un tableau trié du plus ancien au plus récent.
     *
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
}
