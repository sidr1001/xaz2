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
        $list = $pdo->query('SELECT MIN(id) AS id, full_name, MIN(birth_date) AS birth_date, MIN(passport) AS passport, MIN(phone) AS phone, MIN(email) AS email, MIN(created_at) AS created_at FROM tourists GROUP BY full_name, birth_date, passport ORDER BY created_at DESC')->fetchAll();
        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/tourists/index.twig', [
            'tourists' => $list,
            'breadcrumbs' => [
                ['title' => 'Админка', 'url' => '/admin'],
                ['title' => 'Туристы'],
            ],
        ]);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM tourists WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $t = $stmt->fetch();
        if (!$t) {
            $view = Twig::fromRequest($request);
            return $view->render($response->withStatus(404), '404.twig');
        }
        $files = self::listFiles($id);
        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/tourists/form.twig', [
            't' => $t,
            'files' => $files,
            'breadcrumbs' => [
                ['title' => 'Админка', 'url' => '/admin'],
                ['title' => 'Туристы', 'url' => '/admin/tourists'],
                ['title' => 'Редактировать'],
            ],
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $d = (array)$request->getParsedBody();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE tourists SET full_name=:n, birth_date=:b, passport=:p, phone=:ph, email=:e WHERE id=:id');
        $stmt->execute([
            ':n' => trim((string)($d['full_name'] ?? '')),
            ':b' => $d['birth_date'] ?? null,
            ':p' => trim((string)($d['passport'] ?? '')),
            ':ph' => trim((string)($d['phone'] ?? '')),
            ':e' => trim((string)($d['email'] ?? '')),
            ':id' => $id,
        ]);
        $response->getBody()->write(json_encode(['ok'=>true,'message'=>'Сохранено','redirect'=>'/admin/tourists/'.$id.'/edit'], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }

    public function upload(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? null;
        if (!$file) { $response->getBody()->write(json_encode(['ok'=>false,'error'=>'nofile'], JSON_UNESCAPED_UNICODE)); return $response->withHeader('Content-Type','application/json'); }
        if ($file->getSize() > 10*1024*1024) { $response->getBody()->write(json_encode(['ok'=>false,'error'=>'size'], JSON_UNESCAPED_UNICODE)); return $response->withHeader('Content-Type','application/json'); }
        $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($file->getStream()->getContents());
        $file->getStream()->rewind();
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
        if (!array_key_exists($mime, $allowed)) {
            $response->getBody()->write(json_encode(['ok'=>false,'error'=>'type'], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type','application/json');
        }
        $dir = dirname(__DIR__, 3) . '/public/uploads/tourists/'.$id;
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $name = bin2hex(random_bytes(8)).'.'.$allowed[$mime];
        $path = $dir.'/'.$name;
        $file->moveTo($path);
        $response->getBody()->write(json_encode(['ok'=>true,'message'=>'Файл загружен','redirect'=>'/admin/tourists/'.$id.'/edit'], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }

    public function deleteFile(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $d = (array)$request->getParsedBody();
        $file = basename((string)($d['file'] ?? ''));
        $path = dirname(__DIR__, 3) . '/public/uploads/tourists/'.$id.'/'.$file;
        if (is_file($path)) unlink($path);
        $response->getBody()->write(json_encode(['ok'=>true,'message'=>'Файл удален','redirect'=>'/admin/tourists/'.$id+'/edit'], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }

    private static function listFiles(int $touristId): array
    {
        $dir = dirname(__DIR__, 3) . '/public/uploads/tourists/'.$touristId;
        if (!is_dir($dir)) return [];
        $out = [];
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $out[] = [
                'name' => $f,
                'url' => '/uploads/tourists/'.$touristId.'/'.$f,
            ];
        }
        return $out;
    }
}

