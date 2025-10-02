<?php
declare(strict_types=1);

namespace App\Service;

use App\Settings;
use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instance = null;
    private static array $queryLog = [];

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
            // Wrap PDO to log queries if debug enabled
            $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [LoggedPDOStatement::class, [&$pdo]]);
            self::$instance = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public static function logQuery(string $sql, array $params, float $ms): void
    {
        // Always collect in memory; visibility is controlled at render time
        self::$queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'time_ms' => $ms,
        ];
        if (count(self::$queryLog) > 200) { array_shift(self::$queryLog); }
    }

    public static function getQueryLog(): array
    {
        return self::$queryLog;
    }
}

final class LoggedPDOStatement extends \PDOStatement
{
    /** @var PDO */
    protected $pdo;
    protected function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        // For PDO::query(), statement is already executed upon construction. Log without timing.
        try {
            \App\Service\Database::logQuery($this->queryString, [], 0.0);
        } catch (\Throwable $e) {}
    }

    public function execute($params = null): bool
    {
        $start = microtime(true);
        try {
            return parent::execute($params);
        } finally {
            $timeMs = (microtime(true) - $start) * 1000.0;
            try {
                \App\Service\Database::logQuery($this->queryString, (array)$params, $timeMs);
            } catch (\Throwable $e) {}
        }
    }
}

