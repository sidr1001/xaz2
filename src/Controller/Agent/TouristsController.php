<?php
declare(strict_types=1);

namespace App\Controller\Agent;

use App\Service\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class TouristsController
{
    public function index(Request $request, Response $response): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT t.* FROM tourists t INNER JOIN bookings b ON b.id=t.booking_id WHERE b.agent_id=:a ORDER BY t.created_at DESC');
        $stmt->execute([':a' => $agentId]);
        $tourists = $stmt->fetchAll();
        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/tourists/index.twig', [
            'tourists' => $tourists,
        ]);
    }

    public function search(Request $request, Response $response): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $q = (string)($request->getQueryParams()['q'] ?? '');
        $q = trim($q);
        if ($q === '') {
            $response->getBody()->write(json_encode(['ok'=>true,'items'=>[]], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type','application/json');
        }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT DISTINCT t.full_name, t.birth_date, t.passport, t.phone, t.email FROM tourists t INNER JOIN bookings b ON b.id=t.booking_id WHERE b.agent_id=:a AND t.full_name LIKE :q ORDER BY t.full_name LIMIT 20');
        $stmt->execute([':a' => $agentId, ':q' => $q.'%']);
        $items = $stmt->fetchAll();
        $response->getBody()->write(json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }
}

