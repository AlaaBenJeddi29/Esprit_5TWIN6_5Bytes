<?php

namespace App\Services;

use App\Models\User;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

/**
 * Génération et vérification des facteurs TOTP, QR et codes de secours.
 *
 * Le secret et les empreintes des codes de secours sont stockés dans des
 * colonnes chiffrées par les casts du modèle User. Les codes en clair ne
 * quittent jamais cette classe : ils sont affichés une seule fois au moment
 * de leur création, puis seules leurs empreintes sont conservées.
 */
class TwoFactorAuthenticationService
{
    public const RECOVERY_CODE_COUNT = 8;

    public const RECOVERY_CODE_LENGTH = 10;

    public const PENDING_SETUP_TTL = 600;

    public const PENDING_LOGIN_TTL = 600;

    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private readonly Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            (string) config('app.name', 'AquaSecure'),
            $user->email,
            $secret,
        );
    }

    /**
     * SVG directement intégrable dans la page de configuration.
     * Aucune extension GD/Imagick n'est requise.
     */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $qrCode = new QrCode(
            data: $this->otpauthUrl($user, $secret),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 280,
            margin: 8,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return (new SvgWriter)->write($qrCode, null, null, [
            SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
        ])->getString();
    }

    public function verifyTotp(?string $secret, string $code): bool
    {
        if ($secret === null || $secret === '') {
            return false;
        }

        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        try {
            // Fenêtre de ±30 s pour tolérer une légère dérive d'horloge.
            return (bool) $this->google2fa->verifyKey($secret, $code, 1);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Active la 2FA et retourne les codes de secours en clair, à afficher
     * une seule fois.
     *
     * @return list<string>
     */
    public function enable(User $user, string $secret): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $this->hashRecoveryCodes($codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * Remplace tous les codes existants et retourne les nouveaux en clair.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $this->hashRecoveryCodes($codes),
        ])->save();

        return $codes;
    }

    /**
     * Consomme un code de secours. Le verrou de ligne garantit qu'un même
     * code ne peut pas être utilisé deux fois en cas de requêtes concurrentes.
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $normalized = $this->normalizeRecoveryCode($code);

        if (strlen($normalized) !== self::RECOVERY_CODE_LENGTH) {
            return false;
        }

        $candidateHash = $this->hashRecoveryCode($normalized);

        return DB::transaction(function () use ($user, $candidateHash): bool {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if ($locked === null || ! $locked->hasEnabledTwoFactorAuthentication()) {
                return false;
            }

            $hashes = (array) ($locked->two_factor_recovery_codes ?? []);
            $matchedIndex = null;

            foreach ($hashes as $index => $hash) {
                if (is_string($hash) && hash_equals($hash, $candidateHash)) {
                    $matchedIndex = $index;
                    break;
                }
            }

            if ($matchedIndex === null) {
                return false;
            }

            unset($hashes[$matchedIndex]);

            $locked->forceFill([
                'two_factor_recovery_codes' => array_values($hashes),
            ])->save();

            return true;
        });
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        $maxIndex = strlen(self::RECOVERY_ALPHABET) - 1;

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $raw = '';

            for ($j = 0; $j < self::RECOVERY_CODE_LENGTH; $j++) {
                $raw .= self::RECOVERY_ALPHABET[random_int(0, $maxIndex)];
            }

            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5);
        }

        return $codes;
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function hashRecoveryCodes(array $codes): array
    {
        return array_map(
            fn (string $code): string => $this->hashRecoveryCode($this->normalizeRecoveryCode($code)),
            $codes,
        );
    }

    private function hashRecoveryCode(string $normalized): string
    {
        return hash('sha256', $normalized);
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }
}
