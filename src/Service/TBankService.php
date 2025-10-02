<?php
declare(strict_types=1);

namespace App\Service;

use GuzzleHttp\Client as HttpClient;
use Arekaev\TbankPayment\TbankPayment;
use Arekaev\TbankPayment\Values\GetState as TbankGetState;
use Arekaev\TbankPayment\Enums\PaymentStatus as TbankPaymentStatus;
use Arekaev\TbankPayment\Values\Init as TbankInit;
use Arekaev\TbankPayment\Values\Item as TbankItem;
use Arekaev\TbankPayment\Values\Receipt as TbankReceipt;
use Arekaev\TbankPayment\Enums\Tax as TbankTax;
use Arekaev\TbankPayment\Enums\Taxation as TbankTaxation;

final class TBankService
{
    private TbankPayment $client;

    public function __construct()
    {
        $terminalKey = (string)($_ENV['TBANK_TERMINAL_KEY'] ?? '');
        $password = (string)($_ENV['TBANK_TERMINAL_PASSWORD'] ?? '');
        $testMode = (string)($_ENV['TBANK_TEST_MODE'] ?? 'true') === 'true';
        $apiUrl = $testMode ? 'https://rest-api-test.tinkoff.ru/v2' : 'https://securepay.tinkoff.ru/v2';
        $this->client = new TbankPayment(new HttpClient(), $terminalKey, $password, $apiUrl);
    }

    public function initPayment(int $paymentId, int $bookingId, float $amount, string $description): array
    {
        $callback = (string)($_ENV['TBANK_CALLBACK_URL'] ?? '');
        $data = TbankInit::make((string)$paymentId);
        $data->setAmount((int)round($amount * 100))
            ->setSuccessURL($callback)
            ->setFailURL($callback)
            ->setNotificationURL($callback);

        // Minimal receipt with one item (optional but recommended)
        $item = new TbankItem('Оплата заявки #'.$bookingId, 1, (int)round($amount * 100), (int)round($amount * 100), TbankTax::NONE);
        $receipt = TbankReceipt::make([$item]);
        // Optionally set email/phone if available later
        $receipt->setTaxation(TbankTaxation::USN_INCOME_OUTCOME);
        $data->setReceipt($receipt);

        $response = $this->client->init($data);
        // Unify return shape with previous controllers
        return [
            'PaymentURL' => $response->getPaymentUrl(),
            'PaymentId' => $response->getPaymentId(),
        ];
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

    public function getState(int $paymentId): array
    {
        $data = TbankGetState::make((string)$paymentId);
        $resp = $this->client->getState($data);
        $statusEnum = $resp->getStatus();
        $success = in_array($statusEnum, [TbankPaymentStatus::CONFIRMED, TbankPaymentStatus::AUTHORIZED, TbankPaymentStatus::COMPLETED], true);
        return [
            'status' => (string)$statusEnum->value,
            'success' => $success,
        ];
    }
}

