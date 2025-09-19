<?php
declare(strict_types=1);

namespace App\Service;

use Arekaev\TBank\TBankClient;

final class TBankService
{
    private TBankClient $client;

    public function __construct()
    {
        $this->client = new TBankClient(
            (string)($_ENV['TBANK_TERMINAL_KEY'] ?? ''),
            (string)($_ENV['TBANK_TERMINAL_PASSWORD'] ?? '')
        );
    }

    public function initPayment(int $paymentId, int $bookingId, float $amount, string $description): array
    {
        $callback = (string)($_ENV['TBANK_CALLBACK_URL'] ?? '');
        $resp = $this->client->init(
            (string)$paymentId,
            (int)round($amount * 100),
            $description,
            $callback,
            $callback,
            ['booking_id' => $bookingId]
        );
        return $resp;
    }

    public function parseCallback(array $payload): array
    {
        // Map to PaymentService: orderId is our payment_id inside createOnline
        $status = (string)($payload['Status'] ?? '');
        $success = in_array($status, ['CONFIRMED','AUTHORIZED','COMPLETED'], true);
        return [
            'orderId' => (int)($payload['OrderId'] ?? 0),
            'status' => $status,
            'success' => $success,
        ];
    }
}

