<?php
declare(strict_types=1);

namespace App\Service;

use PDO;

final class SettingsService
{
    public static function getAll(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT `key`, `value` FROM settings');
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['key']] = $row['value'];
        }
        // Defaults
        if (!isset($settings['bus_seat_selection_enabled'])) {
            $settings['bus_seat_selection_enabled'] = '0';
        }
        if (!isset($settings['card_image_width'])) {
            $settings['card_image_width'] = '480';
        }
        if (!isset($settings['card_image_height'])) {
            $settings['card_image_height'] = '320';
        }
        if (!isset($settings['operator_signature_path'])) {
            $settings['operator_signature_path'] = is_file(dirname(__DIR__,3).'/public/uploads/operator/signature.png') ? '/uploads/operator/signature.png' : null;
        }
        if (!isset($settings['operator_stamp_path'])) {
            $settings['operator_stamp_path'] = is_file(dirname(__DIR__,3).'/public/uploads/operator/stamp.png') ? '/uploads/operator/stamp.png' : null;
        }
        return $settings;
    }

    public static function set(string $key, ?string $value): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO settings(`key`, `value`) VALUES(:k,:v) ON DUPLICATE KEY UPDATE `value`=:v2');
        $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
    }
}

