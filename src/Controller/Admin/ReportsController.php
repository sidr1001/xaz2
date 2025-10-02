<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Database;
use App\Service\SettingsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class ReportsController
{
    public function index(Request $request, Response $response): Response
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT t.id, t.title, t.start_date, t.end_date, t.tour_type, COALESCE(t.bus_seats_total, 0) AS seats_total, COUNT(b.id) AS bookings_count FROM tours t LEFT JOIN bookings b ON b.tour_id=t.id GROUP BY t.id ORDER BY t.created_at DESC');
        $rows = $stmt->fetchAll();
        $settings = SettingsService::getAll();
        $queryLog = $settings['sql_debug_enabled'] === '1' ? Database::getQueryLog() : [];
        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/reports/index.twig', [
            'rows' => $rows,
            'settings' => $settings,
            'query_log' => $queryLog,
            'breadcrumbs' => [
                ['title' => 'Админка', 'url' => '/admin'],
                ['title' => 'Отчеты'],
            ],
        ]);
    }

    public function toursCsv(Request $request, Response $response): Response
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT t.id, t.title, t.start_date, t.end_date, COUNT(b.id) AS bookings_count FROM tours t LEFT JOIN bookings b ON b.tour_id=t.id GROUP BY t.id ORDER BY t.created_at DESC');
        $rows = $stmt->fetchAll();
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['ID', 'Название', 'Начало', 'Окончание', 'Кол-во заявок']);
        foreach ($rows as $r) {
            fputcsv($fh, [$r['id'], $r['title'], $r['start_date'], $r['end_date'], $r['bookings_count']]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        $response->getBody()->write($csv);
        return $response->withHeader('Content-Type', 'text/csv; charset=UTF-8')
                        ->withHeader('Content-Disposition', 'attachment; filename="tours_report.csv"');
    }
}

