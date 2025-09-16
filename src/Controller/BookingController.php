<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Csrf;
use App\Service\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class BookingController
{
    public function create(Request $request, Response $response, array $args): Response
    {
        $tourId = (int)($args['id'] ?? 0);
        $view = Twig::fromRequest($request);
        return $view->render($response, 'booking/form.twig', [
            'tour_id' => $tourId,
            'csrf' => Csrf::token(),
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO bookings(tour_id, agent_id, customer_name, customer_phone, customer_email, status, total_amount) VALUES(:tour_id, :agent_id, :name, :phone, :email, :status, :amount)');
            $stmt->execute([
                ':tour_id' => (int)($data['tour_id'] ?? 0),
                ':agent_id' => $_SESSION['agent_id'] ?? null,
                ':name' => trim((string)($data['customer_name'] ?? '')),
                ':phone' => trim((string)($data['customer_phone'] ?? '')),
                ':email' => trim((string)($data['customer_email'] ?? '')),
                ':status' => 'pending',
                ':amount' => (float)($data['total_amount'] ?? 0),
            ]);
            $bookingId = (int)$pdo->lastInsertId();

            $tourists = $data['tourists'] ?? [];
            if (is_array($tourists)) {
                $stmtT = $pdo->prepare('INSERT INTO tourists(booking_id, full_name, birth_date, passport, phone, email) VALUES(:b,:n,:d,:p,:ph,:e)');
                foreach ($tourists as $t) {
                    $stmtT->execute([
                        ':b' => $bookingId,
                        ':n' => trim((string)($t['full_name'] ?? '')),
                        ':d' => $t['birth_date'] ?? null,
                        ':p' => trim((string)($t['passport'] ?? '')),
                        ':ph' => trim((string)($t['phone'] ?? '')),
                        ':e' => trim((string)($t['email'] ?? '')),
                    ]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $response->getBody()->write(json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['ok' => true, 'booking_id' => $bookingId]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

