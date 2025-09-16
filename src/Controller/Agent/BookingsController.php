<?php
declare(strict_types=1);

namespace App\Controller\Agent;

use App\Service\Database;
use App\Service\PdfService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class BookingsController
{
    public function index(Request $request, Response $response): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT b.*, t.title AS tour_title FROM bookings b LEFT JOIN tours t ON t.id=b.tour_id WHERE b.agent_id=:a ORDER BY b.created_at DESC');
        $stmt->execute([':a' => $agentId]);
        $list = $stmt->fetchAll();
        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/bookings/index.twig', ['bookings' => $list]);
    }

    public function view(Request $request, Response $response, array $args): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $id = (int)$args['id'];
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id=:id AND agent_id=:a');
        $stmt->execute([':id' => $id, ':a' => $agentId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            $view = Twig::fromRequest($request);
            return $view->render($response->withStatus(404), '404.twig');
        }
        $tourists = $pdo->prepare('SELECT * FROM tourists WHERE booking_id=:b');
        $tourists->execute([':b' => $id]);
        $touristsList = $tourists->fetchAll();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/bookings/view.twig', [
            'booking' => $booking,
            'tourists' => $touristsList,
        ]);
    }

    public function generateDocuments(Request $request, Response $response, array $args): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $id = (int)$args['id'];
        $pdo = Database::getConnection();
        // check access
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id=:id AND agent_id=:a');
        $stmt->execute([':id' => $id, ':a' => $agentId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            $response->getBody()->write(json_encode(['ok'=>false, 'error'=>'Not found']));
            return $response->withHeader('Content-Type','application/json');
        }

        $files = [];
        foreach (['contract','insurance','voucher','tickets'] as $doc) {
            $filepath = dirname(__DIR__, 3) . '/public/uploads/documents/' . $id . '/' . $doc . '.pdf';
            PdfService::renderTemplateToFile($request, 'documents/' . $doc . '.twig', ['booking' => $booking], $filepath);
            $files[$doc] = str_replace(dirname(__DIR__, 3) . '/public', '', $filepath);
        }

        $response->getBody()->write(json_encode(['ok'=>true, 'files'=>$files], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }
    public function create(Request $request, Response $response, array $args): Response
    {
        $tourId = (int)($args['tour_id'] ?? 0);
        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/bookings/form.twig', ['tour_id' => $tourId]);
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
                ':agent_id' => (int)($_SESSION['agent_id'] ?? 0),
                ':name' => trim((string)($data['customer_name'] ?? '')),
                ':phone' => trim((string)($data['customer_phone'] ?? '')),
                ':email' => trim((string)($data['customer_email'] ?? '')),
                ':status' => 'pending',
                ':amount' => (float)($data['total_amount'] ?? 0),
            ]);
            $bookingId = (int)$pdo->lastInsertId();

            $tourists = $data['tourists'] ?? [];
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

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $response->getBody()->write(json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $response->withHeader('Location', '/agent/bookings')->withStatus(302);
    }
}

