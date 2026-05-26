<?php
namespace App\Controllers;

use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use PDO;
use Exception;

/**
 * Autenticación exclusiva para administradores del panel Analytics.
 * Sin registro público: los admins se crean directamente en la base de datos.
 */
class AdminAuthController extends BaseController
{
    public function __construct(ContainerInterface $c)
    {
        parent::__construct($c);
    }

    /**
     * POST /api/admin/auth/login
     * Body: { "email": "...", "password": "..." }
     */
    public function login(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->getJsonInput($request);
            if (!$data) {
                return $this->errorResponse($response, 'Datos JSON inválidos', 400);
            }

            $email = strtolower(trim((string) ($data['email'] ?? '')));
            $password = (string) ($data['password'] ?? '');

            if ($email === '' || $password === '') {
                return $this->errorResponse($response, 'Email y contraseña son requeridos', 400);
            }

            $db = $this->getDatabase();
            $this->ensureAdminTable($db);

            $stmt = $db->prepare('
                SELECT id, email, password_hash, nombre, activo
                FROM admin_usuarios
                WHERE email = ? AND activo = 1
                LIMIT 1
            ');
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                return $this->errorResponse($response, 'Credenciales incorrectas', 401);
            }

            $hash = (string) $admin['password_hash'];
            $passwordValid = false;

            if ($this->isBcryptHash($hash)) {
                $passwordValid = password_verify($password, $hash);
            } else {
                // Compatibilidad: si guardaron la clave en texto plano por error en BD
                $passwordValid = hash_equals($hash, $password);
                if ($passwordValid) {
                    $newHash = password_hash($password, PASSWORD_BCRYPT);
                    $upd = $db->prepare('UPDATE admin_usuarios SET password_hash = ? WHERE id = ?');
                    $upd->execute([$newHash, $admin['id']]);
                }
            }

            if (!$passwordValid) {
                $hint = !$this->isBcryptHash($hash)
                    ? 'La contraseña en BD no está hasheada. Use: php database/hash_admin_password.php "su_clave"'
                    : 'Credenciales incorrectas';
                return $this->errorResponse($response, $hint, 401);
            }

            $payload = [
                'iss' => 'cuestionario-api-admin',
                'aud' => 'analytics-dashboard',
                'iat' => time(),
                'exp' => time() + (60 * 60 * 8),
                'data' => [
                    'id' => (int) $admin['id'],
                    'email' => $admin['email'],
                    'nombre' => $admin['nombre'],
                    'tipo' => 'admin',
                ],
            ];

            $token = JWT::encode($payload, $this->jwtSecret, 'HS256');

            return $this->successResponse($response, 'Acceso administrativo concedido', [
                'token' => $token,
                'admin' => [
                    'id' => (int) $admin['id'],
                    'email' => $admin['email'],
                    'nombre' => $admin['nombre'],
                    'tipo' => 'admin',
                ],
            ]);
        } catch (Exception $e) {
            error_log('Error en admin login: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error interno del servidor', 500);
        }
    }

    /**
     * GET /api/admin/auth/verify
     */
    public function verifyToken(Request $request, Response $response, array $args): Response
    {
        try {
            $token = $this->getBearerToken($request);
            if (!$token) {
                return $this->errorResponse($response, 'Token requerido', 401);
            }

            $decoded = $this->verifyAdminJwtToken($token);
            if (!$decoded) {
                return $this->errorResponse($response, 'Token de administrador inválido', 401);
            }

            $admin = $this->getAdminFromToken($decoded);

            return $this->successResponse($response, 'Sesión administrativa válida', [
                'autenticado' => true,
                'admin' => $admin,
            ]);
        } catch (Exception $e) {
            error_log('Error en admin verify: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error al verificar sesión', 500);
        }
    }

    /**
     * POST /api/admin/auth/logout
     */
    public function logout(Request $request, Response $response, array $args): Response
    {
        return $this->successResponse($response, 'Sesión administrativa cerrada', []);
    }

    private function isBcryptHash(string $hash): bool
    {
        return str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2b$');
    }

    private function ensureAdminTable(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS admin_usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(120) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                nombre VARCHAR(100) NOT NULL DEFAULT 'Administrador',
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_admin_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
