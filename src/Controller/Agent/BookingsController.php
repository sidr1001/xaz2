<?php
declare(strict_types=1);

namespace App\Controller\Agent;

use App\Service\Database;
use App\Service\PdfService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class BookingsController
{
    public function index(Request $request, Response $response): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $pdo = Database::getConnection();
        $q = $request->getQueryParams();
        $cond = ['b.agent_id = :a'];
        $p = [':a' => $agentId];
        if (isset($q['id']) && $q['id'] !== '') { $cond[] = 'b.id = :id'; $p[':id'] = (int)$q['id']; }
        if (isset($q['created_from']) && $q['created_from'] !== '') { $cond[] = 'b.created_at >= :cf'; $p[':cf'] = $q['created_from']; }
        if (isset($q['created_to']) && $q['created_to'] !== '') { $cond[] = 'b.created_at <= :ct'; $p[':ct'] = $q['created_to']; }
        if (isset($q['trip_from']) && $q['trip_from'] !== '') { $cond[] = 't.start_date >= :tf'; $p[':tf'] = $q['trip_from']; }
        if (isset($q['trip_to']) && $q['trip_to'] !== '') { $cond[] = 't.end_date <= :tt'; $p[':tt'] = $q['trip_to']; }
        if (isset($q['order_status']) && $q['order_status'] !== '') { $cond[] = 'b.order_status = :os'; $p[':os'] = $q['order_status']; }
        if (isset($q['payment_status']) && $q['payment_status'] !== '') { $cond[] = 'b.payment_status = :ps'; $p[':ps'] = $q['payment_status']; }
        $where = 'WHERE ' . implode(' AND ', $cond);
        $sql = "SELECT b.*, t.title AS tour_title, t.start_date, t.end_date FROM bookings b LEFT JOIN tours t ON t.id=b.tour_id $where ORDER BY b.created_at DESC";
        $stmt = $pdo->prepare($sql);
        foreach ($p as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->execute();
        $list = $stmt->fetchAll();
        // log agent filters into SQL Debug
        try { \App\Service\Database::logQuery($sql, $p, 0.0); } catch (\Throwable $e) {}
        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/bookings/index.twig', [
            'bookings' => $list,
            'filters' => $q,
            'breadcrumbs' => [
                ['title' => 'Кабинет агента', 'url' => '/agent'],
                ['title' => 'Заявки'],
            ],
            'agentCommissionPercent' => (float)($pdo->query('SELECT agent_commission_percent FROM agents WHERE id='.(int)$agentId)->fetchColumn() ?: 0),
        ]);
    }

    public function view(Request $request, Response $response, array $args): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $id = (int)$args['id'];
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT b.*, t.title AS tour_title FROM bookings b LEFT JOIN tours t ON t.id=b.tour_id WHERE b.id=:id AND b.agent_id=:a');
        $stmt->execute([':id' => $id, ':a' => $agentId]);
        $booking = $stmt->fetch();
        // Pull seats from booking_seats
        if ($booking) {
            try {
                $ss = $pdo->prepare('SELECT seat_number FROM booking_seats WHERE booking_id=:b ORDER BY seat_number');
                $ss->execute([':b'=>$id]);
                $booking['seats'] = array_map('intval', array_column($ss->fetchAll(), 'seat_number'));
            } catch (\Throwable $e) {}
        }
        if (!$booking) {
            $view = Twig::fromRequest($request);
            return $view->render($response->withStatus(404), '404.twig');
        }
        // Fallback: load agent_comment from file if column missing/empty
        if (!isset($booking['agent_comment']) || $booking['agent_comment'] === null || $booking['agent_comment'] === '') {
            $cfile = dirname(__DIR__, 3) . '/public/uploads/documents/' . $id . '/agent_comment.txt';
            if (is_file($cfile)) {
                $booking['agent_comment'] = trim((string)file_get_contents($cfile));
            }
        }
        $tourists = $pdo->prepare('SELECT * FROM tourists WHERE booking_id=:b');
        $tourists->execute([':b' => $id]);
        $touristsList = $tourists->fetchAll();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/bookings/view.twig', [
            'booking' => $booking,
            'tourists' => $touristsList,
            'breadcrumbs' => [
                ['title' => 'Кабинет агента', 'url' => '/agent'],
                ['title' => 'Заявки', 'url' => '/agent/bookings'],
                ['title' => 'Заявка #'.$id],
            ],
        ]);
    }

    public function comment(Request $request, Response $response, array $args): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $id = (int)$args['id'];
        $pdo = Database::getConnection();
        // access check
        $chk = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE id=:id AND agent_id=:a');
        $chk->execute([':id'=>$id, ':a'=>$agentId]);
        if ((int)$chk->fetchColumn() === 0) {
            return $response->withStatus(403);
        }
        $data = (array)$request->getParsedBody();
        $comment = trim((string)($data['agent_comment'] ?? ''));
        try {
            $stmt = $pdo->prepare('UPDATE bookings SET agent_comment=:c WHERE id=:id AND agent_id=:a');
            $stmt->execute([':c'=>$comment, ':id'=>$id, ':a'=>$agentId]);
        } catch (\Throwable $e) {
            // Fallback to filesystem storage if column doesn't exist
            $dir = dirname(__DIR__, 3) . '/public/uploads/documents/' . $id;
            if (!is_dir($dir)) { mkdir($dir, 0777, true); }
            file_put_contents($dir . '/agent_comment.txt', $comment);
        }
        return $response->withHeader('Location', '/agent/bookings/'.$id)->withStatus(302);
    }

    public function generateDocuments(Request $request, Response $response, array $args): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $id = (int)$args['id'];
        $pdo = Database::getConnection();
        // check access
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id=:id AND agent_id=:a');
        $stmt->execute([':id' => $id, ':a' => $agentId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            $response->getBody()->write(json_encode(['ok'=>false, 'error'=>'Not found']));
            return $response->withHeader('Content-Type','application/json');
        }

        $files = [];
        foreach (['contract','insurance','voucher','tickets'] as $doc) {
            $filepath = dirname(__DIR__, 3) . '/public/uploads/documents/' . $id . '/' . $doc . '.pdf';
            PdfService::renderTemplateToFile($request, 'documents/' . $doc . '.twig', ['booking' => $booking], $filepath);
            $files[$doc] = str_replace(dirname(__DIR__, 3) . '/public', '', $filepath);
        }

        $response->getBody()->write(json_encode(['ok'=>true, 'files'=>$files], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }
    public function create(Request $request, Response $response, array $args): Response
    {
        $tourId = (int)($args['tour_id'] ?? 0);
        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/bookings/form.twig', ['tour_id' => $tourId]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $pdo = Database::getConnection();
        // Validate bus seats if bus tour enabled
        $tourId = (int)($data['tour_id'] ?? 0);
        $meta = $pdo->prepare('SELECT tour_type, COALESCE(bus_seats_total,40) AS seats FROM tours WHERE id=:id');
        $meta->execute([':id'=>$tourId]);
        $tourMeta = $meta->fetch() ?: [];
        $isBus = ($tourMeta['tour_type'] ?? null) === 'bus';
        $seatsTotal = (int)($tourMeta['seats'] ?? 40);
        $taken = [];
        if ($isBus) {
            try {
                $q = $pdo->prepare('SELECT seat_number FROM booking_seats WHERE tour_id=:tid');
                $q->execute([':tid'=>$tourId]);
                foreach($q->fetchAll() as $row){ $s=(int)($row['seat_number']??0); if($s>0){ $taken[$s]=true; } }
            } catch (\Throwable $e) {}
        }
        $busSeatsStr = trim((string)($data['bus_seats'] ?? ''));
        $tourists = $data['tourists'] ?? [];
        if ($isBus) {
            $selected = array_values(array_filter(array_map(fn($x)=>(int)trim($x), explode(',', $busSeatsStr)), fn($x)=>$x>0));
            $selected = array_unique($selected);
            if (count($selected) !== count($tourists)) {
                return $response->withHeader('Location', '/agent/tours/'.$tourId.'/book?error=seats_mismatch')->withStatus(302);
            }
            if (count($selected) > $seatsTotal) {
                return $response->withHeader('Location', '/agent/tours/'.$tourId.'/book?error=bus_full')->withStatus(302);
            }
            foreach ($selected as $s) { if (isset($taken[$s])) { return $response->withHeader('Location', '/agent/tours/'.$tourId.'/book?error=seat_taken')->withStatus(302); } }
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO bookings(tour_id, agent_id, customer_name, customer_phone, customer_email, order_status, payment_status, total_amount, created_at) VALUES(:tour_id, :agent_id, :name, :phone, :email, :order_status, :payment_status, :amount, NOW())');
            $stmt->execute([
                ':tour_id' => $tourId,
                ':agent_id' => (int)($_SESSION['agent_id'] ?? 0),
                ':name' => trim((string)($data['customer_name'] ?? '')),
                ':phone' => trim((string)($data['customer_phone'] ?? '')),
                ':email' => trim((string)($data['customer_email'] ?? '')),
                ':order_status' => 'new',
                ':payment_status' => 'unpaid',
                ':amount' => (float)($data['total_amount'] ?? 0),
            ]);
            $bookingId = (int)$pdo->lastInsertId();

            // Persist each selected seat in booking_seats table
            if ($isBus) {
                $ins = $pdo->prepare('INSERT INTO booking_seats(booking_id, tour_id, seat_number) VALUES(:b,:t,:s)');
                foreach (array_unique(array_map('intval', array_filter(explode(',', $busSeatsStr)))) as $s) {
                    $ins->execute([':b'=>$bookingId, ':t'=>$tourId, ':s'=>$s]);
                }
            }

            $stmtT = $pdo->prepare('INSERT INTO tourists(booking_id, full_name, birth_date, passport, phone, email) VALUES(:b,:n,:d,:p,:ph,:e)');
            foreach ((array)$tourists as $t) {
                $stmtT->execute([
                    ':b' => $bookingId,
                    ':n' => trim((string)($t['full_name'] ?? '')),
                    ':d' => $t['birth_date'] ?? null,
                    ':p' => trim((string)($t['passport'] ?? '')),
                    ':ph' => trim((string)($t['phone'] ?? '')),
                    ':e' => trim((string)($t['email'] ?? '')),
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $response->getBody()->write(json_encode(['ok'=>false,'error'=>'Ошибка сохранения'], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type','application/json');
        }

        $response->getBody()->write(json_encode(['ok'=>true,'booking_id'=>$bookingId], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }
}

