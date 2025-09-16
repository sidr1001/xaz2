<?php
declare(strict_types=1);

namespace App\Controller\Agent;

use App\Service\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class DashboardController
{
    public function index(Request $request, Response $response): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $pdo = Database::getConnection();
        $tours = $pdo->query('SELECT * FROM tours ORDER BY created_at DESC')->fetchAll();
        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/dashboard.twig', [
            'tours' => $tours,
        ]);
    }
}

