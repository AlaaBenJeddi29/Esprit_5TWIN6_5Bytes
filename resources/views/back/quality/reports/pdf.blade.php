@php
    $fmt = function ($value, int $decimals = 4): string {
        if ($value === null || $value === '') {
            return '—';
        }

        $formatted = number_format((float) $value, $decimals, ',', ' ');

        return rtrim(rtrim($formatted, '0'), ',') ?: '0';
    };

    $dateTime = fn ($value): string => $value ? $value->format('d/m/Y H:i') : '—';

    $threshold = function ($parameter) use ($fmt): string {
        $min = $parameter->seuil_min;
        $max = $parameter->seuil_max;

        if ($min === null && $max === null) {
            return 'Aucun seuil enregistré';
        }

        if ($min !== null && $max !== null) {
            return $fmt($min).' – '.$fmt($max).' '.$parameter->unite;
        }

        return $min !== null
            ? '≥ '.$fmt($min).' '.$parameter->unite
            : '≤ '.$fmt($max).' '.$parameter->unite;
    };

    $statutLabel = fn ($sample): string => $sample->statutLabel();

    $tendanceLabel = match ($summary['tendance']) {
        'amelioration' => 'en amélioration',
        'degradation' => 'en dégradation',
        default => 'stable',
    };

    $latestTotal = $latest ? $latest->parameters->count() : 0;
    $latestConformes = $latest ? $latest->parameters->where('est_conforme', true)->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport qualité de l'eau — {{ $zone->nom }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9pt;
            color: #1e293b;
            background: #ffffff;
        }

        .bandeau {
            background: #0f6e86;
            color: #ffffff;
            padding: 14px 18px 12px 18px;
            margin-bottom: 14px;
        }
        .bandeau-titre {
            font-size: 15pt;
            font-weight: bold;
            letter-spacing: 0.4px;
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

        h2 {
            font-size: 11pt;
            font-weight: bold;
            color: #ffffff;
            background: #0f6e86;
            padding: 5px 8px;
            margin: 14px 0 8px 0;
            page-break-after: avoid;
        }

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
        thead { display: table-header-group; }
        tbody tr td {
            font-size: 8.5pt;
            padding: 4px 7px;
            border-bottom: 1px solid #e2e8f0;
            border-left: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            vertical-align: top;
        }
        tbody tr:nth-child(even) td { background: #f0f7f9; }
        tbody tr:nth-child(odd) td { background: #ffffff; }
        tbody tr { page-break-inside: avoid; }

        .meta-table td {
            border: 1px solid #d1e9ee;
            background: #f0f8fa;
            padding: 6px 8px;
            font-size: 8.5pt;
        }
        .meta-label {
            color: #475569;
            font-size: 7.5pt;
            display: block;
        }
        .meta-valeur {
            color: #1e293b;
            font-size: 9pt;
            font-weight: bold;
        }

        .kpi-table { margin-bottom: 4px; }
        .kpi-table td {
            text-align: center;
            padding: 9px 6px;
            border: 1px solid #d1e9ee;
            background: #f0f8fa;
            vertical-align: middle;
        }
        .kpi-valeur {
            font-size: 14pt;
            font-weight: bold;
            line-height: 1.1;
            color: #0f6e86;
        }
        .kpi-valeur-danger { color: #c0392b; }
        .kpi-label {
            font-size: 7.5pt;
            color: #475569;
            margin-top: 2px;
        }

        .ok { color: #127a45; font-weight: bold; }
        .ko { color: #c0392b; font-weight: bold; }
        .muted { color: #64748b; font-style: italic; }
        .num { text-align: right; }

        .mesure { padding: 1px 0; font-size: 8.2pt; line-height: 1.35; }
        .seuil { color: #64748b; }

        .synthese {
            font-size: 8.8pt;
            line-height: 1.55;
            color: #334155;
            margin-bottom: 8px;
        }
        .note {
            font-size: 7.5pt;
            color: #64748b;
            font-style: italic;
            margin-top: 6px;
            line-height: 1.4;
        }
    </style>
</head>
<body>

<div class="bandeau">
    <div class="bandeau-titre">Rapport qualité de l'eau — {{ $zone->nom }}</div>
    <div class="bandeau-sous-titre">AquaSecure · Gestion 3 — Qualité de l'eau · Prélèvements et conformité de la zone</div>
    <div class="bandeau-date">Généré le {{ $generatedAt->format('d/m/Y à H:i') }}</div>
</div>

<table class="meta-table" style="margin-bottom:12px;">
    <tr>
        <td style="width:32%;">
            <span class="meta-label">Zone / périmètre</span>
            <span class="meta-valeur">{{ $zone->nom }}</span>
        </td>
        <td style="width:23%;">
            <span class="meta-label">Commune</span>
            <span class="meta-valeur">{{ $zone->commune ?: '—' }}</span>
        </td>
        <td style="width:22%;">
            <span class="meta-label">Code postal</span>
            <span class="meta-valeur">{{ $zone->code_postal ?: '—' }}</span>
        </td>
        <td style="width:23%;">
            <span class="meta-label">Généré par</span>
            <span class="meta-valeur">{{ $author }}</span>
        </td>
    </tr>
</table>

<h2>1. Synthèse de la situation</h2>

<p class="synthese">
    Le périmètre <strong>{{ $zone->nom }}</strong> compte
    <strong>{{ $counts['total'] }}</strong> prélèvement{{ $counts['total'] > 1 ? 's' : '' }} enregistré{{ $counts['total'] > 1 ? 's' : '' }}
    @if ($firstDate && $lastDate)
        entre le <strong>{{ $firstDate->format('d/m/Y') }}</strong> et le <strong>{{ $lastDate->format('d/m/Y') }}</strong>,
    @endif
    dont <strong>{{ $counts['conformes'] }}</strong> conforme{{ $counts['conformes'] > 1 ? 's' : '' }},
    <strong>{{ $counts['non_conformes'] }}</strong> non conforme{{ $counts['non_conformes'] > 1 ? 's' : '' }}
    @if ($counts['en_attente'] > 0)
        et <strong>{{ $counts['en_attente'] }}</strong> en attente
    @endif
    .
</p>

@if ($latest)
    <p class="synthese">
        Le dernier prélèvement, daté du <strong>{{ $dateTime($latest->date_prelevement) }}</strong>
        (référence <strong>{{ $latest->reference }}</strong>), présente le statut
        <strong>{{ $statutLabel($latest) }}</strong> avec un score de <strong>{{ $fmt($latest->score, 2) }} %</strong>.
        Il porte sur {{ $latestTotal }} paramètre{{ $latestTotal > 1 ? 's' : '' }},
        dont {{ $latestConformes }} conforme{{ $latestConformes > 1 ? 's' : '' }}
        au regard des seuils enregistrés.
    </p>
@endif

<p class="synthese">
    Alertes actives pour ce périmètre : <strong>{{ $summary['alertes_actives'] }}</strong>.
    @if ($trendDisponible)
        Tendance de conformité calculée à partir des scores réellement enregistrés : <strong>{{ $tendanceLabel }}</strong>.
    @else
        Tendance non déterminable : moins de trois prélèvements analysés sont disponibles pour ce périmètre.
    @endif
</p>

<table class="kpi-table">
    <tr>
        <td style="width:33.33%;">
            <div class="kpi-valeur">{{ $counts['total'] }}</div>
            <div class="kpi-label">Prélèvements enregistrés</div>
        </td>
        <td style="width:33.33%;">
            <div class="kpi-valeur">{{ $counts['conformes'] }}</div>
            <div class="kpi-label">Prélèvements conformes</div>
        </td>
        <td style="width:33.33%;">
            <div class="kpi-valeur {{ $counts['non_conformes'] > 0 ? 'kpi-valeur-danger' : '' }}">{{ $counts['non_conformes'] }}</div>
            <div class="kpi-label">Prélèvements non conformes</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="kpi-valeur {{ $summary['alertes_actives'] > 0 ? 'kpi-valeur-danger' : '' }}">{{ $summary['alertes_actives'] }}</div>
            <div class="kpi-label">Alertes actives</div>
        </td>
        <td>
            <div class="kpi-valeur">{{ $summary['score_moyen'] !== null ? $fmt($summary['score_moyen'], 2).' %' : '—' }}</div>
            <div class="kpi-label">Score moyen (6 derniers analysés)</div>
        </td>
        <td>
            <div class="kpi-valeur">{{ $summary['conformite_90j'] !== null ? $fmt($summary['conformite_90j'], 2).' %' : '—' }}</div>
            <div class="kpi-label">Conformité sur 90 jours</div>
        </td>
    </tr>
</table>

<h2>2. État actuel — dernier prélèvement</h2>

@if ($latest)
    <table style="margin-bottom:8px;">
        <thead>
            <tr>
                <th style="width:20%;">Date</th>
                <th style="width:22%;">Référence</th>
                <th style="width:22%;">Point de prélèvement</th>
                <th style="width:18%;">Statut</th>
                <th style="width:18%;">Score</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $dateTime($latest->date_prelevement) }}</td>
                <td>{{ $latest->reference }}</td>
                <td>{{ $latest->source ?: '—' }}</td>
                <td class="{{ $latest->statut === 'non_conforme' ? 'ko' : ($latest->statut === 'conforme' ? 'ok' : '') }}">{{ $statutLabel($latest) }}</td>
                <td>{{ $fmt($latest->score, 2) }} %</td>
            </tr>
        </tbody>
    </table>

    @if ($latestTotal > 0)
        <table>
            <thead>
                <tr>
                    <th style="width:24%;">Paramètre</th>
                    <th style="width:14%;">Valeur</th>
                    <th style="width:14%;">Unité</th>
                    <th style="width:28%;">Seuils appliqués</th>
                    <th style="width:20%;">Conformité</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($latest->parameters as $parameter)
                    <tr>
                        <td>{{ $parameter->parametreLabel() }}</td>
                        <td class="num">{{ $fmt($parameter->valeur) }}</td>
                        <td>{{ $parameter->unite ?: '—' }}</td>
                        <td>{{ $threshold($parameter) }}</td>
                        <td class="{{ $parameter->est_conforme ? 'ok' : 'ko' }}">{{ $parameter->est_conforme ? 'Conforme' : 'Non conforme' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Aucune mesure enregistrée pour ce prélèvement.</p>
    @endif
@else
    <p class="muted">Aucun prélèvement disponible.</p>
@endif

<h2>3. Historique complet — du plus ancien au plus récent</h2>

<table>
    <thead>
        <tr>
            <th style="width:14%;">Date</th>
            <th style="width:15%;">Référence</th>
            <th style="width:11%;">Point</th>
            <th style="width:37%;">Mesures, seuils et conformité</th>
            <th style="width:13%;">Statut</th>
            <th style="width:10%;">Score</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($history as $sample)
            <tr>
                <td>{{ $dateTime($sample->date_prelevement) }}</td>
                <td>{{ $sample->reference }}</td>
                <td>{{ $sample->source ?: '—' }}</td>
                <td>
                    @forelse ($sample->parameters as $parameter)
                        <div class="mesure">
                            {{ $parameter->parametreLabel() }} :
                            <strong>{{ $fmt($parameter->valeur) }} {{ $parameter->unite }}</strong>
                            <span class="seuil">(seuils : {{ $threshold($parameter) }})</span>
                            <span class="{{ $parameter->est_conforme ? 'ok' : 'ko' }}">{{ $parameter->est_conforme ? 'Conforme' : 'Non conforme' }}</span>
                        </div>
                    @empty
                        <span class="muted">Aucune mesure enregistrée</span>
                    @endforelse
                </td>
                <td class="{{ $sample->statut === 'non_conforme' ? 'ko' : ($sample->statut === 'conforme' ? 'ok' : '') }}">{{ $statutLabel($sample) }}</td>
                <td>{{ $fmt($sample->score, 2) }} %</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>4. Historique des statuts de conformité</h2>

<table>
    <thead>
        <tr>
            <th style="width:34%;">Date</th>
            <th style="width:36%;">Référence</th>
            <th style="width:15%;">Statut</th>
            <th style="width:15%;">Score</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($history as $sample)
            <tr>
                <td>{{ $dateTime($sample->date_prelevement) }}</td>
                <td>{{ $sample->reference }}</td>
                <td class="{{ $sample->statut === 'non_conforme' ? 'ko' : ($sample->statut === 'conforme' ? 'ok' : '') }}">{{ $statutLabel($sample) }}</td>
                <td>{{ $fmt($sample->score, 2) }} %</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>5. Alertes associées à la zone</h2>

@if ($alerts->isNotEmpty())
    <table>
        <thead>
            <tr>
                <th style="width:14%;">Date prélèvement</th>
                <th style="width:16%;">Paramètre</th>
                <th style="width:11%;">Niveau</th>
                <th style="width:33%;">Message</th>
                <th style="width:11%;">Statut</th>
                <th style="width:15%;">Résolue le</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($alerts as $alert)
                <tr>
                    <td>{{ $alert->sample ? $dateTime($alert->sample->date_prelevement) : $dateTime($alert->created_at) }}</td>
                    <td>{{ \App\Models\Quality\QualityThreshold::PARAMETRES[$alert->parametre] ?? ucfirst((string) $alert->parametre) }}</td>
                    <td class="{{ $alert->niveau === 'critique' ? 'ko' : '' }}">{{ $alert->niveauLabel() }}</td>
                    <td>{{ $alert->message ?: '—' }}</td>
                    <td>{{ $alert->statutLabel() }}</td>
                    <td>{{ $alert->resolue_at ? $dateTime($alert->resolue_at) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="muted">Aucune alerte enregistrée pour cette zone.</p>
@endif

<p class="note">
    Ce rapport est établi uniquement à partir des prélèvements et analyses enregistrés dans AquaSecure
    pour la zone {{ $zone->nom }}. Les statuts de conformité reprennent l'analyse existante du système
    et les seuils réellement appliqués à chaque mesure. Aucune donnée d'un autre périmètre n'est incluse
    et aucune conclusion n'est formulée au-delà des données disponibles.
</p>

<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->get_font("DejaVu Sans", "normal");
        $size = 7;
        $color = [0.40, 0.47, 0.54];

        $pdf->page_text(
            36, 818,
            "AquaSecure — Rapport qualité · {{ $zone->nom }} · {{ $generatedAt->format('d/m/Y') }}",
            $font, $size, $color
        );

        $pdf->page_text(
            460, 818,
            "Page {PAGE_NUM} / {PAGE_COUNT}",
            $font, $size, $color
        );
    }
</script>

</body>
</html>
