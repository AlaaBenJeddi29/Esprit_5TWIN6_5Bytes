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
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: bold;
        }
        
        .status-planned {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: bold;
        }
        
        .status-lifted {
            background-color: #e2e3e5;
            color: #383d41;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: bold;
        }
        
        .level-vigilance {
            color: #0066cc;
            font-weight: bold;
        }
        
        .level-alerte {
            color: #ff9900;
            font-weight: bold;
        }
        
        .level-alerte_renforcee {
            color: #cc0000;
            font-weight: bold;
        }
        
        .level-crise {
            color: #990000;
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
        <div class="header-title">Rapport - Restrictions d'Utilisation</div>
        <div class="header-info">
            <span>Date d'export: {{ now()->format('d/m/Y H:i') }}</span>
            <span>Gestionnaire: {{ Auth::user()?->name ?? 'Système' }}</span>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Titre</th>
                <th>Niveau</th>
                <th>Zone</th>
                <th>Date Début</th>
                <th>Date Fin</th>
                <th class="text-center">Statut</th>
                <th>Usages Interdits</th>
            </tr>
        </thead>
        <tbody>
            @forelse($restrictions as $restriction)
                <tr>
                    <td><strong>{{ $restriction->titre }}</strong></td>
                    <td><span class="level-{{ $restriction->type }}">{{ $restriction->typeLabel() }}</span></td>
                    <td>{{ $restriction->zone?->nom ?? '—' }}</td>
                    <td>{{ $restriction->date_debut?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $restriction->date_fin?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="text-center">
                        @switch($restriction->statut)
                            @case('active')
                                <span class="status-active">{{ $restriction->statutLabel() }}</span>
                                @break
                            @case('planifiee')
                                <span class="status-planned">{{ $restriction->statutLabel() }}</span>
                                @break
                            @default
                                <span class="status-lifted">{{ $restriction->statutLabel() }}</span>
                        @endswitch
                    </td>
                    <td>{{ $restriction->usages_interdits ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Aucune donnée disponible</td>
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
