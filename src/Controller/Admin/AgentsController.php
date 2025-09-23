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
        return $view->render($response, 'admin/agents/index.twig', ['agents' => $agents]);
    }

    public function create(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/agents/form.twig');
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO agents(login, password, permissions, created_at) VALUES(:login, :password, :permissions, NOW())');
        $stmt->execute([
            ':login' => trim((string)($data['login'] ?? '')),
            ':password' => trim((string)($data['password'] ?? '')),
            ':permissions' => trim((string)($data['permissions'] ?? '')),
        ]);
        return $response->withHeader('Location', '/admin/agents')->withStatus(302);
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
        return $view->render($response, 'admin/agents/form.twig', ['agent' => $agent, 'profile'=>$profile, 'contract_uploaded'=>$hasContract, 'contract_url'=>$hasContract ? $contractPath : null]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = (array)$request->getParsedBody();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE agents SET login=:login, password=:password, permissions=:permissions WHERE id=:id');
        $stmt->execute([
            ':login' => trim((string)($data['login'] ?? '')),
            ':password' => trim((string)($data['password'] ?? '')),
            ':permissions' => trim((string)($data['permissions'] ?? '')),
            ':id' => $id,
        ]);
        return $response->withHeader('Location', '/admin/agents')->withStatus(302);
    }

    public function contractUpload(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $files = $request->getUploadedFiles();
        $file = $files['contract'] ?? null;
        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            return $response->withHeader('Location', '/admin/agents/'.$id.'/edit?error=upload')->withStatus(302);
        }
        $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            return $response->withHeader('Location', '/admin/agents/'.$id.'/edit?error=type')->withStatus(302);
        }
        $dir = dirname(__DIR__,3).'/public/uploads/agents/'.$id;
        if(!is_dir($dir)) mkdir($dir, 0777, true);
        $file->moveTo($dir.'/contract.pdf');
        return $response->withHeader('Location', '/admin/agents/'.$id.'/edit')->withStatus(302);
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
        return $response->withHeader('Location', '/admin/agents')->withStatus(302);
    }
}

