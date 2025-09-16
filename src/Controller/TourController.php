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
        return $view->render($response, 'tour_view.twig', ['tour' => $tour]);
    }
}

