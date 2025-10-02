<?php
declare(strict_types=1);

namespace App\Console;

use App\Service\Database;

final class MigrateHashAgentPasswords
{
    public static function run(): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT id, password FROM agents');
        $update = $pdo->prepare('UPDATE agents SET password=:p WHERE id=:id');
        $updated = 0; $skipped = 0;
        foreach ($stmt->fetchAll() as $row) {
            $id = (int)$row['id'];
            $pass = (string)$row['password'];
            if ($pass === '' || self::looksLikeHash($pass)) { $skipped++; continue; }
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $update->execute([':p' => $hash, ':id' => $id]);
            $updated++;
        }
        echo "Updated: {$updated}, skipped: {$skipped}\n";
    }

    private static function looksLikeHash(string $value): bool
    {
        // password_hash default (bcrypt/argon) start with $2y$ or $argon2
        return str_starts_with($value, '$2y$') || str_starts_with($value, '$argon2');
    }
}

