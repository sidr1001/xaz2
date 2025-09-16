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
        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/agents/form.twig', ['agent' => $agent]);
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

    public function delete(Request $request, Response $response, array $args): Response
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM agents WHERE id = :id');
        $stmt->execute([':id' => (int)$args['id']]);
        return $response->withHeader('Location', '/admin/agents')->withStatus(302);
    }
}

