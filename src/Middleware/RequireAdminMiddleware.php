<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

final class RequireAdminMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $roleId = (int)($_SESSION['role_id'] ?? 999);
        if ($roleId !== 0) {
            $response = new \Slim\Psr7\Response(403);
            $response->getBody()->write('Access denied');
            return $response;
        }

        return $handler->handle($request);
    }
}
