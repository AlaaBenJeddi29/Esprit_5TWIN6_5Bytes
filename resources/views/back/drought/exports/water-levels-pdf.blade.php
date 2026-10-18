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
        
        .status-critical {
            background-color: #fee;
            color: #c33;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: bold;
        }
        
        .status-warning {
            background-color: #ffeaa7;
            color: #d63031;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: bold;
        }
        
        .status-normal {
            background-color: #d4edda;
            color: #155724;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: bold;
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
        <div class="header-title">📊 Rapport - Niveaux des Ressources</div>
        <div class="header-info">
            <span>Date d'export: {{ now()->format('d/m/Y H:i') }}</span>
            <span>Gestionnaire: {{ Auth::user()?->name ?? 'Système' }}</span>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Source</th>
                <th>Type</th>
                <th>Zone</th>
                <th class="text-right">Niveau (%)</th>
                <th class="text-right">Seuil Alerte (%)</th>
                <th class="text-right">Seuil Critique (%)</th>
                <th class="text-center">Statut</th>
                <th>Date Mesure</th>
            </tr>
        </thead>
        <tbody>
            @forelse($levels as $level)
                <tr>
                    <td><strong>{{ $level->nom_source }}</strong></td>
                    <td>{{ $level->typeLabel() }}</td>
                    <td>{{ $level->zone?->nom ?? '—' }}</td>
                    <td class="text-right">{{ $level->niveau }}</td>
                    <td class="text-right">{{ $level->seuil_alerte }}</td>
                    <td class="text-right">{{ $level->seuil_critique }}</td>
                    <td class="text-center">
                        @switch($level->niveauStatut())
                            @case('critique')
                                <span class="status-critical">{{ $level->niveauStatutLabel() }}</span>
                                @break
                            @case('alerte')
                                <span class="status-warning">{{ $level->niveauStatutLabel() }}</span>
                                @break
                            @default
                                <span class="status-normal">{{ $level->niveauStatutLabel() }}</span>
                        @endswitch
                    </td>
                    <td>{{ $level->date_mesure?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">Aucune donnée disponible</td>
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
