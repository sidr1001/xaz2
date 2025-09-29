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
        // Agent commission percent to adjust displayed prices
        $agentCommissionPercent = 0.0;
        try {
            $stmtA = $pdo->prepare('SELECT agent_commission_percent FROM agents WHERE id=:id');
            $stmtA->execute([':id' => $agentId]);
            $agentCommissionPercent = (float)($stmtA->fetchColumn() ?: 0);
        } catch (\Throwable $e) {}

        // Filters
        $q = $request->getQueryParams();
        $conditions = [];
        $params = [];
        if (isset($q['q']) && $q['q'] !== '') { $conditions[] = '(title LIKE :q OR city LIKE :q OR country LIKE :q)'; $params[':q'] = '%'.$q['q'].'%'; }
        if (isset($q['country']) && $q['country'] !== '') { $conditions[] = 'country = :country'; $params[':country'] = $q['country']; }
        if (isset($q['region']) && $q['region'] !== '') { $conditions[] = 'region = :region'; $params[':region'] = $q['region']; }
        if (isset($q['city']) && $q['city'] !== '') { $conditions[] = 'city = :city'; $params[':city'] = $q['city']; }
        if (isset($q['start_date']) && $q['start_date'] !== '') { $conditions[] = 'start_date >= :start_date'; $params[':start_date'] = $q['start_date']; }
        if (isset($q['end_date']) && $q['end_date'] !== '') { $conditions[] = 'end_date <= :end_date'; $params[':end_date'] = $q['end_date']; }
        if (isset($q['min_price']) && $q['min_price'] !== '') { $conditions[] = 'price >= :min_price'; $params[':min_price'] = (float)$q['min_price']; }
        if (isset($q['max_price']) && $q['max_price'] !== '') { $conditions[] = 'price <= :max_price'; $params[':max_price'] = (float)$q['max_price']; }
        $where = $conditions ? ('WHERE '.implode(' AND ', $conditions)) : '';

        // Paging and view
        $perPage = max(1, min(60, (int)($q['per_page'] ?? 12)));
        $page = max(1, (int)($q['page'] ?? 1));
        $offset = ($page-1)*$perPage;
        $stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM tours {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $tours = $stmt->fetchAll();
        $total = (int)$pdo->query('SELECT FOUND_ROWS()')->fetchColumn();

        // Seats taken per tour
        $takenMap = [];
        try {
            $q = $pdo->query('SELECT tour_id, COUNT(*) AS c FROM booking_seats GROUP BY tour_id');
            foreach ($q->fetchAll() as $row) {
                $takenMap[(int)$row['tour_id']] = (int)$row['c'];
            }
        } catch (\Throwable $e) {}

        // Filter value sources (disable if none)
        $vals = [
            'countries' => $pdo->query('SELECT DISTINCT country FROM tours WHERE country IS NOT NULL AND country<>"" ORDER BY country')->fetchAll(\PDO::FETCH_COLUMN),
            'regions' => $pdo->query('SELECT DISTINCT region FROM tours WHERE region IS NOT NULL AND region<>"" ORDER BY region')->fetchAll(\PDO::FETCH_COLUMN),
            'cities' => $pdo->query('SELECT DISTINCT city FROM tours WHERE city IS NOT NULL AND city<>"" ORDER BY city')->fetchAll(\PDO::FETCH_COLUMN),
        ];
        $priceMin = (float)$pdo->query('SELECT COALESCE(MIN(price),0) FROM tours')->fetchColumn();
        $priceMax = (float)$pdo->query('SELECT COALESCE(MAX(price),0) FROM tours')->fetchColumn();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/dashboard.twig', [
            'tours' => $tours,
            'filters' => $q,
            'perPage' => $perPage,
            'page' => $page,
            'total' => $total,
            'values' => $vals,
            'priceMin' => (int)$priceMin,
            'priceMax' => (int)$priceMax,
            'breadcrumbs' => [
                ['title' => 'Кабинет агента']
            ],
            'agentCommissionPercent' => $agentCommissionPercent,
            'seatsTakenMap' => $takenMap,
        ]);
    }
}

