<?php
declare(strict_types=1);

namespace App\Service;

use Arekaev\TBank\Client as TBankClient;

final class TBankService
{
    private TBankClient $client;

    public function __construct()
    {
        $this->client = new TBankClient([
            'terminal_key' => $_ENV['TBANK_TERMINAL_KEY'] ?? '',
            'password' => $_ENV['TBANK_TERMINAL_PASSWORD'] ?? '',
        ]);
    }

    public function initPayment(int $paymentId, int $bookingId, float $amount, string $description): array
    {
        $callback = $_ENV['TBANK_CALLBACK_URL'] ?? '';
        $resp = $this->client->init([
            'OrderId' => (string)$paymentId,
            'Amount' => (int)round($amount * 100),
            'Description' => $description,
            'SuccessURL' => $callback,
            'FailURL' => $callback,
            'DATA' => [
                'booking_id' => $bookingId,
            ],
        ]);
        return $resp;
    }

    public function parseCallback(array $payload): array
    {
        // T-Bank callback contains OrderId and Status
        return [
            'orderId' => (int)($payload['OrderId'] ?? 0),
            'status' => (string)($payload['Status'] ?? ''),
            'success' => ($payload['Status'] ?? '') === 'CONFIRMED',
        ];
    }
}

