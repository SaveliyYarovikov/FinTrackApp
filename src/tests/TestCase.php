<?php

namespace Tests;

use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PDO;
use PDOException;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private static bool $testingDatabasePrepared = false;

    protected function setUp(): void
    {
        $this->prepareTestingDatabase();

        parent::setUp();

        $this->app->bind(UncompromisedVerifier::class, static function (): UncompromisedVerifier {
            return new class implements UncompromisedVerifier
            {
                public function verify($data): bool
                {
                    return true;
                }
            };
        });
    }

    private function prepareTestingDatabase(): void
    {
        if (self::$testingDatabasePrepared) {
            return;
        }

        $connection = (string) getenv('DB_CONNECTION');

        if (!in_array($connection, ['mysql', 'mariadb'], true)) {
            self::$testingDatabasePrepared = true;

            return;
        }

        $host = (string) (getenv('DB_HOST') ?: '127.0.0.1');
        $port = (string) (getenv('DB_PORT') ?: '3306');
        $database = (string) getenv('DB_DATABASE');
        $username = (string) (getenv('DB_USERNAME') ?: 'root');
        $password = (string) (getenv('DB_PASSWORD') ?: '');

        if ($database === '') {
            throw new RuntimeException('DB_DATABASE must be set for tests.');
        }

        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
        $escapedDatabase = str_replace('`', '``', $database);

        $lastException = null;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            try {
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $pdo->exec(
                    sprintf(
                        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                        $escapedDatabase
                    )
                );

                self::$testingDatabasePrepared = true;

                return;
            } catch (PDOException $exception) {
                $lastException = $exception;
                usleep(500_000);
            }
        }

        throw new RuntimeException('Unable to prepare testing database.', 0, $lastException);
    }
}
