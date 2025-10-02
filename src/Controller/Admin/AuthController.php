<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class AuthController
{
    public function loginForm(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'admin/login.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $login = $data['login'] ?? '';
        $password = $data['password'] ?? '';

        $validLogin = $_ENV['ADMIN_DEFAULT_LOGIN'] ?? 'admin';
        $validPassword = $_ENV['ADMIN_DEFAULT_PASSWORD'] ?? 'admin';

        if ($login === $validLogin && $password === $validPassword) {
            $_SESSION['admin'] = true;
            $response->getBody()->write(json_encode(['ok' => true, 'redirect' => '/admin'], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['ok' => false, 'error' => 'Неверный логин или пароль'], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function logout(Request $request, Response $response): Response
    {
        unset($_SESSION['admin']);
        return $response->withHeader('Location', '/admin/login')->withStatus(302);
    }
}

