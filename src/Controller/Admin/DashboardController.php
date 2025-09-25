<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class DashboardController
{
    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $settings = \App\Service\SettingsService::getAll();
        $queryLog = $settings['sql_debug_enabled'] === '1' ? \App\Service\Database::getQueryLog() : [];
        return $view->render($response, 'admin/dashboard.twig', [
            'breadcrumbs' => [
                ['title' => 'Админка'],
            ],
            'settings' => $settings,
            'query_log' => $queryLog,
        ]);
    }
}

