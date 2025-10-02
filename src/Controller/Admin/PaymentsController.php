<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Database;
use App\Service\PaymentService;
use App\Service\PdfService;
use App\Service\TBankService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class PaymentsController
{
    public function invoice(Request $request, Response $response, array $args): Response
    {
        $bookingId = (int)$args['booking_id'];
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id=:id');
        $stmt->execute([':id' => $bookingId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            return $response->withStatus(404);
        }
        $paymentId = PaymentService::createInvoice($bookingId, (float)$booking['total_amount']);

        $file = dirname(__DIR__, 3) . '/public/uploads/documents/' . $bookingId . '/invoice_' . $paymentId . '.pdf';
        PdfService::renderTemplateToFile($request, 'documents/invoice.twig', ['booking' => $booking, 'payment_id' => $paymentId], $file);

        $publicPath = str_replace(dirname(__DIR__, 3) . '/public', '', $file);
        $response->getBody()->write(json_encode(['ok'=>true,'redirect'=>$publicPath], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }

    public function online(Request $request, Response $response, array $args): Response
    {
        $bookingId = (int)$args['booking_id'];
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id=:id');
        $stmt->execute([':id' => $bookingId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            return $response->withStatus(404);
        }
        $paymentId = PaymentService::createOnline($bookingId, (float)$booking['total_amount'], ['provider' => 'tbank']);
        $tbank = new TBankService();
        $init = $tbank->initPayment($paymentId, $bookingId, (float)$booking['total_amount'], 'Оплата заявки #' . $bookingId);
        if (!empty($init['PaymentURL'])) {
            $response->getBody()->write(json_encode(['ok'=>true,'redirect'=>$init['PaymentURL']], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type','application/json');
        }
        $response->getBody()->write(json_encode(['ok'=>false,'error'=>'payment'], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }
}