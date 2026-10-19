@php
    /**
     * Vue PDF — Indicateurs incidents (DomPDF).
     * Contraintes : pas de flex/grid, tableaux uniquement, DejaVu Sans.
     *
     * Variables injectées :
     *  $summary     — IncidentStatsService::summary()
     *  $periodLabel — libellé de la période
     *  $from, $to   — bornes de la période (Y-m-d)
     *  $generatedAt — date de génération (Carbon)
     *  $author      — nom de l'utilisateur ayant généré le PDF
     */
    $nb = fn ($value, int $decimals = 0) => $value === null
        ? '—'
        : number_format((float) $value, $decimals, ',', ' ');

    $avgResolution = $summary['avg_resolution_h'] === null
        ? '—'
        : number_format((float) $summary['avg_resolution_h'], 1, ',', ' ').' h';

    $resolutionRate = $summary['resolution_rate'] === null
        ? '—'
        : number_format((float) $summary['resolution_rate'], 1, ',', ' ').' %';

    $maxTotal = max(1, (int) collect($summary['by_zone'])->max('total'));
    $grandTotal = (int) collect($summary['by_zone'])->sum('total');
    $days = \Illuminate\Support\Carbon::parse($from)->diffInDays(\Illuminate\Support\Carbon::parse($to)) + 1;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Indicateurs incidents — AquaSecure</title>
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
            vertical-align: middle;
        }
        tbody tr:nth-child(even) td { background: #f0f7f9; }
        tbody tr:nth-child(odd) td { background: #ffffff; }

        .num { text-align: right; }
        .crit { color: #c0392b; font-weight: bold; }

        .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
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
            color: #0f6e86;
        }
        .kpi-valeur-danger { color: #c0392b; }
        .kpi-label {
            font-size: 7.5pt;
            color: #475569;
            margin-top: 2px;
        }

        .barre-fond { background: #dde8ec; width: 100%; height: 8px; }
        .barre { background: #0f6e86; height: 8px; }

        .note {
            font-size: 7.5pt;
            color: #64748b;
            font-style: italic;
            margin-top: 6px;
            line-height: 1.4;
        }

        .no-cut { page-break-inside: avoid; }
        .meta { font-size: 8.5pt; color: #475569; margin-bottom: 10px; }
        .meta strong { color: #1e293b; }
    </style>
</head>
<body>

<div class="bandeau">
    <div class="bandeau-titre">Indicateurs incidents — AquaSecure</div>
    <div class="bandeau-sous-titre">Gestion 2 · Suivi opérationnel des incidents du réseau d'eau potable</div>
    <div class="bandeau-date">Généré le {{ $generatedAt->format('d/m/Y à H:i') }}</div>
</div>

<p class="meta">
    <strong>Période analysée :</strong> {{ $periodLabel }}
    @if ($from && $to)
        ({{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }}
        → {{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }})
    @endif
    &nbsp;·&nbsp;
    <strong>Généré par :</strong> {{ $author }}
    &nbsp;·&nbsp;
    <strong>Incidents sur la période :</strong> {{ $nb($grandTotal) }}
</p>

<h2>1. Indicateurs clés</h2>

<div class="no-cut">
    <table class="kpi-table">
        <tr>
            <td style="width:25%;">
                <div class="kpi-valeur {{ $summary['critical_open'] > 0 ? 'kpi-valeur-danger' : '' }}">
                    {{ $nb($summary['critical_open']) }}
                </div>
                <div class="kpi-label">Incidents critiques ouverts</div>
            </td>
            <td style="width:25%;">
                <div class="kpi-valeur">{{ $nb($summary['unassigned_open']) }}</div>
                <div class="kpi-label">Incidents non affectés</div>
            </td>
            <td style="width:25%;">
                <div class="kpi-valeur">{{ $avgResolution }}</div>
                <div class="kpi-label">Délai moyen de résolution</div>
            </td>
            <td style="width:25%;">
                <div class="kpi-valeur">{{ $resolutionRate }}</div>
                <div class="kpi-label">Taux de résolution</div>
            </td>
        </tr>
    </table>

    <p class="note">
        Les incidents critiques ouverts et non affectés sont comptés sur la période sélectionnée.
        Le délai moyen porte sur les incidents traités disposant des deux dates (signalement et résolution).
        Le taux de résolution = traités / (total − rejetés − doublons signalés).
    </p>
</div>

<h2>2. Incidents par zone</h2>

@if ($grandTotal === 0)
    <p style="color:#64748b;font-size:8.5pt;">Aucun incident signalé sur cette période.</p>
@else
    <table>
        <thead>
            <tr>
                <th style="width:34%;">Zone</th>
                <th style="width:12%;">Total</th>
                <th style="width:12%;">Ouverts</th>
                <th style="width:12%;">Critiques</th>
                <th style="width:30%;">Répartition</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['by_zone'] as $row)
                @php
                    $width = $row['total'] > 0 ? min(100, round(($row['total'] / $maxTotal) * 100)) : 0;
                @endphp
                <tr>
                    <td style="{{ $row['zone_id'] === null ? 'font-style:italic;color:#64748b;' : 'font-weight:bold;' }}">
                        {{ $row['zone'] }}
                    </td>
                    <td class="num">{{ $nb($row['total']) }}</td>
                    <td class="num">{{ $nb($row['open']) }}</td>
                    <td class="num {{ $row['critical'] > 0 ? 'crit' : '' }}">{{ $nb($row['critical']) }}</td>
                    <td style="vertical-align:middle;padding-left:6px;">
                        <div class="barre-fond">
                            <div class="barre" style="width:{{ $width }}%;"></div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<p class="note">
    Les zones sans incident sur la période sont affichées avec des compteurs à zéro.
    La ligne « Sans zone » regroupe les signalements non rattachés à une zone géographique.
</p>

<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->get_font("DejaVu Sans", "normal");
        $size = 7;
        $color = [0.40, 0.47, 0.54];

        $pdf->page_text(
            36, 818,
            "AquaSecure — Indicateurs incidents · {{ $generatedAt->format('d/m/Y') }}",
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
