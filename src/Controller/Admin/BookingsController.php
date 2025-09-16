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
        $list = $pdo->query('SELECT b.*, t.title AS tour_title, a.login AS agent_login FROM bookings b LEFT JOIN tours t ON t.id=b.tour_id LEFT JOIN agents a ON a.id=b.agent_id ORDER BY b.created_at DESC')->fetchAll();
        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/bookings/index.twig', ['bookings' => $list]);
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
        $tourists = $pdo->prepare('SELECT * FROM tourists WHERE booking_id=:b');
        $tourists->execute([':b' => $id]);
        $touristsList = $tourists->fetchAll();

        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/bookings/view.twig', [
            'booking' => $booking,
            'tourists' => $touristsList,
        ]);
    }

    public function status(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = (array)$request->getParsedBody();
        $status = in_array(($data['status'] ?? ''), ['pending','confirmed','cancelled'], true) ? $data['status'] : 'pending';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE bookings SET status=:s WHERE id=:id');
        $stmt->execute([':s' => $status, ':id' => $id]);
        return $response->withHeader('Location', '/admin/bookings/'.$id)->withStatus(302);
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

