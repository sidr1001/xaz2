<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class HomeController
{
    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $pdo = Database::getConnection();

        $queryParams = $request->getQueryParams();
        $conditions = [];
        $params = [];

        if (!empty($queryParams['q'])) {
            $conditions[] = '(title LIKE :q OR city LIKE :q OR country LIKE :q)';
            $params[':q'] = '%' . $queryParams['q'] . '%';
        }
        if (!empty($queryParams['min_price'])) {
            $conditions[] = 'price >= :min_price';
            $params[':min_price'] = (float)$queryParams['min_price'];
        }
        if (!empty($queryParams['max_price'])) {
            $conditions[] = 'price <= :max_price';
            $params[':max_price'] = (float)$queryParams['max_price'];
        }
        if (!empty($queryParams['city'])) {
            $conditions[] = 'city = :city';
            $params[':city'] = $queryParams['city'];
        }
        if (!empty($queryParams['region'])) {
            $conditions[] = 'region = :region';
            $params[':region'] = $queryParams['region'];
        }
        if (!empty($queryParams['country'])) {
            $conditions[] = 'country = :country';
            $params[':country'] = $queryParams['country'];
        }
        if (!empty($queryParams['start_date'])) {
            $conditions[] = 'start_date >= :start_date';
            $params[':start_date'] = $queryParams['start_date'];
        }
        if (!empty($queryParams['end_date'])) {
            $conditions[] = 'end_date <= :end_date';
            $params[':end_date'] = $queryParams['end_date'];
        }

        $page = max(1, (int)($queryParams['page'] ?? 1));
        $perPage = 9;
        $offset = ($page - 1) * $perPage;
        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM tours {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $tours = $stmt->fetchAll();
        $total = (int)$pdo->query('SELECT FOUND_ROWS()')->fetchColumn();
        $hasMore = $offset + count($tours) < $total;

        if (($queryParams['ajax'] ?? null) === '1') {
            $html = $view->fetch('partials/tour_cards.twig', ['tours' => $tours]);
            $response->getBody()->write(json_encode(['html' => $html, 'hasMore' => $hasMore], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $view->render($response, 'home.twig', [
            'tours' => $tours,
            'filters' => $queryParams,
            'hasMore' => $hasMore,
        ]);
    }
}