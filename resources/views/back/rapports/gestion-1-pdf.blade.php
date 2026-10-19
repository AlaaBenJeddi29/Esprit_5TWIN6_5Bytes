<?php
/**
 * Vue PDF — Rapport global Gestion 1 (DomPDF).
 *
 * Contraintes DomPDF :
 *  - pas de flexbox, pas de grid, pas de JavaScript
 *  - tableaux HTML pour toute mise en page multi-colonnes
 *  - page-break-inside: avoid  pour ne jamais couper un bloc
 *  - police DejaVu Sans (supporte les accents)
 *  - pied de page via script PHP intégré (remplacé par DomPDF)
 *
 * Variables injectées :
 *  $donnees  — GestionReportService::donnees()
 *  $texte    — analyse IA ou texte de secours (string)
 *  $source   — 'ollama' | 'secours'
 */

/* ── Helpers d'affichage ────────────────────────────────────────────────── */
$dt     = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ') . ' DT';
$nb     = fn ($v, int $dec = 0) => $v === null ? '—' : number_format((float) $v, $dec, ',', ' ');
$pct    = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', ' ') . ' %';

/* Couleur du score selon le niveau */
$couleurScore = fn (?float $s) => match (true) {
    $s === null => '#6b7280',
    $s <= 30    => '#1a6f3c',
    $s <= 60    => '#b45309',
    $s <= 80    => '#c0392b',
    default     => '#7a0c0c',
};

/* Badge de fond selon le niveau */
$bgScore = fn (?float $s) => match (true) {
    $s === null => '#f3f4f6',
    $s <= 30    => '#dcfce7',
    $s <= 60    => '#fef3c7',
    $s <= 80    => '#fee2e2',
    default     => '#fca5a5',
};

$libelleNiveau = fn (string $n) => match ($n) {
    'faible'   => 'Faible',
    'modere'   => 'Modéré',
    'eleve'    => 'Élevé',
    'critique' => 'Critique',
    'aucun'    => 'Non évalué',
    default    => ucfirst($n),
};

/* Convertit les lignes commençant par "- " en items <li> */
$formatTexte = function (string $texte) : string {
    $html   = '';
    $inList = false;

    foreach (explode("\n", $texte) as $ligne) {
        $ligne = htmlspecialchars(trim($ligne), ENT_QUOTES, 'UTF-8');

        if ($ligne === '') {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            continue;
        }

        /* Titres de sections (pas de tiret, texte court, pas de chiffre initial) */
        if (
            in_array($ligne, [
                'Synthèse globale',
                'Analyse des risques par zone prioritaire',
                'Analyse des coûts de maintenance',
                'Recommandations',
            ], true)
        ) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<p style="font-weight:bold;font-size:9.5pt;color:#0f6e86;margin:10px 0 4px 0;">' . $ligne . '</p>';
            continue;
        }

        /* Lignes de liste (commencent par "- " ou "– ") */
        if (preg_match('/^[-–]\s+(.+)$/', $ligne, $m)) {
            if (! $inList) { $html .= '<ul style="margin:0 0 0 14px;padding:0;">'; $inList = true; }
            $html .= '<li style="margin-bottom:2px;">' . $m[1] . '</li>';
            continue;
        }

        if ($inList) { $html .= '</ul>'; $inList = false; }
        $html .= '<p style="margin:0 0 4px 0;">' . $ligne . '</p>';
    }

    if ($inList) { $html .= '</ul>'; }

    return $html;
};

$i       = $donnees['indicateurs'];
$m       = $donnees['maintenances'];
$maxMens = $m['couts_mensuels'] !== [] ? max(array_merge(array_values($m['couts_mensuels']), [0.01])) : 0.01;
$maxZone = array_reduce($m['couts_par_zone'], fn ($acc, $z) => max($acc, (float) $z['cout']), 0.01);

$dateGen = now()->locale('fr')->translatedFormat('d F Y') . ' à ' . now()->format('H:i');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport global — Gestion 1</title>
    <style>
        /* ── Réinitialisation ── */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        /* ── Corps ── */
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9pt;
            color: #1e293b;
            background: #ffffff;
        }

        /* ── En-tête du rapport (bandeau teal compact) ── */
        .bandeau {
            background: #0f6e86;
            color: #ffffff;
            padding: 14px 18px 12px 18px;
            margin-bottom: 16px;
        }
        .bandeau-titre {
            font-size: 15pt;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .bandeau-sous-titre {
            font-size: 9.5pt;
            margin-top: 3px;
            color: #cce8ef;
        }
        .bandeau-date {
            font-size: 8pt;
            color: #a8d4dd;
            margin-top: 2px;
        }

        /* ── Titres de section ── */
        h2 {
            font-size: 11pt;
            font-weight: bold;
            color: #ffffff;
            background: #0f6e86;
            padding: 5px 8px;
            margin: 14px 0 8px 0;
            page-break-after: avoid;
        }
        h3 {
            font-size: 9.5pt;
            font-weight: bold;
            color: #0f6e86;
            border-bottom: 1px solid #b0d4dc;
            padding-bottom: 2px;
            margin: 10px 0 5px 0;
            page-break-after: avoid;
        }

        /* ── Tableaux ── */
        table {
            border-collapse: collapse;
            width: 100%;
        }
        thead tr th {
            background: #0f6e86;
            color: #ffffff;
            font-size: 8pt;
            font-weight: bold;
            padding: 5px 7px;
            text-align: left;
            border: 1px solid #0a5568;
        }
        thead {
            /* répétition sur chaque page DomPDF */
            display: table-header-group;
        }
        tbody tr td {
            font-size: 8.5pt;
            padding: 4px 7px;
            border-bottom: 1px solid #e2e8f0;
            border-left: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        /* Lignes zébrées */
        tbody tr:nth-child(even) td { background: #f0f7f9; }
        tbody tr:nth-child(odd)  td { background: #ffffff; }

        /* Nombres alignés à droite */
        .num { text-align: right; }

        /* ── Bloc texte IA ── */
        .bloc-ia {
            border-left: 4px solid #0f6e86;
            background: #f4f9fb;
            padding: 10px 14px;
            font-size: 8.5pt;
            line-height: 1.5;
            page-break-inside: avoid;
        }

        /* ── Cartes indicateurs ── */
        .kpi-table { width: 100%; border-collapse: collapse; }
        .kpi-table td {
            text-align: center;
            padding: 10px 6px;
            border: 1px solid #d1e9ee;
            background: #f0f8fa;
            vertical-align: middle;
        }
        .kpi-valeur {
            font-size: 16pt;
            font-weight: bold;
            line-height: 1.1;
        }
        .kpi-label {
            font-size: 7.5pt;
            color: #475569;
            margin-top: 2px;
        }

        /* ── Barres horizontales ── */
        .barre-fond { background: #dde8ec; width: 100%; height: 8px; }
        .barre       { background: #0f6e86; height: 8px; }

        /* ── Sauts de page ── */
        .saut { page-break-before: always; }
        .no-cut { page-break-inside: avoid; }

        /* ── Notes de bas de section ── */
        .note {
            font-size: 7.5pt;
            color: #64748b;
            font-style: italic;
            margin-top: 6px;
        }

        /* ── Badges inline ── */
        .badge {
            display: inline;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: bold;
        }
    </style>
</head>
<body>

{{-- ══════════════════════════════════════════════════════════════════════
     BANDEAU D'EN-TÊTE COMPACT (remplace la page de titre avec grand espace)
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="bandeau">
    <div class="bandeau-titre">Rapport global — Gestion 1</div>
    <div class="bandeau-sous-titre">Infrastructures et cartographie du réseau d'eau potable · AquaSecure</div>
    <div class="bandeau-date">Généré le {{ $dateGen }}</div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     1. INDICATEURS CLÉS
     ══════════════════════════════════════════════════════════════════════ --}}
<h2>1. Indicateurs clés</h2>

<div class="no-cut">
    <table class="kpi-table">
        <tr>
            {{-- Zones --}}
            <td style="width:16.6%;">
                <div class="kpi-valeur" style="color:#0f6e86;">{{ $nb($i['nb_zones']) }}</div>
                <div class="kpi-label">Zones</div>
            </td>
            {{-- Infrastructures --}}
            <td style="width:16.6%;">
                <div class="kpi-valeur" style="color:#0f6e86;">{{ $nb($i['nb_infrastructures']) }}</div>
                <div class="kpi-label">Infrastructures</div>
            </td>
            {{-- Population --}}
            <td style="width:16.6%;">
                <div class="kpi-valeur" style="color:#0f6e86;">{{ $nb($i['population_totale']) }}</div>
                <div class="kpi-label">Habitants desservis</div>
            </td>
            {{-- Risque moyen --}}
            <td style="width:16.6%;">
                @php $rm = $i['risque_moyen']; @endphp
                <div class="kpi-valeur" style="color:{{ $couleurScore($rm ? (float)$rm : null) }};">
                    {{ $nb($rm, 1) }}
                </div>
                <div class="kpi-label">Risque moyen (/100)</div>
            </td>
            {{-- Critiques : rouge si > 0, vert si 0 --}}
            <td style="width:16.6%;">
                <div class="kpi-valeur" style="color:{{ $i['nb_critiques'] > 0 ? '#c0392b' : '#1a6f3c' }};">
                    {{ $nb($i['nb_critiques']) }}
                </div>
                <div class="kpi-label">Critiques&nbsp;({{ $pct($i['pct_critiques']) }})</div>
            </td>
            {{-- Prévision 3 mois --}}
            <td style="width:16.6%;">
                <div class="kpi-valeur" style="font-size:12pt;color:#0f6e86;">{{ $dt($i['prevision_3_mois']) }}</div>
                <div class="kpi-label">Prévision 3 mois</div>
            </td>
        </tr>
    </table>

    <p class="note">
        Coût de maintenance constaté sur les 6 derniers mois :
        <strong>{{ $dt($i['cout_total_6_mois']) }}</strong>
        (préventif : {{ $dt($m['cout_preventif']) }} · correctif : {{ $dt($m['cout_correctif']) }}).
        {{ $nb($i['nb_sans_score']) }} infrastructure(s) sans score de risque.
    </p>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     2. ANALYSE ET RECOMMANDATIONS (texte IA ou secours)
     ══════════════════════════════════════════════════════════════════════ --}}
<h2>2. Analyse et recommandations</h2>

<div class="bloc-ia no-cut">
    {!! $formatTexte($texte) !!}
</div>

<p class="note">
    @if ($source === 'ollama')
        Texte rédigé par l'IA locale Ollama (modèle {{ config('services.ollama.model') }}) — à valider par le gestionnaire.
    @else
        IA locale indisponible : analyse générée automatiquement par règles — à valider par le gestionnaire.
    @endif
</p>

{{-- ══════════════════════════════════════════════════════════════════════
     3. CLASSEMENT DES ZONES (nouvelle page)
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="saut"></div>
<h2>3. Classement des zones</h2>

@if ($donnees['zones'] === [])
    <p style="color:#64748b;font-size:8.5pt;">Aucune zone enregistrée dans le réseau.</p>
@else
    <div class="no-cut">
        <table>
            <thead>
                <tr>
                    <th style="width:3%;">#</th>
                    <th style="width:20%;">Zone</th>
                    <th style="width:13%;">Commune</th>
                    <th style="width:9%;">Population</th>
                    <th style="width:7%;">Infra.</th>
                    <th style="width:9%;">Risque moy.</th>
                    <th style="width:7%;">Crit.</th>
                    <th style="width:14%;">Score de zone</th>
                    <th style="width:13%;">Coût 6 mois</th>
                    <th style="width:13%;">Prév. 3 mois</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($donnees['zones'] as $rang => $zone)
                    <tr>
                        <td class="num">{{ $rang + 1 }}</td>
                        <td style="font-weight:bold;">{{ $zone['nom'] }}</td>
                        <td>{{ $zone['commune'] }}</td>
                        <td class="num">{{ $nb($zone['population']) }}</td>
                        <td class="num">{{ $nb($zone['infrastructures']) }}</td>
                        <td class="num">{{ $nb($zone['risque_moyen'], 1) }}</td>
                        <td class="num"
                            style="font-weight:bold;color:{{ $zone['critiques'] > 0 ? '#c0392b' : '#1a6f3c' }};">
                            {{ $nb($zone['critiques']) }}
                        </td>
                        <td class="num">
                            @if ($zone['score_zone'] !== null)
                                <span class="badge"
                                      style="background:{{ $bgScore((float)$zone['score_zone']) }};color:{{ $couleurScore((float)$zone['score_zone']) }};">
                                    {{ $nb($zone['score_zone'], 1) }}
                                </span>
                                <span style="font-size:7.5pt;color:#64748b;">
                                    &nbsp;{{ $libelleNiveau($zone['niveau']) }}
                                </span>
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </td>
                        <td class="num">{{ $dt($zone['cout_6_mois'] ?? null) }}</td>
                        <td class="num">{{ $dt($zone['prevision_3_mois']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="note">
            Score de zone = 0,6 × risque moyen + 0,4 × pourcentage d'infrastructures critiques.
            Seules les maintenances au statut « terminee » des 6 derniers mois sont comptabilisées.
        </p>
    </div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════
     4. TOP 10 DES INFRASTRUCTURES À RISQUE
     ══════════════════════════════════════════════════════════════════════ --}}
<h2>4. Top 10 des infrastructures à risque</h2>

@if ($donnees['top_infrastructures'] === [])
    <p style="color:#64748b;font-size:8.5pt;">Aucune infrastructure avec un score de risque renseigné.</p>
@else
    <div class="no-cut">
        <table>
            <thead>
                <tr>
                    <th style="width:3%;">#</th>
                    <th style="width:24%;">Infrastructure</th>
                    <th style="width:18%;">Zone</th>
                    <th style="width:13%;">Type</th>
                    <th style="width:11%;">Matériau</th>
                    <th style="width:8%;">Âge (ans)</th>
                    <th style="width:12%;">Statut</th>
                    <th style="width:8%;">Score</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($donnees['top_infrastructures'] as $rang => $inf)
                    <tr>
                        <td class="num">{{ $rang + 1 }}</td>
                        <td style="font-weight:bold;">{{ $inf['nom'] }}</td>
                        <td>{{ $inf['zone'] }}</td>
                        <td>{{ $inf['type'] }}</td>
                        <td>{{ $inf['materiau'] ?? '—' }}</td>
                        <td class="num">{{ $inf['age'] === null ? '—' : $nb($inf['age']) }}</td>
                        <td>{{ $inf['statut'] }}</td>
                        <td class="num">
                            <span class="badge"
                                  style="background:{{ $bgScore((float)$inf['score']) }};color:{{ $couleurScore((float)$inf['score']) }};">
                                {{ $nb($inf['score'], 1) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════
     5. RÉPARTITIONS (nouvelle page)
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="saut"></div>
<h2>5. Répartitions du parc d'infrastructures</h2>

@php
    /* Affiche un tableau de répartition avec barre CSS */
    $ligneRep = function (string $label, int $effectif, float $pourcentage) use ($nb) : string {
        $larg = min(100, max(0, $pourcentage));
        return '<tr>'
            . '<td style="width:36%;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="width:9%;text-align:right;">' . $nb($effectif) . '</td>'
            . '<td style="width:10%;text-align:right;">' . $nb($pourcentage, 1) . '&nbsp;%</td>'
            . '<td style="width:45%;vertical-align:middle;padding-left:6px;">'
            .   '<div class="barre-fond"><div class="barre" style="width:' . $larg . '%;"></div></div>'
            . '</td>'
            . '</tr>';
    };
@endphp

@foreach ([
    'Par niveau de risque' => $donnees['repartitions']['niveaux'],
    'Par type'             => $donnees['repartitions']['types'],
    'Par matériau'         => $donnees['repartitions']['materiaux'],
    'Par statut'           => $donnees['repartitions']['statuts'],
] as $titreRep => $repItems)
    <div class="no-cut">
        <h3>{{ $titreRep }}</h3>
        @if ($repItems === [])
            <p style="color:#64748b;font-size:8.5pt;">Aucune infrastructure.</p>
        @else
            <table>
                <tbody>
                    @foreach ($repItems as $item)
                        {!! $ligneRep($item['label'], $item['nombre'], $item['pourcentage']) !!}
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endforeach

{{-- ══════════════════════════════════════════════════════════════════════
     6. MAINTENANCES ET COÛTS (nouvelle page)
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="saut"></div>
<h2>6. Maintenances et coûts — 6 derniers mois</h2>

{{-- Préventives / correctives --}}
<div class="no-cut">
    <h3>Préventives et correctives</h3>
    <table>
        <thead>
            <tr>
                <th style="width:40%;">Type d'intervention</th>
                <th style="width:20%;">Nombre</th>
                <th style="width:40%;">Coût total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Préventives</td>
                <td class="num">{{ $nb($m['preventives']) }}</td>
                <td class="num"><strong>{{ $dt($m['cout_preventif']) }}</strong></td>
            </tr>
            <tr>
                <td>Correctives / curatives</td>
                <td class="num">{{ $nb($m['correctives']) }}</td>
                <td class="num"><strong>{{ $dt($m['cout_correctif']) }}</strong></td>
            </tr>
            <tr>
                <td style="font-weight:bold;background:#e0f0f4;">Total</td>
                <td class="num" style="font-weight:bold;background:#e0f0f4;">
                    {{ $nb($m['preventives'] + $m['correctives']) }}
                </td>
                <td class="num" style="font-weight:bold;background:#e0f0f4;color:#0f6e86;">
                    {{ $dt($m['cout_preventif'] + $m['cout_correctif']) }}
                </td>
            </tr>
        </tbody>
    </table>
</div>

{{-- Coûts mensuels --}}
<div class="no-cut">
    <h3>Coûts mensuels</h3>
    @if ($m['couts_mensuels'] === [])
        <p style="color:#64748b;font-size:8.5pt;">Aucune maintenance terminée sur les 6 derniers mois.</p>
    @else
        <table>
            <tbody>
                @foreach ($m['couts_mensuels'] as $moisClef => $coutMois)
                    @php
                        $dateMois = \Illuminate\Support\Carbon::createFromFormat('Y-m', $moisClef)->locale('fr');
                        $largMens = $maxMens > 0 ? min(100, round($coutMois / $maxMens * 100)) : 0;
                    @endphp
                    <tr>
                        <td style="width:20%;">{{ $dateMois->translatedFormat('F Y') }}</td>
                        <td style="width:20%;text-align:right;font-weight:bold;">{{ $dt($coutMois) }}</td>
                        <td style="width:60%;vertical-align:middle;padding-left:6px;">
                            <div class="barre-fond">
                                <div class="barre" style="width:{{ $largMens }}%;"></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- Coûts par zone --}}
<div class="no-cut">
    <h3>Coûts par zone</h3>
    @if ($m['couts_par_zone'] === [])
        <p style="color:#64748b;font-size:8.5pt;">Aucun coût de maintenance sur la période.</p>
    @else
        <table>
            <tbody>
                @foreach ($m['couts_par_zone'] as $ligneZone)
                    @php $largZone = $maxZone > 0 ? min(100, round($ligneZone['cout'] / $maxZone * 100)) : 0; @endphp
                    <tr>
                        <td style="width:34%;">{{ $ligneZone['zone'] }}</td>
                        <td style="width:20%;text-align:right;font-weight:bold;">{{ $dt($ligneZone['cout']) }}</td>
                        <td style="width:46%;vertical-align:middle;padding-left:6px;">
                            <div class="barre-fond">
                                <div class="barre" style="width:{{ $largZone }}%;"></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="note">
        Prévision indicative basée sur la moyenne des 3 derniers mois.
        Prévision globale sur 3 mois : <strong>{{ $dt($i['prevision_3_mois']) }}</strong>.
    </p>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     7. ANOMALIES DE COÛTS
     ══════════════════════════════════════════════════════════════════════ --}}
<h2>7. Anomalies de coûts</h2>

@if ($donnees['anomalies'] === [])
    <p style="color:#64748b;font-size:8.5pt;">Aucune anomalie détectée sur la période analysée.</p>
@else
    <div class="no-cut">
        <p style="font-size:8.5pt;margin-bottom:5px;">
            Maintenances dont le coût dépasse la moyenne + 2 × écart-type du même type
            (10 plus élevées affichées, calcul sur ≥ 5 interventions par type).
        </p>
        <table>
            <thead>
                <tr>
                    <th style="width:24%;">Infrastructure</th>
                    <th style="width:18%;">Zone</th>
                    <th style="width:13%;">Type</th>
                    <th style="width:12%;">Date</th>
                    <th style="width:14%;">Coût constaté</th>
                    <th style="width:14%;">Seuil anomalie</th>
                    <th style="width:5%;">Écart</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($donnees['anomalies'] as $anomalie)
                    @php
                        $ecartPct = $anomalie['seuil'] > 0
                            ? round(($anomalie['cout'] - $anomalie['seuil']) / $anomalie['seuil'] * 100)
                            : 0;
                    @endphp
                    <tr>
                        <td>{{ $anomalie['infrastructure'] }}</td>
                        <td>{{ $anomalie['zone'] }}</td>
                        <td>{{ $anomalie['type'] }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($anomalie['date'])->format('d/m/Y') }}</td>
                        <td class="num" style="font-weight:bold;color:#c0392b;">{{ $dt($anomalie['cout']) }}</td>
                        <td class="num">{{ $dt($anomalie['seuil']) }}</td>
                        <td class="num" style="color:#c0392b;">+{{ $ecartPct }}&nbsp;%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════
     8. MÉTHODOLOGIE
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="saut"></div>
<h2>8. Méthodologie</h2>

<div class="no-cut">
    <table>
        <tbody>
            <tr>
                <td style="width:22%;font-weight:bold;background:#e0f0f4;color:#0f6e86;">Score de zone</td>
                <td>0,6 × risque moyen des infrastructures + 0,4 × pourcentage d'infrastructures critiques
                    (score &gt; 80). Niveaux : Faible ≤ 30 · Modéré ≤ 60 · Élevé ≤ 80 · Critique au-delà.</td>
            </tr>
            <tr>
                <td style="font-weight:bold;background:#e0f0f4;color:#0f6e86;">Prévision des coûts</td>
                <td>Moyenne des coûts terminés des 3 derniers mois × 3.
                    Un mois sans intervention compte 0. Prévision indicative — remplaçable par régression linéaire.</td>
            </tr>
            <tr>
                <td style="font-weight:bold;background:#e0f0f4;color:#0f6e86;">Anomalies</td>
                <td>Coût supérieur à moyenne + 2 × écart-type des maintenances terminées du même type.
                    Calcul activé à partir de 5 interventions par type.</td>
            </tr>
            <tr>
                <td style="font-weight:bold;background:#e0f0f4;color:#0f6e86;">Périmètre</td>
                <td>Tables zones, infrastructures et maintenances uniquement.
                    Seuls les coûts des maintenances au statut « terminee » des 6 derniers mois sont comptabilisés.</td>
            </tr>
        </tbody>
    </table>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     PIED DE PAGE — DomPDF remplace {PAGE_NUM} et {PAGE_COUNT}
     ══════════════════════════════════════════════════════════════════════ --}}
<script type="text/php">
    if (isset($pdf)) {
        $font  = $fontMetrics->get_font("DejaVu Sans", "normal");
        $size  = 7;
        $color = [0.40, 0.47, 0.54];

        // Pied gauche : nom du rapport + date
        $pdf->page_text(
            36, 818,
            "AquaSecure — Rapport Gestion 1 · {{ now()->format('d/m/Y') }}",
            $font, $size, $color
        );

        // Pied droit : numéro de page
        $pdf->page_text(
            460, 818,
            "Page {PAGE_NUM} / {PAGE_COUNT}",
            $font, $size, $color
        );
    }
</script>

</body>
</html>
