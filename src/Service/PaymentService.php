<?php
declare(strict_types=1);

namespace App\Service;

use App\Service\Database;
use PDO;

final class PaymentService
{
    public static function createInvoice(int $bookingId, float $amount): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO payments(booking_id, method, status, amount) VALUES(:b, :m, :s, :a)');
        $stmt->execute([':b' => $bookingId, ':m' => 'invoice', ':s' => 'pending', ':a' => $amount]);
        return (int)$pdo->lastInsertId();
    }

    public static function createOnline(int $bookingId, float $amount, array $meta = []): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO payments(booking_id, method, status, amount, meta) VALUES(:b, :m, :s, :a, :meta)');
        $stmt->execute([':b' => $bookingId, ':m' => 'online', ':s' => 'pending', ':a' => $amount, ':meta' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);
        return (int)$pdo->lastInsertId();
    }

    public static function setStatus(int $paymentId, string $status, ?string $externalId = null): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE payments SET status=:s, external_id=:e, paid_at=CASE WHEN :s2 = "paid" THEN NOW() ELSE paid_at END WHERE id=:id');
        $stmt->execute([':s' => $status, ':s2' => $status, ':e' => $externalId, ':id' => $paymentId]);
    }

    public static function getById(int $paymentId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE id=:id');
        $stmt->execute([':id' => $paymentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

