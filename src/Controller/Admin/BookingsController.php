<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Database;
use App\Service\PdfService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class BookingsController
{
    public function index(Request $request, Response $response): Response
    {
        $pdo = Database::getConnection();
        $q = $request->getQueryParams();
        $cond = [];$p=[];
        if(isset($q['id']) && $q['id']!==''){ $cond[]='b.id = :id'; $p[':id']=(int)$q['id']; }
        if(isset($q['created_from']) && $q['created_from']!==''){ $cond[]='b.created_at >= :cf'; $p[':cf']=$q['created_from']; }
        if(isset($q['created_to']) && $q['created_to']!==''){ $cond[]='b.created_at <= :ct'; $p[':ct']=$q['created_to']; }
        if(isset($q['trip_from']) && $q['trip_from']!==''){ $cond[]='t.start_date >= :tf'; $p[':tf']=$q['trip_from']; }
        if(isset($q['trip_to']) && $q['trip_to']!==''){ $cond[]='t.end_date <= :tt'; $p[':tt']=$q['trip_to']; }
        if(isset($q['order_status']) && $q['order_status']!==''){ $cond[]='b.order_status = :os'; $p[':os']=$q['order_status']; }
        if(isset($q['payment_status']) && $q['payment_status']!==''){ $cond[]='b.payment_status = :ps'; $p[':ps']=$q['payment_status']; }
        $where = $cond ? ('WHERE '.implode(' AND ',$cond)) : '';
        $sql = "SELECT b.*, t.title AS tour_title, a.login AS agent_login FROM bookings b LEFT JOIN tours t ON t.id=b.tour_id LEFT JOIN agents a ON a.id=b.agent_id $where ORDER BY b.created_at DESC";
        $stmt = $pdo->prepare($sql);
        foreach($p as $k=>$v){ $stmt->bindValue($k,$v); }
        $stmt->execute();
        // Explicitly record this filtered query in SQL Debug log
        try { \App\Service\Database::logQuery($sql, $p, 0.0); } catch (\Throwable $e) {}
        $list = $stmt->fetchAll();
        $view = Twig::fromRequest($request);
        $settings = \App\Service\SettingsService::getAll();
        $queryLog = $settings['sql_debug_enabled'] === '1' ? \App\Service\Database::getQueryLog() : [];
        return $view->render($response, 'admin/bookings/index.twig', [
            'bookings' => $list,
            'filters'=>$q,
            'breadcrumbs' => [
                ['title' => 'Админка', 'url' => '/admin'],
                ['title' => 'Заявки'],
            ],
            'settings' => $settings,
            'query_log' => $queryLog,
        ]);
    }

    public function view(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT b.*, a.login AS agent_login FROM bookings b LEFT JOIN agents a ON a.id=b.agent_id WHERE b.id=:id');
        $stmt->execute([':id' => $id]);
        $booking = $stmt->fetch();
        if (!$booking) {
            $view = Twig::fromRequest($request);
            return $view->render($response->withStatus(404), '404.twig');
        }
        // Fallback: read agent_comment from file if not in DB
        if (!isset($booking['agent_comment']) || $booking['agent_comment'] === null || $booking['agent_comment'] === '') {
            $cfile = dirname(__DIR__, 3) . '/public/uploads/documents/' . $id . '/agent_comment.txt';
            if (is_file($cfile)) { $booking['agent_comment'] = trim((string)file_get_contents($cfile)); }
        }
        $tourists = $pdo->prepare('SELECT * FROM tourists WHERE booking_id=:b');
        $tourists->execute([':b' => $id]);
        $touristsList = $tourists->fetchAll();

        // Fallback: read agent_comment from file if not in DB
        if (!isset($booking['agent_comment']) || $booking['agent_comment'] === null || $booking['agent_comment'] === '') {
            $cfile = dirname(__DIR__, 3) . '/public/uploads/documents/' . $id . '/agent_comment.txt';
            if (is_file($cfile)) { $booking['agent_comment'] = trim((string)file_get_contents($cfile)); }
        }

        $view = Twig::fromRequest($request);
        $settings = \App\Service\SettingsService::getAll();
        $queryLog = $settings['sql_debug_enabled'] === '1' ? \App\Service\Database::getQueryLog() : [];
        return $view->render($response, 'admin/bookings/view.twig', [
            'booking' => $booking,
            'tourists' => $touristsList,
            'breadcrumbs' => [
                ['title' => 'Админка', 'url' => '/admin'],
                ['title' => 'Заявки', 'url' => '/admin/bookings'],
                ['title' => 'Заявка #'.$id],
            ],
            'settings' => $settings,
            'query_log' => $queryLog,
        ]);
    }

    public function paymentStatus(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = (array)$request->getParsedBody();
        $status = in_array(($data['payment_status'] ?? ''), ['paid','unpaid','cancelled','partial'], true) ? $data['payment_status'] : 'unpaid';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE bookings SET payment_status=:s WHERE id=:id');
        $stmt->execute([':s' => $status, ':id' => $id]);
        $response->getBody()->write(json_encode(['ok'=>true,'message'=>'Статус оплаты обновлен'], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }

    public function orderStatus(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = (array)$request->getParsedBody();
        $allowed = ['new','in_progress','paid','cancel_request','cancelled','waitlist','confirmed','rejected','on_request'];
        $status = in_array(($data['order_status'] ?? ''), $allowed, true) ? $data['order_status'] : 'new';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE bookings SET order_status=:s WHERE id=:id');
        $stmt->execute([':s' => $status, ':id' => $id]);
        $response->getBody()->write(json_encode(['ok'=>true,'message'=>'Статус заявки обновлен'], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }

    public function generateDocuments(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT b.*, t.title AS tour_title FROM bookings b LEFT JOIN tours t ON t.id=b.tour_id WHERE b.id=:id');
        $stmt->execute([':id' => $id]);
        $booking = $stmt->fetch();
        if (!$booking) {
            $response->getBody()->write(json_encode(['ok'=>false, 'error'=>'Booking not found']));
            return $response->withHeader('Content-Type','application/json');
        }

        $dir = dirname(__DIR__, 3) . '/public/uploads/documents/' . $id;
        $files = [];
        foreach (['contract','insurance','voucher','tickets'] as $doc) {
            $filepath = $dir . '/' . $doc . '.pdf';
            PdfService::renderTemplateToFile($request, 'documents/' . $doc . '.twig', ['booking' => $booking], $filepath);
            $files[$doc] = str_replace(dirname(__DIR__, 3) . '/public', '', $filepath);
        }

        $response->getBody()->write(json_encode(['ok'=>true, 'files'=>$files], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }
}

