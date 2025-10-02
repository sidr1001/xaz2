<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

final class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxAttempts;
    private int $windowSeconds;

    public function __construct(int $maxAttempts = 10, int $windowSeconds = 300)
    {
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $key = 'rl_' . md5($ip . '|' . $request->getUri()->getPath());
        $now = time();
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'reset' => $now + $this->windowSeconds];
        }
        if ($now > (int)$_SESSION[$key]['reset']) {
            $_SESSION[$key] = ['count' => 0, 'reset' => $now + $this->windowSeconds];
        }
        $_SESSION[$key]['count'] = (int)$_SESSION[$key]['count'] + 1;
        if ((int)$_SESSION[$key]['count'] > $this->maxAttempts) {
            $resp = new SlimResponse(429);
            $resp->getBody()->write('Too Many Requests');
            return $resp->withHeader('Retry-After', (string)max(1, ((int)$_SESSION[$key]['reset'] - $now)));
        }
        return $handler->handle($request);
    }
}

