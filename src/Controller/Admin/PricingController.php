<?php
declare(strict_types=1);

namespace App\Controller\Agent;

use App\Service\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class PricingController
{
    public function form(Request $request, Response $response, array $args): Response
    {
        $tourId = (int)$args['tour_id'];
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM agent_prices WHERE agent_id=:a AND tour_id=:t');
        $stmt->execute([':a' => $agentId, ':t' => $tourId]);
        $pricing = $stmt->fetch();
        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/pricing/form.twig', [
            'tour_id' => $tourId,
            'pricing' => $pricing,
        ]);
    }

    public function save(Request $request, Response $response, array $args): Response
    {
        $tourId = (int)$args['tour_id'];
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $data = (array)$request->getParsedBody();
        $type = in_array(($data['type'] ?? ''), ['fixed','percent'], true) ? $data['type'] : 'fixed';
        $value = (float)($data['value'] ?? 0);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO agent_prices(agent_id, tour_id, type, value) VALUES(:a,:t,:ty,:v) ON DUPLICATE KEY UPDATE type=:ty2, value=:v2');
        $stmt->execute([':a'=>$agentId, ':t'=>$tourId, ':ty'=>$type, ':v'=>$value, ':ty2'=>$type, ':v2'=>$value]);
        return $response->withHeader('Location', '/agent')->withStatus(302);
    }
}
