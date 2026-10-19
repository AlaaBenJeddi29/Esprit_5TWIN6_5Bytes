<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0066cc; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
        .content { border: 1px solid #ddd; padding: 20px; }
        .button { display: inline-block; background-color: #0066cc; color: white !important; padding: 10px 20px; text-decoration: none !important; border-radius: 5px; margin: 20px 0; }
        .footer { border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Bienvenue {{ $userName }}!</h1>
    </div>
    <div class="content">
        <p>Votre abonnement aux alertes a été créé avec succès.</p>
        
        <p>Vous recevrez désormais des notifications pour :</p>
        
        <p>
            <strong>Zone :</strong> {{ $zoneName }}<br>
            <strong>Type d'alertes :</strong> {{ $typeAlerte }}
        </p>
        
        <h3>À propos de vos abonnements</h3>
        <p>Vous pouvez modifier ou annuler vos abonnements à tout moment en accédant à votre espace personnel.</p>
        
        <a href="{{ rtrim(config('app.url'), '/') }}/alertes" class="button">Gérer mes abonnements</a>
        
        <div class="footer">
            <p>Merci d'utiliser AquaSecure pour rester informé des alertes concernant votre zone d'eau.</p>
            <p>Cordialement,<br><strong>L'équipe AquaSecure</strong></p>
        </div>
    </div>
</div>
</body>
</html>
