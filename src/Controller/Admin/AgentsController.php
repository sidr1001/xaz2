<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class AgentsController
{
    public function index(Request $request, Response $response): Response
    {
        $pdo = Database::getConnection();
        $agents = $pdo->query('SELECT * FROM agents ORDER BY created_at DESC')->fetchAll();
        $view = Twig::fromRequest($request);
        $settings = \App\Service\SettingsService::getAll();
        $queryLog = $settings['sql_debug_enabled'] === '1' ? \App\Service\Database::getQueryLog() : [];
        return $view->render($response, 'admin/agents/index.twig', ['agents' => $agents, 'breadcrumbs'=>[
            ['title'=>'Админка','url'=>'/admin'],['title'=>'Агенты']
        ], 'settings'=>$settings, 'query_log'=>$queryLog]);
    }

    public function create(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $settings = \App\Service\SettingsService::getAll();
        $queryLog = $settings['sql_debug_enabled'] === '1' ? \App\Service\Database::getQueryLog() : [];
        return $view->render($response, 'admin/agents/form.twig', ['breadcrumbs'=>[
            ['title'=>'Админка','url'=>'/admin'],['title'=>'Агенты','url'=>'/admin/agents'],['title'=>'Добавить']
        ], 'settings'=>$settings, 'query_log'=>$queryLog]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $errors = [];
        if (trim((string)($data['login']??''))==='') { $errors['login']='Логин обязателен'; }
        if (trim((string)($data['password']??''))==='') { $errors['password']='Пароль обязателен'; }
        if ($errors) { $response->getBody()->write(json_encode(['ok'=>false,'errors'=>$errors], JSON_UNESCAPED_UNICODE)); return $response->withHeader('Content-Type','application/json'); }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO agents(login, password, permissions, agent_commission_percent, created_at) VALUES(:login, :password, :permissions, :acp, NOW())');
        $stmt->execute([
            ':login' => trim((string)($data['login'] ?? '')),
            ':password' => trim((string)($data['password'] ?? '')),
            ':permissions' => trim((string)($data['permissions'] ?? '')),
            ':acp' => (float)($data['agent_commission_percent'] ?? 0),
        ]);
        $response->getBody()->write(json_encode(['ok'=>true,'redirect'=>'/admin/agents'], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM agents WHERE id = :id');
        $stmt->execute([':id' => (int)$args['id']]);
        $agent = $stmt->fetch();
        if (!$agent) {
            $view = Twig::fromRequest($request);
            return $view->render($response->withStatus(404), '404.twig');
        }
        // Load agent profile (read-only on admin page)
        $p = $pdo->prepare('SELECT * FROM agent_profiles WHERE agent_id=:id');
        $p->execute([':id'=>(int)$args['id']]);
        $profile = $p->fetch() ?: [];
        // Check uploaded contract
        $contractPath = '/uploads/agents/'.(int)$args['id'].'/contract.pdf';
        $hasContract = is_file(dirname(__DIR__,3).'/public'.$contractPath);
        $view = Twig::fromRequest($request);
        $settings = \App\Service\SettingsService::getAll();
        $queryLog = $settings['sql_debug_enabled'] === '1' ? \App\Service\Database::getQueryLog() : [];
        return $view->render($response, 'admin/agents/form.twig', ['agent' => $agent, 'profile'=>$profile, 'contract_uploaded'=>$hasContract, 'contract_url'=>$hasContract ? $contractPath : null, 'breadcrumbs'=>[
            ['title'=>'Админка','url'=>'/admin'],['title'=>'Агенты','url'=>'/admin/agents'],['title'=>'Редактировать']
        ], 'settings'=>$settings, 'query_log'=>$queryLog]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = (array)$request->getParsedBody();
        $errors = [];
        if (trim((string)($data['login']??''))==='') { $errors['login']='Логин обязателен'; }
        if (trim((string)($data['password']??''))==='') { $errors['password']='Пароль обязателен'; }
        if ($errors) { $response->getBody()->write(json_encode(['ok'=>false,'errors'=>$errors], JSON_UNESCAPED_UNICODE)); return $response->withHeader('Content-Type','application/json'); }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE agents SET login=:login, password=:password, permissions=:permissions, agent_commission_percent=:acp WHERE id=:id');
        $stmt->execute([
            ':login' => trim((string)($data['login'] ?? '')),
            ':password' => trim((string)($data['password'] ?? '')),
            ':permissions' => trim((string)($data['permissions'] ?? '')),
            ':acp' => (float)($data['agent_commission_percent'] ?? 0),
            ':id' => $id,
        ]);
        $response->getBody()->write(json_encode(['ok'=>true,'redirect'=>'/admin/agents'], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }

    public function contractUpload(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $files = $request->getUploadedFiles();
        $file = $files['contract'] ?? null;
        if (!$file || $file->getError() !== UPLOAD_ERR_OK) { $response->getBody()->write(json_encode(['ok'=>false,'error'=>'Файл обязателен'], JSON_UNESCAPED_UNICODE)); return $response->withHeader('Content-Type','application/json'); }
        if ($file->getSize() > 10*1024*1024) { $response->getBody()->write(json_encode(['ok'=>false,'error'=>'Слишком большой файл'], JSON_UNESCAPED_UNICODE)); return $response->withHeader('Content-Type','application/json'); }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($file->getStream()->getContents());
        $file->getStream()->rewind();
        if ($mime !== 'application/pdf') { $response->getBody()->write(json_encode(['ok'=>false,'error'=>'Только PDF'], JSON_UNESCAPED_UNICODE)); return $response->withHeader('Content-Type','application/json'); }
        $dir = dirname(__DIR__,3).'/public/uploads/agents/'.$id;
        if(!is_dir($dir)) mkdir($dir, 0777, true);
        $file->moveTo($dir.'/contract.pdf');
        $response->getBody()->write(json_encode(['ok'=>true,'redirect'=>'/admin/agents/'.$id.'/edit'], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }

    public function contractMode(Request $request, Response $response, array $args): Response
    {
        // Placeholder to store mode in settings or agent-specific table if needed
        return $response->withHeader('Location', '/admin/agents/'.$args['id'].'/edit')->withStatus(302);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM agents WHERE id = :id');
        $stmt->execute([':id' => (int)$args['id']]);
        $response->getBody()->write(json_encode(['ok'=>true,'redirect'=>'/admin/agents'], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type','application/json');
    }
}

