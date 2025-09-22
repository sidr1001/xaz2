<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class ToursController
{
    public function index(Request $request, Response $response): Response
    {
        $pdo = Database::getConnection();
        $tours = $pdo->query('SELECT * FROM tours ORDER BY created_at DESC')->fetchAll();
        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/tours/index.twig', ['tours' => $tours]);
    }

    public function create(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/tours/form.twig');
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();
        $imagePath = null;
        if (isset($uploadedFiles['image']) && $uploadedFiles['image']->getError() === UPLOAD_ERR_OK) {
            $imagePath = $this->moveUploadedFile($uploadedFiles['image']);
        }

        $pdo = Database::getConnection();
        // Normalize dates and nullable fields
        $startDate = trim((string)($data['start_date'] ?? ''));
        $endDate = trim((string)($data['end_date'] ?? ''));
        $startDate = $startDate !== '' ? $startDate : null;
        $endDate = $endDate !== '' ? $endDate : null;
        $duration = trim((string)($data['duration_days'] ?? ''));
        $duration = $duration !== '' ? (int)$duration : null;
        $departureCity = trim((string)($data['departure_city'] ?? ''));
        $departureCity = $departureCity !== '' ? $departureCity : null;
        $tourType = $data['tour_type'] ?? null;

        $stmt = $pdo->prepare('INSERT INTO tours(title, description, city, region, country, price, start_date, end_date, tour_type, duration_days, departure_city, image, created_at) VALUES(:title, :description, :city, :region, :country, :price, :start_date, :end_date, :tour_type, :duration_days, :departure_city, :image, NOW())');
        $stmt->execute([
            ':title' => trim((string)($data['title'] ?? '')),
            ':description' => (string)($data['description'] ?? ''),
            ':city' => trim((string)($data['city'] ?? '')),
            ':region' => trim((string)($data['region'] ?? '')),
            ':country' => trim((string)($data['country'] ?? '')),
            ':price' => (float)($data['price'] ?? 0),
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':tour_type' => $tourType,
            ':duration_days' => $duration,
            ':departure_city' => $departureCity,
            ':image' => $imagePath,
        ]);

        // multiple images
        if (isset($uploadedFiles['images']) && is_array($uploadedFiles['images'])) {
            $tourId = (int)$pdo->lastInsertId();
            $stmtImg = $pdo->prepare('INSERT INTO tour_images(tour_id, path, thumb_path) VALUES(:t,:p,:tp)');
            foreach ($uploadedFiles['images'] as $img) {
                if ($img->getError() === UPLOAD_ERR_OK) {
                    $res = \App\Service\ImageService::processUpload($img, 1920, 480, true);
                    $stmtImg->execute([':t'=>$tourId, ':p'=>$res['path'], ':tp'=>$res['thumb']]);
                }
            }
        }

        return $response->withHeader('Location', '/admin/tours')->withStatus(302);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM tours WHERE id = :id');
        $stmt->execute([':id' => (int)$args['id']]);
        $tour = $stmt->fetch();
        if (!$tour) {
            $view = Twig::fromRequest($request);
            return $view->render($response->withStatus(404), '404.twig');
        }
        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/tours/form.twig', ['tour' => $tour]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = (array)$request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();
        $imagePath = null;
        if (isset($uploadedFiles['image']) && $uploadedFiles['image']->getError() === UPLOAD_ERR_OK) {
            $imagePath = $this->moveUploadedFile($uploadedFiles['image']);
        }

        $pdo = Database::getConnection();
        // Normalize dates and nullable fields for update
        $startDate = trim((string)($data['start_date'] ?? ''));
        $endDate = trim((string)($data['end_date'] ?? ''));
        $startDate = $startDate !== '' ? $startDate : null;
        $endDate = $endDate !== '' ? $endDate : null;
        $duration = trim((string)($data['duration_days'] ?? ''));
        $duration = $duration !== '' ? (int)$duration : null;
        $departureCity = trim((string)($data['departure_city'] ?? ''));
        $departureCity = $departureCity !== '' ? $departureCity : null;
        $tourType = $data['tour_type'] ?? null;

        $stmt = $pdo->prepare('UPDATE tours SET title=:title, description=:description, city=:city, region=:region, country=:country, price=:price, start_date=:start_date, end_date=:end_date, tour_type=:tour_type, duration_days=:duration_days, departure_city=:departure_city' . ($imagePath ? ', image=:image' : '') . ' WHERE id=:id');
        $params = [
            ':title' => trim((string)($data['title'] ?? '')),
            ':description' => (string)($data['description'] ?? ''),
            ':city' => trim((string)($data['city'] ?? '')),
            ':region' => trim((string)($data['region'] ?? '')),
            ':country' => trim((string)($data['country'] ?? '')),
            ':price' => (float)($data['price'] ?? 0),
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':tour_type' => $tourType,
            ':duration_days' => $duration,
            ':departure_city' => $departureCity,
            ':id' => $id,
        ];
        if ($imagePath) {
            $params[':image'] = $imagePath;
        }
        $stmt->execute($params);

        // multiple images
        if (isset($uploadedFiles['images']) && is_array($uploadedFiles['images'])) {
            $stmtImg = $pdo->prepare('INSERT INTO tour_images(tour_id, path, thumb_path) VALUES(:t,:p,:tp)');
            foreach ($uploadedFiles['images'] as $img) {
                if ($img->getError() === UPLOAD_ERR_OK) {
                    $res = \App\Service\ImageService::processUpload($img, 1920, 480, true);
                    $stmtImg->execute([':t'=>$id, ':p'=>$res['path'], ':tp'=>$res['thumb']]);
                }
            }
        }

        return $response->withHeader('Location', '/admin/tours')->withStatus(302);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM tours WHERE id = :id');
        $stmt->execute([':id' => (int)$args['id']]);
        return $response->withHeader('Location', '/admin/tours')->withStatus(302);
    }

    private function moveUploadedFile($uploadedFile): string
    {
        $directory = dirname(__DIR__, 3) . '/public/uploads';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $basename = bin2hex(random_bytes(8));
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = sprintf('%s.%s', $basename, $extension ?: 'jpg');
        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
        return '/uploads/' . $filename;
    }
}