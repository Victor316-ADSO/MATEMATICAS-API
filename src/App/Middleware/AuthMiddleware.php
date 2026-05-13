<?php
namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as Psr7Response;

/**
 * Middleware de autenticación JWT
 */
class AuthMiddleware
{
    /**
     * Invoca el middleware de autenticación
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader) {
            $response = new Psr7Response(401);
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Token no proporcionado (falta header Authorization)'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $arr = explode(' ', $authHeader);
        if (count($arr) !== 2 || $arr[0] !== 'Bearer') {
            $response = new Psr7Response(401);
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Formato de token inválido (debe ser: Bearer TOKEN)'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $token = $arr[1];

        try {
            if (empty($_ENV['JWT_SECRET'])) {
                $response = new Psr7Response(500);
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'JWT_SECRET no configurado en .env'
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            $requestWithUser = $request->withAttribute('user', $decoded->data);
            
            return $handler->handle($requestWithUser);
            
        } catch (\Exception $e) {
            $response = new Psr7Response(401);
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Token inválido o expirado',
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }
}
