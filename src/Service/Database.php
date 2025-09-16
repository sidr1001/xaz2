<?php
declare(strict_types=1);

namespace App\Service;

use App\Settings;
use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        try {
            $pdo = new PDO(
                Settings::dbDsn(),
                Settings::dbUser(),
                Settings::dbPass(),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            self::$instance = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            throw $e;
        }
    }
}

