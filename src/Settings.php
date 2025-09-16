<?php
declare(strict_types=1);

namespace App;

final class Settings
{
    public static function get(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? $default;
    }

    public static function dbDsn(): string
    {
        $host = self::get('DB_HOST', '127.0.0.1');
        $port = self::get('DB_PORT', '3306');
        $name = self::get('DB_NAME', 'tours_cms');
        $charset = self::get('DB_CHARSET', 'utf8mb4');
        return "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    }

    public static function dbUser(): string
    {
        return self::get('DB_USER', 'root') ?? 'root';
    }

    public static function dbPass(): string
    {
        return self::get('DB_PASS', '') ?? '';
    }
}

