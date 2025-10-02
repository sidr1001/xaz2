<?php
declare(strict_types=1);

namespace App\Controller\Agent;

use App\Service\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class AuthController
{
    public function loginForm(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/login.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM agents WHERE login=:l');
        $stmt->execute([':l' => $data['login'] ?? '']);
        $agent = $stmt->fetch();
        $password = (string)($data['password'] ?? '');
        if ($agent && is_string($agent['password']) && (
            password_verify($password, (string)$agent['password']) || $password === (string)$agent['password']
        )) {
            if (session_status() === PHP_SESSION_ACTIVE) { @session_regenerate_id(true); }
            $_SESSION['agent_id'] = (int)$agent['id'];
            return $response->withHeader('Location', '/agent')->withStatus(302);
        }
        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/login.twig', ['error' => 'Неверные данные']);
    }

    public function logout(Request $request, Response $response): Response
    {
        unset($_SESSION['agent_id']);
        return $response->withHeader('Location', '/agent/login')->withStatus(302);
    }
}

