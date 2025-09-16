<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class TouristsController
{
    public function index(Request $request, Response $response): Response
    {
        $pdo = Database::getConnection();
        $list = $pdo->query('SELECT * FROM tourists ORDER BY created_at DESC')->fetchAll();
        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/tourists/index.twig', [
            'tourists' => $list,
        ]);
    }
}

