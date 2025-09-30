<?php
declare(strict_types=1);

namespace App\Controller\Agent;

use App\Service\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class TouristsController
{
    public function index(Request $request, Response $response): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT t.* FROM tourists t INNER JOIN bookings b ON b.id=t.booking_id WHERE b.agent_id=:a ORDER BY t.created_at DESC');
        $stmt->execute([':a' => $agentId]);
        $tourists = $stmt->fetchAll();
        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/tourists/index.twig', [
            'tourists' => $tourists,
            'breadcrumbs' => [
                ['title' => 'Кабинет агента', 'url' => '/agent'],
                ['title' => 'Туристы'],
            ],
        ]);
    }

    public function search(Request $request, Response $response): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $q = (string)($request->getQueryParams()['q'] ?? '');
        $q = trim($q);
        if ($q === '') {
            $response->getBody()->write(json_encode(['ok'=>true,'items'=>[]], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type','application/json');
        }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT DISTINCT t.full_name, t.birth_date, t.passport, t.phone, t.email FROM tourists t INNER JOIN bookings b ON b.id=t.booking_id WHERE b.agent_id=:a AND t.full_name LIKE :q ORDER BY t.full_name LIMIT 20');
        $stmt->execute([':a' => $agentId, ':q' => $q.'%']);
        $items = $stmt->fetchAll();
        $response->getBody()->write(json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $id = (int)$args['id'];
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT t.* FROM tourists t INNER JOIN bookings b ON b.id=t.booking_id WHERE t.id=:id AND b.agent_id=:a');
        $stmt->execute([':id'=>$id, ':a'=>$agentId]);
        $t = $stmt->fetch();
        if(!$t){ $view = Twig::fromRequest($request); return $view->render($response->withStatus(404), '404.twig'); }
        $files = self::listFiles($id);
        // Related bookings/tours for same person (dedup by booking_id)
        $related = [];
        try {
            $q = $pdo->prepare('SELECT DISTINCT b.id AS booking_id, tt2.id AS tourist_id, tr.title AS tour_title, tr.start_date, tr.end_date
                                 FROM tourists tt2
                                 INNER JOIN bookings b ON b.id=tt2.booking_id
                                 INNER JOIN tours tr ON tr.id=b.tour_id
                                 WHERE b.agent_id=:a AND tt2.full_name=:n AND COALESCE(tt2.birth_date, "")=COALESCE(:bd, "") AND COALESCE(tt2.passport, "")=COALESCE(:pp, "")
                                 ORDER BY b.created_at DESC');
            $q->execute([':a'=>$agentId, ':n'=>(string)($t['full_name']??''), ':bd'=>$t['birth_date']??null, ':pp'=>(string)($t['passport']??'')]);
            $related = $q->fetchAll();
        } catch (\Throwable $e) {}
        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/tourists/form.twig', [
            't'=>$t,
            'files'=>$files,
            'related'=>$related,
            'breadcrumbs' => [
                ['title' => 'Кабинет агента', 'url' => '/agent'],
                ['title' => 'Туристы', 'url' => '/agent/tourists'],
                ['title' => 'Редактировать'],
            ],
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $id = (int)$args['id'];
        $pdo = Database::getConnection();
        // access check
        $chk = $pdo->prepare('SELECT COUNT(*) FROM tourists t INNER JOIN bookings b ON b.id=t.booking_id WHERE t.id=:id AND b.agent_id=:a');
        $chk->execute([':id'=>$id, ':a'=>$agentId]);
        if((int)$chk->fetchColumn() === 0){ return $response->withStatus(403); }
        $d = (array)$request->getParsedBody();
        $stmt = $pdo->prepare('UPDATE tourists SET full_name=:n, birth_date=:b, passport=:p, phone=:ph, email=:e WHERE id=:id');
        $stmt->execute([':n'=>trim((string)($d['full_name']??'')), ':b'=>$d['birth_date']??null, ':p'=>trim((string)($d['passport']??'')), ':ph'=>trim((string)($d['phone']??'')), ':e'=>trim((string)($d['email']??'')), ':id'=>$id]);
        return $response->withHeader('Location','/agent/tourists/'.$id.'/edit')->withStatus(302);
    }

    public function upload(Request $request, Response $response, array $args): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $id = (int)$args['id'];
        $pdo = Database::getConnection();
        $chk = $pdo->prepare('SELECT COUNT(*) FROM tourists t INNER JOIN bookings b ON b.id=t.booking_id WHERE t.id=:id AND b.agent_id=:a');
        $chk->execute([':id'=>$id, ':a'=>$agentId]);
        if((int)$chk->fetchColumn() === 0){ return $response->withStatus(403); }
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? null;
        if (!$file) { return $response->withHeader('Location', '/agent/tourists/'.$id.'/edit?error=nofile')->withStatus(302); }
        if ($file->getSize() > 10*1024*1024) { return $response->withHeader('Location', '/agent/tourists/'.$id.'/edit?error=size')->withStatus(302); }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($file->getStream()->getContents());
        $file->getStream()->rewind();
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
        if (!array_key_exists($mime, $allowed)) {
            return $response->withHeader('Location', '/agent/tourists/'.$id.'/edit?error=type')->withStatus(302);
        }
        $dir = dirname(__DIR__, 3) . '/public/uploads/tourists/'.$id;
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $name = bin2hex(random_bytes(8)).'.'.$allowed[$mime];
        $path = $dir.'/'.$name;
        $file->moveTo($path);
        return $response->withHeader('Location','/agent/tourists/'.$id.'/edit')->withStatus(302);
    }

    public function deleteFile(Request $request, Response $response, array $args): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $id = (int)$args['id'];
        $pdo = Database::getConnection();
        $chk = $pdo->prepare('SELECT COUNT(*) FROM tourists t INNER JOIN bookings b ON b.id=t.booking_id WHERE t.id=:id AND b.agent_id=:a');
        $chk->execute([':id'=>$id, ':a'=>$agentId]);
        if((int)$chk->fetchColumn() === 0){ return $response->withStatus(403); }
        $d = (array)$request->getParsedBody();
        $file = basename((string)($d['file'] ?? ''));
        $path = dirname(__DIR__, 3) . '/public/uploads/tourists/'.$id.'/'.$file;
        if (is_file($path)) unlink($path);
        return $response->withHeader('Location','/agent/tourists/'.$id.'/edit')->withStatus(302);
    }

    private static function listFiles(int $touristId): array
    {
        $dir = dirname(__DIR__, 3) . '/public/uploads/tourists/'.$touristId;
        if (!is_dir($dir)) return [];
        $out = [];
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $out[] = [ 'name'=>$f, 'url'=>'/uploads/tourists/'.$touristId.'/'.$f ];
        }
        return $out;
    }
}

