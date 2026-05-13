<?php
namespace App\Controllers;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use PDO;

/**
 * Controlador base con métodos comunes para todos los controladores
 */
class BaseController
{
    protected $container;
    protected $jwtSecret;

    public function __construct(ContainerInterface $c)
    {
        $this->container = $c;
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'default_secret_key';
    }

    /**
     * Obtiene datos JSON del request body
     * 
     * @param Request $request
     * @return array|null Datos decodificados o null si hay error
     */
    protected function getJsonInput(Request $request): ?array
    {
        $body = $request->getBody()->getContents();
        if (empty($body)) {
            return null;
        }
        $input = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $input;
    }

    /**
     * Sanitizar entrada de datos
     * 
     * @param string $input
     * @return string
     */
    protected function sanitizeInput(string $input): string
    {
        return htmlspecialchars(strip_tags(trim($input)));
    }

    /**
     * Sanitizar HTML permitiendo ciertas etiquetas
     * 
     * @param string $input
     * @return string
     */
    protected function sanitizeHtml(string $input): string
    {
        $allowedTags = '<p><strong><b><em><i><u><h1><h2><h3><ul><ol><li><br><a><img>';
        return strip_tags(trim($input), $allowedTags);
    }

    /**
     * Genera respuesta de éxito en formato JSON
     * 
     * @param Response $response
     * @param string $message Mensaje de éxito
     * @param array $data Datos adicionales
     * @return Response
     */
    protected function successResponse(Response $response, string $message, array $data = []): Response
    {
        $responseData = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus(200);
    }

    /**
     * Genera respuesta de error en formato JSON
     * 
     * @param Response $response
     * @param string $message Mensaje de error
     * @param int $statusCode Código de estado HTTP
     * @return Response
     */
    protected function errorResponse(Response $response, string $message, int $statusCode = 400): Response
    {
        $responseData = [
            'success' => false,
            'message' => $message,
            'error' => true,
            'status_code' => $statusCode,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($statusCode);
    }

    /**
     * Extrae el token JWT del header Authorization
     * 
     * @param Request $request
     * @return string|null Token o null si no existe
     */
    protected function getBearerToken(Request $request): ?string
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (empty($authHeader)) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Verifica y decodifica un token JWT
     * 
     * @param string $token
     * @return object|null Datos del token o null si es inválido
     */
    protected function verifyJwtToken(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            error_log("Error verificando JWT: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene la base de datos desde el contenedor
     * 
     * @return PDO
     */
    protected function getDatabase(): PDO
    {
        return $this->container->get('db');
    }
}
