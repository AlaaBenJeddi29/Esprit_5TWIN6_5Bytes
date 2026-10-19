<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Couche IA locale réutilisable — Ollama uniquement (Gestion 5 et modules futurs).
 *
 * Zéro coût : aucune API externalisée, aucune clé, aucun téléchargement. Les
 * appels partent uniquement vers un Ollama installé sur ce poste : toute URL
 * dont l'hôte n'est pas local (localhost, 127.x.x.x, ::1) est refusée avant
 * l'appel, pour ne jamais envoyer de données du projet hors de la machine.
 *
 * Ollama est une dépendance optionnelle : generer() ne lève jamais
 * d'exception. Quand le serveur est éteint, absent, trop lent ou renvoie une
 * réponse inexploitable, le résultat portera success=false avec un code
 * d'erreur stable — l'appelant (controller, service métier) choisit alors son
 * propre texte de secours. Les prompts, réponses brutes et données
 * utilisateur ne sont jamais journalisés.
 */
class LocalLlmService
{
    /**
     * Génère un texte avec le modèle local configuré (/api/generate, sans stream).
     *
     * @param  string  $prompt  Prompt complet construit par l'appelant — les prompts
     *                          restent hors des contrôleurs et jamais journalisés.
     * @param  array{model?: string, temperature?: float, timeout?: int, format?: string, max_tokens?: int}  $options
     * @return array{success: bool, texte: string, code: ?string, message: ?string}
     *                                                                              code : null | 'desactive' | 'hote_non_local' | 'indisponible' |
     *                                                                              'expiration' | 'statut_http' | 'reponse_invalide' | 'reponse_vide' | 'erreur'
     */
    public function generer(string $prompt, array $options = []): array
    {
        $config = $this->configuration();

        if (! ($config['enabled'] ?? true)) {
            return $this->echec('desactive', 'La couche IA locale est désactivée.');
        }

        $url = $this->urlLocalisee((string) $config['url']);

        if ($url === null) {
            // Refus explicite : aucune donnée ne quitte ce poste.
            return $this->echec('hote_non_local', 'L\'IA locale exige une adresse Ollama sur ce poste (localhost).');
        }

        $modele = (string) ($options['model'] ?? $config['model']);

        try {
            $payload = [
                'model' => $modele,
                'prompt' => $prompt,
                'stream' => false,
                'options' => ['temperature' => (float) ($options['temperature'] ?? $config['temperature'])],
            ];

            if (isset($options['format'])) {
                // Structuration locale de la sortie (ex. « json ») — Ollama uniquement.
                $payload['format'] = (string) $options['format'];
            }

            if (isset($options['max_tokens'])) {
                // Sans borne de sortie, une génération locale peut ne jamais s'arrêter :
                // le modèle enchaîne les répétitions jusqu'à l'expiration du délai HTTP.
                $payload['options']['num_predict'] = (int) $options['max_tokens'];
            }

            $reponse = Http::timeout((int) ($options['timeout'] ?? $config['timeout']))
                ->connectTimeout((int) $config['connect_timeout'])
                ->post($url.'/api/generate', $payload);

            if (! $reponse->successful()) {
                // Le statut seul est journalisable : le corps peut contenir du prompt.
                Log::warning('Ollama (IA locale) a répondu HTTP '.$reponse->status().' — modèle '.$modele.'.');

                return $this->echec('statut_http', 'Ollama a répondu HTTP '.$reponse->status().'.');
            }

            $corps = $reponse->json();

            if (! is_array($corps) || ! isset($corps['response']) || ! is_string($corps['response'])) {
                Log::warning('Réponse Ollama (IA locale) de forme inattendue — modèle '.$modele.'.');

                return $this->echec('reponse_invalide', 'La réponse d\'Ollama est de forme inattendue.');
            }

            $texte = trim($corps['response']);

            if ($texte === '') {
                Log::warning('Réponse Ollama (IA locale) vide — modèle '.$modele.'.');

                return $this->echec('reponse_vide', 'Ollama a renvoyé une réponse vide.');
            }

            return ['success' => true, 'texte' => $texte, 'code' => null, 'message' => null];
        } catch (ConnectionException $e) {
            // cURL 28 = dépassement de délai ; les autres échecs = serveur injoignable.
            $expire = str_contains($e->getMessage(), 'cURL error 28');

            Log::warning('Ollama (IA locale) '.($expire ? 'trop lent sur ' : 'injoignable sur ').$url.' — modèle '.$modele.'.');

            return $expire
                ? $this->echec('expiration', 'Ollama n\'a pas répondu dans le délai imparti.')
                : $this->echec('indisponible', 'Ollama est injoignable sur ce poste.');
        } catch (Throwable $e) {
            Log::warning('Erreur inattendue de l\'appel Ollama (IA locale) : '.$e->getMessage());

            return $this->echec('erreur', 'Une erreur est survenue pendant la génération locale.');
        }
    }

    /**
     * Ollama répond-il et le modèle configuré est-il présent sur ce poste ?
     * Ne lève jamais d'exception — false sinon. N'initie aucun téléchargement.
     */
    public function estDisponible(?string $modele = null): bool
    {
        $config = $this->configuration();

        if (! ($config['enabled'] ?? true)) {
            return false;
        }

        $url = $this->urlLocalisee((string) $config['url']);

        if ($url === null) {
            return false;
        }

        $modele ??= (string) $config['model'];

        try {
            $reponse = Http::timeout((int) $config['connect_timeout'])->get($url.'/api/tags');

            if (! $reponse->successful()) {
                return false;
            }

            $corps = $reponse->json();

            if (! is_array($corps) || ! is_array($corps['models'] ?? null)) {
                return false;
            }

            foreach ($corps['models'] as $entree) {
                $nom = is_array($entree) && isset($entree['name']) ? (string) $entree['name'] : '';

                if ($nom === $modele || $nom === $modele.':latest' || str_starts_with($nom, $modele.':')) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Configuration avec valeurs par défaut non secrètes (les clés manquantes
     * ou une configuration vide gardent le comportement documenté en .env.example).
     *
     * @return array<string, mixed>
     */
    private function configuration(): array
    {
        return (array) config('services.local_llm', []) + [
            'enabled' => true,
            'url' => 'http://localhost:11434',
            'model' => 'llama3.2',
            'timeout' => 120,
            'connect_timeout' => 5,
            'temperature' => 0.3,
        ];
    }

    /**
     * Normalise l'URL (schéma http par défaut, sans « / » final) et vérifie que
     * l'hôte est bien local. Renvoie null si l'hôte est distant ou l'URL invalide.
     */
    private function urlLocalisee(string $url): ?string
    {
        $url = rtrim($url, '/');
        $url = str_starts_with($url, 'http') ? $url : 'http://'.$url;

        $hote = parse_url($url, PHP_URL_HOST);

        if (! is_string($hote) || $hote === '') {
            return null;
        }

        $hote = strtolower(trim($hote, '[]'));

        $local = $hote === 'localhost'
            || $hote === '::1'
            || filter_var($hote, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && str_starts_with($hote, '127.');

        return $local ? $url : null;
    }

    /**
     * @return array{success: bool, texte: string, code: string, message: string}
     */
    private function echec(string $code, string $message): array
    {
        return ['success' => false, 'texte' => '', 'code' => $code, 'message' => $message];
    }
}
