<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.6;
        }
        
        @page {
            size: A4 landscape;
            margin: 15mm;
        }
        
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #1a5276;
            padding-bottom: 10px;
        }
        
        .header-title {
            font-size: 16px;
            font-weight: bold;
            color: #1a5276;
            margin-bottom: 5px;
        }
        
        .header-info {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #666;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        thead {
            background-color: #1a5276;
            color: white;
            font-weight: bold;
        }
        
        th {
            padding: 8px;
            text-align: left;
            border: 1px solid #333;
            font-size: 10px;
        }
        
        td {
            padding: 7px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        tbody tr:hover {
            background-color: #e6e6e6;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-title">📈 Rapport - Relevés de Consommation</div>
        <div class="header-info">
            <span>Date d'export: {{ now()->format('d/m/Y H:i') }}</span>
            <span>Gestionnaire: {{ Auth::user()?->name ?? 'Système' }}</span>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Zone</th>
                <th>Période</th>
                <th class="text-right">Consommation (m³)</th>
                <th class="text-right">Abonnés</th>
                <th class="text-right">m³/Abonné</th>
            </tr>
        </thead>
        <tbody>
            @forelse($readings as $reading)
                @php
                    $m3_per_subscriber = $reading->nombre_abonnes > 0 
                        ? round($reading->consommation_m3 / $reading->nombre_abonnes, 2)
                        : 0;
                @endphp
                <tr>
                    <td><strong>{{ $reading->zone?->nom ?? '—' }}</strong></td>
                    <td>{{ $reading->periodeLabel() }}</td>
                    <td class="text-right">{{ number_format($reading->consommation_m3, 2, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format($reading->nombre_abonnes, 0, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format($m3_per_subscriber, 2, ',', ' ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">Aucune donnée disponible</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="footer">
        <p>AquaSecure - Gestion 4 : Sécheresse, Consommation et Ressources</p>
        <p>Rapport généré automatiquement le {{ now()->format('d/m/Y à H:i:s') }}</p>
    </div>
</body>
</html>
