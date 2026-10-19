<?php
/**
 * Vue PDF — Rapport IA d'un projet (Gestion 5, DomPDF).
 *
 * Contraintes DomPDF : tableaux HTML, pas de flexbox/grid/JS,
 * police DejaVu Sans, page-break-inside: avoid.
 *
 * Variables injectées :
 *  $project — modèle Project
 *  $rapport — ProjectReportService::genererRapport()
 *    ['success' => bool, 'source' => 'ollama'|'regles', 'message' => ?string,
 *     'sections' => [6 clés => texte], 'contexte' => [...]]
 */

$dt  = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ') . ' €';
$nb  = fn ($v, int $dec = 0) => number_format((float) $v, $dec, ',', ' ');
$pct = fn ($v) => number_format((float) $v, 1, ',', ' ') . ' %';

$c = $rapport['contexte'];
$libelleEcheance = fn (string $s) => match ($s) {
    'depassee' => 'Dépassée',
    'proche'   => 'Proche',
    'aucune'   => 'Non planifiée',
    default    => 'Dans les délais',
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1f2937; line-height: 1.45; }
    h1 { font-size: 17pt; margin: 0 0 2pt 0; color: #0f3d5c; }
    h2 { font-size: 12pt; color: #0f3d5c; border-bottom: 1.5pt solid #0f3d5c; padding-bottom: 2pt; margin: 14pt 0 6pt 0; page-break-after: avoid; }
    .meta { color: #6b7280; font-size: 8.5pt; margin-bottom: 10pt; }
    .badge { display: inline-block; padding: 2pt 8pt; border-radius: 8pt; font-size: 8pt; font-weight: bold; }
    .badge-ia { background: #dcfce7; color: #166534; }
    .badge-regles { background: #fef3c7; color: #92400e; }
    .alerte-box { background: #fef3c7; border: 1pt solid #d97706; padding: 6pt 9pt; margin: 8pt 0; font-size: 9pt; }
    table { width: 100%; border-collapse: collapse; margin: 6pt 0; page-break-inside: avoid; }
    th { background: #0f3d5c; color: #ffffff; text-align: left; padding: 4pt 6pt; font-size: 8.5pt; }
    td { border: 0.5pt solid #d1d5db; padding: 3.5pt 6pt; font-size: 8.5pt; vertical-align: top; }
    tr:nth-child(even) td { background: #f9fafb; }
    .fiche td:first-child { width: 32%; font-weight: bold; background: #f3f4f6; }
    ul { margin: 4pt 0 4pt 14pt; padding: 0; }
    li { margin-bottom: 2pt; }
    .section-texte { margin: 4pt 0 8pt 0; text-align: justify; }
    .disclaimer { background: #eff6ff; border: 0.5pt solid #3b82f6; padding: 6pt 9pt; font-size: 8pt; color: #1e3a8a; margin-top: 12pt; }
    .footer { margin-top: 14pt; padding-top: 6pt; border-top: 0.5pt solid #d1d5db; font-size: 7.5pt; color: #9ca3af; }
</style>
</head>
<body>

<h1>Rapport de projet — {{ $c['projet']['titre'] }}</h1>
<div class="meta">
    AquaSecure · Gestion 5 — Projets &amp; financement · Généré le {{ now()->format('d/m/Y à H:i') }}
    @if ($rapport['source'] === 'ollama')
        <span class="badge badge-ia">Rédaction : IA locale (Ollama)</span>
    @else
        <span class="badge badge-regles">Rédigé par règles — sans IA</span>
    @endif
</div>

@if (! $rapport['success'])
    <div class="alerte-box">
        <strong>IA locale indisponible.</strong> {{ $rapport['message'] }}
        Les chiffres ci-dessous restent calculés et vérifiés par l'application.
    </div>
@endif

<h2>1. Fiche signalétique</h2>
<table class="fiche">
    <tr><td>Titre</td><td>{{ $c['projet']['titre'] }}</td></tr>
    <tr><td>Type</td><td>{{ ucfirst($c['projet']['type']) }}</td></tr>
    <tr><td>Statut</td><td>{{ ucfirst(str_replace('_', ' ', $c['projet']['statut'])) }}</td></tr>
    <tr><td>Zone</td><td>{{ $c['zone'] ?? 'Non renseignée' }}{{ $c['commune_zone'] ? ' — '.$c['commune_zone'] : '' }}</td></tr>
    <tr><td>Infrastructure</td><td>{{ $c['infrastructure'] ?? 'Non renseignée' }}</td></tr>
    <tr><td>Responsable</td><td>{{ $c['responsable'] ?? 'Non renseigné' }}</td></tr>
    <tr><td>Démarrage</td><td>{{ $c['projet']['date_debut'] ?? 'Non renseigné' }}</td></tr>
    <tr><td>Fin prévue</td><td>{{ $c['projet']['date_fin_prevue'] ?? 'Non renseignée' }}</td></tr>
    <tr><td>Documents attachés</td><td>{{ $c['documents']['total'] }} (dont {{ $c['documents']['publics'] }} public(s))</td></tr>
</table>

<h2>2. Indicateurs chiffrés (calculés par l'application)</h2>
<table>
    <tr>
        <th>Indicateur</th><th>Valeur</th>
        <th>Indicateur</th><th>Valeur</th>
    </tr>
    <tr>
        <td>Budget prévu</td><td>{{ $dt($c['finances']['budget_prevu']) }}</td>
        <td>Avancement déclaré</td><td>{{ $pct($c['avancement']['pourcentage']) }}</td>
    </tr>
    <tr>
        <td>Coût des phases (estimation)</td><td>{{ $dt($c['finances']['cout_phases']) }}</td>
        <td>Taux de financement</td><td>{{ $pct($c['finances']['taux_financement']) }}</td>
    </tr>
    <tr>
        <td>Financements reçus</td><td>{{ $dt($c['finances']['financements_recus']) }}</td>
        <td>Phases</td><td>{{ $nb($c['avancement']['nombre_phases']) }} ({{ $nb($c['avancement']['phases_en_retard']) }} en retard)</td>
    </tr>
    <tr>
        <td>Reste à financer</td><td>{{ $dt($c['finances']['reste_a_financer']) }}</td>
        <td>Échéance du projet</td><td>{{ $libelleEcheance($c['avancement']['echeance_projet']) }}</td>
    </tr>
    @if ((float) $c['finances']['sur_financement'] > 0)
    <tr>
        <td>Sur-financement</td><td>{{ $dt($c['finances']['sur_financement']) }}</td>
        <td></td><td></td>
    </tr>
    @endif
</table>

@if ($c['phases'] !== [])
<h2>3. Phases</h2>
<table>
    <tr>
        <th>Phase</th><th>Entreprise</th><th>Début</th><th>Échéance</th>
        <th>Coût</th><th>Avancement</th><th>État échéance</th>
    </tr>
    @foreach ($c['phases'] as $phase)
    <tr>
        <td>{{ $phase['nom'] }}</td>
        <td>{{ $phase['entreprise'] ?? '—' }}</td>
        <td>{{ $phase['date_debut'] ?? '—' }}</td>
        <td>{{ $phase['date_fin'] ?? '—' }}</td>
        <td>{{ $dt($phase['cout']) }}</td>
        <td>{{ $nb($phase['avancement']) }} %</td>
        <td>{{ $libelleEcheance($phase['echeance']) }}</td>
    </tr>
    @endforeach
</table>
@endif

<h2>4. Constat des règles de contrôle (faits vérifiés)</h2>
@if ($c['alertes_regles'] === [])
    <p class="section-texte">Aucune alerte des règles de contrôle à partir des données enregistrées.</p>
@else
    <ul>
        @foreach ($c['alertes_regles'] as $alerte)
            <li>{{ $alerte }}</li>
        @endforeach
    </ul>
@endif

@foreach (\App\Services\ProjectReportService::SECTIONS as $cle => $titre)
    <h2>{{ $titre }}</h2>
    <p class="section-texte">{{ $rapport['sections'][$cle] }}</p>
@endforeach

<div class="disclaimer">
    <strong>Nature des recommandations :</strong> les propositions de la section « Prochaines actions suggérées »
    sont des conseils consultatives issus de l'analyse — elles ne constituent pas des faits vérifiés ni des
    décisions engageantes, et aucune donnée du projet n'a été modifiée par la génération de ce rapport.
    @if ($c['informations_manquantes'] !== [])
        <br><strong>Informations manquantes signalées :</strong> {{ implode(', ', $c['informations_manquantes']) }}.
    @endif
</div>

<div class="footer">
    Document interne du Back Office AquaSecure (Gestion 5) — généré à la demande, non archivé.
    Source de rédaction : {{ $rapport['source'] === 'ollama' ? 'IA locale Ollama (modèle '.config('services.local_llm.model', 'llama3.2').')' : 'règles déterministes locales' }}.
</div>

</body>
</html>
