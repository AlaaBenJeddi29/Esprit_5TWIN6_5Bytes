<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->guardTestingDatabase();

        parent::setUp();

        if (config('app.env') !== 'testing'
            || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== $this->environmentValue('DB_DATABASE')) {
            throw new RuntimeException(
                'La configuration de test a été altérée. Exécutez `php artisan config:clear` '
                .'puis relancez les tests sur la base MySQL dédiée.'
            );
        }
    }

    /**
     * Must run before parent::setUp(): RefreshDatabase resets the database
     * during trait initialization.
     */
    private function guardTestingDatabase(): void
    {
        if (is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            throw new RuntimeException(
                'La configuration est mise en cache. Exécutez `php artisan config:clear` avant les tests '
                .'pour ne jamais cibler la base de développement.'
            );
        }

        $connection = $this->environmentValue('DB_CONNECTION');
        $database = $this->environmentValue('DB_DATABASE');
        $url = $this->environmentValue('DB_URL');

        if ($connection !== 'mysql'
            || ! is_string($database)
            || ! str_ends_with($database, '_test')
            || ($url !== null && $url !== '')) {
            throw new RuntimeException(
                'Les tests automatisés exigent MySQL 8 avec une base dédiée dont le nom se termine par "_test" '
                .'(voir phpunit.xml). SQLite et la base de développement sont interdits.'
            );
        }
    }

    private function environmentValue(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) ? $value : null;
    }
}
