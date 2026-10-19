<?php
/**
 * Test rapide d'envoi SMTP Gmail — supprimer ce fichier après le test.
 * Usage : php test_mail.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Mail;

echo "Mailer  : " . config('mail.default') . PHP_EOL;
echo "Host    : " . config('mail.mailers.smtp.host') . PHP_EOL;
echo "Port    : " . config('mail.mailers.smtp.port') . PHP_EOL;
echo "Scheme  : " . config('mail.mailers.smtp.scheme') . PHP_EOL;
echo "Username: " . config('mail.mailers.smtp.username') . PHP_EOL;
echo "From    : " . config('mail.from.address') . PHP_EOL;
echo PHP_EOL;

try {
    Mail::raw('Test SMTP AquaSecure — si tu reçois cet email, la config est correcte.', function ($message) {
        $message->to('aouadimariem25@gmail.com')
                ->subject('Test SMTP AquaSecure');
    });
    echo "✅ Email envoyé avec succès !" . PHP_EOL;
} catch (\Throwable $e) {
    echo "❌ Erreur : " . $e->getMessage() . PHP_EOL;
}
