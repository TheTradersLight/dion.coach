<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequireAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $path = $request->getUri()->getPath();
        if (preg_match('#^/camps/\d+/test-physique#', $path) || preg_match('#^/camps/\d+/test-physique-results#', $path)) {
            return $handler->handle($request);
        }

        // Rule: must be logged in
        if (empty($_SESSION['user_id'])) {
            $response = new \Slim\Psr7\Response();
            return $response
                ->withHeader('Location', '/')
                ->withStatus(302);
        }

        return $handler->handle($request);
    }
}
