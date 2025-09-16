<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Service\Csrf;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (in_array($request->getMethod(), ['POST','PUT','PATCH','DELETE'], true)) {
            $data = (array)$request->getParsedBody();
            $token = $data['_csrf'] ?? $request->getHeaderLine('X-CSRF-Token') ?: null;
            if (!Csrf::validate(is_string($token) ? $token : null)) {
                $response = new SlimResponse();
                $response->getBody()->write('Invalid CSRF token');
                return $response->withStatus(419);
            }
        }
        return $handler->handle($request);
    }
}

