# AquaSecure

Plateforme de surveillance citoyenne de l'eau potable (Laravel 12 / Tailwind CSS 4 / Vite 7).

## Prérequis

- PHP 8.2+ avec les extensions `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`.
- Composer et Node.js.
- **XAMPP avec MySQL 8** démarré (Apache n'est pas requis si vous utilisez `php artisan serve`).
- SQLite n'est ni pris en charge ni configuré pour ce projet.

## Installation locale (MySQL 8 / XAMPP)

1. Démarrer MySQL depuis le panneau XAMPP.
2. Créer la base de développement (une seule fois, via phpMyAdmin ou la console MySQL) :
   ```sql
   CREATE DATABASE aquasecure CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Copier `.env.example` vers `.env` si nécessaire, puis renseigner les accès MySQL
   (`DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=aquasecure`,
   `DB_USERNAME`, `DB_PASSWORD`). Ne jamais committer `.env`.
4. Installer les dépendances et générer la clé (ne pas régénérer une clé existante) :
   ```bash
   composer install
   php artisan key:generate
   npm install
   ```
5. Appliquer les migrations et compiler le Front Office :
   ```bash
   php artisan migrate
   php artisan view:cache
   npm run build
   ```
6. Créer les comptes et données de démonstration (facultatif, explicite) :
   ```bash
   php artisan db:seed --class=DemoUsersSeeder
   php artisan db:seed --class=DemoDataSeeder
   ```
   `DemoUsersSeeder` nécessite `AQUASECURE_DEMO_PASSWORD` et les trois emails
   `AQUASECURE_DEMO_*_EMAIL` dans `.env`. `DemoDataSeeder` nécessite un gestionnaire ou
   un administrateur existant.
7. Lancer l'application (serveur + worker de file d'attente) :
   ```bash
   composer run dev
   ```
   `composer run dev` démarre `php artisan serve`, le worker `queue:listen`, Vite et les
   logs. Sous Windows, `scripts\start-projet.bat` démarre le serveur et le worker (ainsi
   qu'Ollama). Un `php artisan serve` seul ne traite pas la file (`QUEUE_CONNECTION=database`) :
   dans ce cas, lancer `php artisan queue:work` dans un second terminal.
   Aucun `php artisan storage:link` n'est nécessaire : les fichiers privés sont servis
   par les contrôleurs.

## Tests automatisés (MySQL 8 uniquement)

Les tests utilisent une base MySQL dédiée, **jamais** la base de développement. La
configuration est forcée dans `phpunit.xml` :

- `DB_CONNECTION=mysql`
- `DB_DATABASE=aquasecure_test`
- `DB_URL` vide

`tests/TestCase.php` refuse de démarrer si la connexion n'est pas MySQL, si le nom de la
base ne se termine pas par `_test`, ou si la configuration Laravel est mise en cache.

1. Créer la base de test une seule fois :
   ```sql
   CREATE DATABASE aquasecure_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Lancer les tests :
   ```bash
   php artisan config:clear
   php artisan test
   ```

`RefreshDatabase` recrée le schéma et **efface** le contenu de `aquasecure_test` à chaque
exécution. Ne jamais faire pointer les tests vers `aquasecure` ni vers une autre base
contenant des données réelles.

## Vérifications rapides

```bash
php artisan config:clear
php artisan view:cache
npm run build
```
