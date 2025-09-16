<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\PaymentService;
use App\Service\TBankService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PaymentWebhookController
{
    public function tbank(Request $request, Response $response): Response
    {
        $payload = (array)$request->getParsedBody();
        $service = new TBankService();
        $parsed = $service->parseCallback($payload);
        $status = $parsed['success'] ? 'paid' : 'failed';
        PaymentService::setStatus((int)$parsed['orderId'], $status, null);
        $response->getBody()->write('OK');
        return $response;
    }
}
