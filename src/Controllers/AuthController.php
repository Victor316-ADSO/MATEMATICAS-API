<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Firebase\JWT\JWT;
use PDO;
use PDOException;
use Exception;

/**
 * Controlador de autenticación
 */
class AuthController extends BaseController
{
    public function __construct(ContainerInterface $c)
    {
        parent::__construct($c);
    }

    /**
     * Login de usuarios (egresados)
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function login(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->getJsonInput($request);
            
            // Log para debugging
            error_log("Login attempt - Raw body: " . $request->getBody()->getContents());
            $request->getBody()->rewind(); // Rebobinar para que getJsonInput funcione
            $data = $this->getJsonInput($request);
            error_log("Login attempt - Parsed data: " . json_encode($data));
            
            if (!$data) {
                return $this->errorResponse($response, 'Datos JSON inválidos', 400);
            }

            $programa = trim((string) ($data['programa'] ?? $data['codi_prog'] ?? ''));
            $identificacion = trim((string) ($data['identificacion'] ?? $data['iden_pers'] ?? ''));

            if ($programa === '' || $identificacion === '') {
                return $this->errorResponse($response, 'Programa e identificación son requeridos', 400);
            }

            $db = $this->getDatabase();
            $stmt = $db->prepare('SELECT * FROM egresados WHERE codi_prog = ? AND iden_pers = ?');
            $stmt->execute([$programa, $identificacion]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $payload = [
                    'iss' => 'http://localhost',
                    'aud' => 'http://localhost',
                    'iat' => time(),
                    'exp' => time() + (60 * 60 * 2), // 2 horas
                    'data' => [
                        'iden_pers' => $user['iden_pers'],
                        'codi_prog' => $user['codi_prog']
                    ]
                ];

                $jwt = JWT::encode($payload, $this->jwtSecret, 'HS256');

                return $this->successResponse($response, 'Login exitoso', [
                    'token' => $jwt,
                    'user' => $user
                ]);
            }

            return $this->errorResponse(
                $response,
                'Usuario no encontrado. Verifica programa e identificación, o regístrate en la plataforma.',
                401
            );
            
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            return $this->errorResponse($response, 'Error interno del servidor', 500);
        }
    }

    /**
     * Registro mínimo: tipo de documento, número de documento (iden_pers) y programa (codi_prog).
     * Login: mismo programa + número de documento. Opcional: columna personas.codi_iden (ver database/alter_personas_codi_iden.sql).
     */
    public function registro(Request $request, Response $response, array $args): Response
    {
        $db = null;
        try {
            $data = $this->getJsonInput($request);
            if (!$data) {
                return $this->errorResponse($response, 'Datos JSON inválidos', 400);
            }

            $tipoDoc = strtoupper(trim((string) ($data['tipo_documento'] ?? $data['tipo_doc'] ?? $data['codi_iden'] ?? '')));
            $iden = isset($data['identificacion']) ? trim((string) $data['identificacion']) : '';
            if ($iden === '' && isset($data['iden_pers'])) {
                $iden = trim((string) $data['iden_pers']);
            }

            $codi = isset($data['programa']) ? trim((string) $data['programa']) : '';
            if ($codi === '' && isset($data['codi_prog'])) {
                $codi = trim((string) $data['codi_prog']);
            }
            if ($codi === '' && isset($data['programa_id'])) {
                $codi = trim((string) $data['programa_id']);
            }

            $tiposPermitidos = ['CC', 'CE', 'TI', 'PA', 'RC', 'MS', 'CD', 'SC', 'PE', 'PT'];
            if ($tipoDoc === '' || !in_array($tipoDoc, $tiposPermitidos, true)) {
                return $this->errorResponse($response, 'Seleccione un tipo de documento válido', 400);
            }

            if ($iden === '' || $codi === '') {
                return $this->errorResponse($response, 'Número de documento y programa son obligatorios', 400);
            }

            $db = $this->getDatabase();

            $dup = $db->prepare('SELECT 1 FROM egresados WHERE codi_prog = ? AND iden_pers = ? LIMIT 1');
            $dup->execute([$codi, $iden]);
            if ($dup->fetch()) {
                return $this->errorResponse($response, 'Ya existe un registro para esta identificación en el programa seleccionado', 409);
            }

            $nombPers = 'REGISTRO';
            $ape1Pers = '-';

            $db->beginTransaction();

            $stP = $db->prepare('SELECT 1 FROM personas WHERE iden_pers = ? LIMIT 1');
            $stP->execute([$iden]);
            $personaExiste = (bool) $stP->fetch(PDO::FETCH_NUM);

            if (!$personaExiste) {
                $tieneCodiIden = $this->personasTieneColumna($db, 'codi_iden');
                if ($tieneCodiIden) {
                    $insP = $db->prepare(
                        'INSERT INTO personas (iden_pers, codi_iden, nomb_pers, ape1_pers) VALUES (?, ?, ?, ?)'
                    );
                    $insP->execute([$iden, $tipoDoc, $nombPers, $ape1Pers]);
                } else {
                    $insP = $db->prepare('INSERT INTO personas (iden_pers, nomb_pers, ape1_pers) VALUES (?, ?, ?)');
                    $insP->execute([$iden, $nombPers, $ape1Pers]);
                }
            } elseif ($this->personasTieneColumna($db, 'codi_iden')) {
                $updT = $db->prepare('UPDATE personas SET codi_iden = ? WHERE iden_pers = ? AND (codi_iden IS NULL OR codi_iden = \'\')');
                $updT->execute([$tipoDoc, $iden]);
            }

            $insE = $db->prepare('INSERT INTO egresados (iden_pers, codi_prog) VALUES (?, ?)');
            $insE->execute([$iden, $codi]);

            $db->commit();

            return $this->successResponse($response, 'Registro exitoso. Ya puede iniciar sesión con su programa y número de documento.', [
                'iden_pers' => $iden,
                'codi_prog' => $codi,
                'tipo_documento' => $tipoDoc,
            ]);
        } catch (PDOException $e) {
            if ($db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error PDO en registro: ' . $e->getMessage());
            if (stripos($e->getMessage(), 'Duplicate') !== false
                || stripos($e->getMessage(), '1062') !== false
                || (string) $e->getCode() === '23000') {
                return $this->errorResponse($response, 'Datos duplicados o restricción en base de datos', 409);
            }
            return $this->errorResponse($response, 'Error al guardar el registro', 500);
        } catch (Exception $e) {
            if ($db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en registro: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error interno del servidor', 500);
        }
    }

    /**
     * Verifica si un token JWT es válido
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function verifyToken(Request $request, Response $response, array $args): Response
    {
        try {
            $token = $this->getBearerToken($request);
            
            if (!$token) {
                return $this->errorResponse($response, 'Token no proporcionado', 401);
            }
            
            $decoded = $this->verifyJwtToken($token);
            
            if (!$decoded) {
                return $this->errorResponse($response, 'Token inválido o expirado', 401);
            }
            
            $decodedUser = isset($decoded->data) ? (array) $decoded->data : (array) $decoded;

            $usuario = $decodedUser;
            $iden = $decodedUser['iden_pers'] ?? null;
            $codi = $decodedUser['codi_prog'] ?? null;
            if ($iden !== null && $iden !== '' && $codi !== null && $codi !== '') {
                try {
                    $db = $this->getDatabase();
                    $stmt = $db->prepare('SELECT * FROM egresados WHERE codi_prog = ? AND iden_pers = ?');
                    $stmt->execute([$codi, $iden]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $usuario = $row;
                    }
                } catch (Exception $e) {
                    error_log('verifyToken: no se pudo cargar egresado: ' . $e->getMessage());
                }
            }

            return $this->successResponse($response, 'Token válido', [
                'autenticado' => true,
                'usuario' => $usuario
            ]);
            
        } catch (Exception $e) {
            error_log("Error en verifyToken: " . $e->getMessage());
            return $this->errorResponse($response, 'Token inválido o expirado', 401);
        }
    }

    /**
     * Refresca un token JWT válido
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function refreshToken(Request $request, Response $response, array $args): Response
    {
        try {
            $token = $this->getBearerToken($request);
            
            if (!$token) {
                return $this->errorResponse($response, 'Token no proporcionado', 401);
            }
            
            $decoded = $this->verifyJwtToken($token);
            
            if (!$decoded) {
                return $this->errorResponse($response, 'Token inválido', 401);
            }

            $userData = (array) $decoded;
            
            // Generar nuevo token
            $payload = [
                'iss' => 'http://localhost',
                'aud' => 'http://localhost',
                'iat' => time(),
                'exp' => time() + (60 * 60 * 2), // 2 horas
                'data' => $userData['data']
            ];

            $newToken = JWT::encode($payload, $this->jwtSecret, 'HS256');
            
            return $this->successResponse($response, 'Token refrescado', [
                'token' => $newToken
            ]);
            
        } catch (Exception $e) {
            error_log("Error en refreshToken: " . $e->getMessage());
            return $this->errorResponse($response, 'Error al refrescar token', 500);
        }
    }

    /**
     * Cierra la sesión del usuario
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function logout(Request $request, Response $response, array $args): Response
    {
        return $this->successResponse($response, 'Sesión cerrada correctamente', [
            'message' => 'Token eliminado del lado del cliente'
        ]);
    }

    /**
     * Obtiene el texto de autorización de tratamiento de datos
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getAutorizacion(Request $request, Response $response, array $args): Response
    {
        try {
            $apiUrl = 'https://axis.uninunez.edu.co/apiLDAP/api/authdb/get';
            $data = json_encode(['dbcod' => '21']);

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Content-Type: application/json'
            ]);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                return $this->errorResponse($response, 'Error al conectar con el servicio de autorización: ' . $error, 500);
            }
            
            curl_close($ch);

            if ($httpCode !== 200) {
                return $this->errorResponse($response, 'Error al obtener autorización', $httpCode);
            }

            return $this->successResponse($response, 'Autorización obtenida correctamente', [
                'contenido' => $result
            ]);
            
        } catch (Exception $e) {
            error_log("Error en getAutorizacion: " . $e->getMessage());
            return $this->errorResponse($response, 'Error al obtener autorización: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Registra la aceptación de tratamiento de datos
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function setAutorizacion(Request $request, Response $response, array $args): Response
    {
        try {
            // Obtener datos del body JSON
            $data = $this->getJsonInput($request);
            
            if (!$data) {
                return $this->errorResponse($response, 'Datos JSON inválidos', 400);
            }
            
            $dni = $data['dni'] ?? $data['userdni'] ?? null;
            
            if (!$dni) {
                return $this->errorResponse($response, 'El DNI es requerido', 400);
            }

            // Obtener IP del cliente
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }

            // Log para debugging
            error_log("Registrando autorización - DNI: $dni, IP: $ip");

            $apiUrl = 'https://axis.uninunez.edu.co/apiLDAP/api/authdb/set';
            $payload = json_encode([
                'dbcod' => '21',
                'app' => 'EGRESADOS-UPDATE',
                'userdni' => $dni,
                'ip' => $ip
            ]);

            error_log("Payload enviado al API: $payload");

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para desarrollo
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Para desarrollo

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            error_log("Respuesta del API - HTTP Code: $httpCode, Response: $result");
            
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                error_log("Error CURL: $error");
                return $this->errorResponse($response, 'Error al conectar con el servicio de autorización: ' . $error, 500);
            }
            
            curl_close($ch);

            if ($httpCode !== 200) {
                return $this->errorResponse($response, 'Error al registrar autorización. Código HTTP: ' . $httpCode, $httpCode);
            }

            $resultData = json_decode($result, true);

            return $this->successResponse($response, 'Autorización registrada correctamente', [
                'resultado' => $resultData,
                'dni' => $dni,
                'ip' => $ip
            ]);
            
        } catch (Exception $e) {
            error_log("Error en setAutorizacion: " . $e->getMessage());
            return $this->errorResponse($response, 'Error al registrar autorización: ' . $e->getMessage(), 500);
        }
    }

    private function personasTieneColumna(PDO $db, string $columna): bool
    {
        try {
            $dbName = $db->query('SELECT DATABASE()')->fetchColumn();
            if (!$dbName) {
                return false;
            }
            $st = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = \'personas\' AND COLUMN_NAME = ?'
            );
            $st->execute([(string) $dbName, $columna]);

            return (int) $st->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log('personasTieneColumna: ' . $e->getMessage());

            return false;
        }
    }
}
