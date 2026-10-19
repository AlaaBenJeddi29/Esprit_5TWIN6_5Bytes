<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0066cc; color: white; padding: 20px; border-radius: 5px; }
        .alert-box { border-left: 4px solid; padding: 15px; margin: 20px 0; background-color: #f9f9f9; }
        .alert-qualite { border-color: #dc2626; background-color: #fee2e2; }
        .alert-restriction { border-color: #ea580c; background-color: #fed7aa; }
        .alert-coupure { border-color: #eab308; background-color: #fef3c7; }
        .button { display: inline-block; background-color: #0066cc; color: white !important; padding: 10px 20px; text-decoration: none !important; border-radius: 5px; margin: 20px 0; }
        .footer { border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Alerte pour votre zone : {{ $alertData['zone_name'] }}</h1>
    </div>
    
    @switch($alertData['type_alerte'])
        @case('qualite')
            <div class="alert-box alert-qualite">
                <strong>⚠️ Alerte Qualité de l'Eau</strong>
            </div>
        @break
        @case('restriction')
            <div class="alert-box alert-restriction">
                <strong>🚫 Alerte Restriction d'Eau</strong>
            </div>
        @break
        @case('coupure')
            <div class="alert-box alert-coupure">
                <strong>✂️ Alerte Coupure Programmée</strong>
            </div>
        @break
    @endswitch
    
    <h2>{{ $alertData['titre'] }}</h2>
    <p>{{ $alertData['description'] }}</p>
    
    <p><strong>Date de l'alerte :</strong> {{ $alertData['created_at']->format('d/m/Y à H:i') }}</p>
    
    <a href="{{ rtrim(config('app.url'), '/') }}/alertes" class="button">Consulter les détails</a>
    
    <div class="footer">
        <p>Vous recevez cet email car vous êtes abonné aux alertes de la zone <strong>{{ $alertData['zone_name'] }}</strong> sur AquaSecure.</p>
        <p>Pour gérer vos abonnements ou vous désabonner, consultez votre <a href="{{ rtrim(config('app.url'), '/') }}/alertes">espace personnel</a>.</p>
        <p><strong>L'équipe AquaSecure</strong></p>
    </div>
</div>
</body>
</html>
