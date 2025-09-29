<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class TourController
{
    public function view(Request $request, Response $response, array $args): Response
    {
        $view = Twig::fromRequest($request);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM tours WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)($args['id'] ?? 0)]);
        $tour = $stmt->fetch();
        if (!$tour) {
            return $view->render($response->withStatus(404), '404.twig');
        }
        $qp = $request->getQueryParams();
        if (($qp['ajax'] ?? null) === 'meta') {
            $enabled = (\App\Service\SettingsService::getAll()['bus_seat_selection_enabled'] ?? '0') === '1';
            $taken = [];
            if ($enabled && ($tour['tour_type'] ?? null) === 'bus') {
                try {
                    $stmt2 = $pdo->prepare('SELECT seat_number FROM booking_seats WHERE tour_id=:tid');
                    $stmt2->execute([':tid' => (int)$tour['id']]);
                    foreach ($stmt2->fetchAll() as $row) {
                        $s = (int)($row['seat_number'] ?? 0);
                        if ($s > 0) { $taken[$s] = true; }
                    }
                } catch (\Throwable $e) {
                    // If table doesn't exist yet, ignore
                }
            }
            $seatsTotal = 40;
            if (isset($tour['bus_seats_total']) && (int)$tour['bus_seats_total'] > 0) {
                $seatsTotal = (int)$tour['bus_seats_total'];
            }
            $payload = [
                'ok' => true,
                'tour_type' => $tour['tour_type'] ?? null,
                'seat_enabled' => $enabled,
                'seats' => $seatsTotal,
                'taken' => array_keys($taken),
                'price' => isset($tour['price']) ? (float)$tour['price'] : null,
            ];
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }
        return $view->render($response, 'tour_view.twig', ['tour' => $tour]);
    }
}

