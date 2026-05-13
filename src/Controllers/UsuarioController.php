<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PDO;
use Exception;

/**
 * Controlador de usuario
 */
class UsuarioController extends BaseController
{
    public function __construct(ContainerInterface $c)
    {
        parent::__construct($c);
    }

    /**
     * Obtiene el perfil del usuario autenticado
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getPerfil(Request $request, Response $response, array $args): Response
    {
        try {
            // Verificar autenticación
            $jwt = $request->getHeaderLine('Authorization');
            if (empty($jwt)) {
                return $this->errorResponse($response, 'Token de autorización requerido', 401);
            }
            
            $jwt = str_replace('Bearer ', '', $jwt);
            
            // Verificar y decodificar el token JWT
            $key = new Key($this->jwtSecret, 'HS256');
            $decoded = JWT::decode($jwt, $key);
            $userData = $decoded->data;
            
            // Obtener datos del usuario con JOIN a la tabla personas
            $db = $this->getDatabase();
            $stmt = $db->prepare("
                SELECT 
                    e.iden_pers, 
                    e.codi_prog,
                    p.codi_iden,
                    p.nomb_pers,
                    p.ape1_pers,
                    p.ape2_pers,
                    p.sexo_pers,
                    p.fnac_pers,
                    p.fech_expe,
                    p.lnac_pais,
                    p.lnac_regi,
                    p.lnac_ciud,
                    p.lexp_pais,
                    p.lexp_regi,
                    p.lexp_ciud,
                    p.esta_pers,
                    prog.EvalDNomb_Prog as nomb_prog
                FROM egresados e
                INNER JOIN personas p ON e.iden_pers = p.iden_pers
                LEFT JOIN programa prog ON e.codi_prog = prog.EvalDCod_Prog
                WHERE e.iden_pers = ? AND e.codi_prog = ?
            ");
            $stmt->execute([$userData->iden_pers, $userData->codi_prog]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario) {
                return $this->errorResponse($response, 'Usuario no encontrado', 404);
            }

            return $this->successResponse($response, 'Perfil obtenido correctamente', [
                'usuario' => $usuario
            ]);
            
        } catch (Exception $e) {
            error_log("Error en getPerfil: " . $e->getMessage());
            return $this->errorResponse($response, 'Error al obtener perfil: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza el perfil del usuario
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function updatePerfil(Request $request, Response $response, array $args): Response
    {
        try {
            // Verificar autenticación
            $jwt = $request->getHeaderLine('Authorization');
            if (empty($jwt)) {
                return $this->errorResponse($response, 'Token de autorización requerido', 401);
            }
            
            $jwt = str_replace('Bearer ', '', $jwt);
            
            // Verificar y decodificar el token JWT
            $key = new Key($this->jwtSecret, 'HS256');
            $decoded = JWT::decode($jwt, $key);
            $userData = $decoded->data;
            
            $data = $this->getJsonInput($request);
            
            if (!$data) {
                return $this->errorResponse($response, 'Datos JSON inválidos', 400);
            }

            $db = $this->getDatabase();
            
            // Los datos de contacto se actualizan en tecni_datos_contacto, no en personas
            // Retornar mensaje indicando que esta funcionalidad usa otro endpoint
            return $this->successResponse($response, 'Use el endpoint /api/usuario/contacto para actualizar datos de contacto', []);
            
        } catch (Exception $e) {
            error_log("Error en updatePerfil: " . $e->getMessage());
            return $this->errorResponse($response, 'Error al actualizar perfil', 500);
        }
    }

    /**
     * Obtiene los datos de contacto del usuario
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getContacto(Request $request, Response $response, array $args): Response
    {
        try {
            $jwt = $request->getHeaderLine('Authorization');
            if (empty($jwt)) {
                return $this->errorResponse($response, 'Token de autorización requerido', 401);
            }
            
            $jwt = str_replace('Bearer ', '', $jwt);
            $decoded = $this->verifyJwtToken($jwt);
            
            if (!$decoded) {
                return $this->errorResponse($response, 'Token inválido o expirado', 401);
            }

            $userData = (array) $decoded;
            
            $db = $this->getDatabase();
            $stmt = $db->prepare("SELECT * FROM tecni_datos_contacto WHERE iden_pers = ?");
            $stmt->execute([$userData['data']->iden_pers]);
            $contacto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contacto) {
                // Si no existe, retornar datos vacíos
                return $this->successResponse($response, 'No hay datos de contacto registrados', []);
            }

            return $this->successResponse($response, 'Datos de contacto obtenidos correctamente', $contacto);
            
        } catch (Exception $e) {
            error_log("Error en getContacto: " . $e->getMessage());
            return $this->errorResponse($response, 'Error al obtener datos de contacto', 500);
        }
    }

    /**
     * Actualiza o crea los datos de contacto del usuario
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function updateContacto(Request $request, Response $response, array $args): Response
    {
        try {
            $jwt = $request->getHeaderLine('Authorization');
            if (empty($jwt)) {
                return $this->errorResponse($response, 'Token de autorización requerido', 401);
            }
            
            $jwt = str_replace('Bearer ', '', $jwt);
            $decoded = $this->verifyJwtToken($jwt);
            
            if (!$decoded) {
                return $this->errorResponse($response, 'Token inválido o expirado', 401);
            }

            $userData = (array) $decoded;
            $data = $this->getJsonInput($request);
            
            if (!$data) {
                return $this->errorResponse($response, 'Datos JSON inválidos', 400);
            }

            $db = $this->getDatabase();
            
            // Verificar si ya existe registro
            $stmt = $db->prepare("SELECT iden_pers FROM tecni_datos_contacto WHERE iden_pers = ?");
            $stmt->execute([$userData['data']->iden_pers]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existe) {
                // Actualizar
                $updateFields = [];
                $params = [];
                
                if (isset($data['celular'])) {
                    $updateFields[] = "celular = ?";
                    $params[] = $data['celular'];
                }
                if (isset($data['telefono_alternativo'])) {
                    $updateFields[] = "telefono_alternativo = ?";
                    $params[] = $data['telefono_alternativo'];
                }
                if (isset($data['email_institucional'])) {
                    $updateFields[] = "email_institucional = ?";
                    $params[] = $data['email_institucional'];
                }
                if (isset($data['email_alternativo'])) {
                    $updateFields[] = "email_alternativo = ?";
                    $params[] = $data['email_alternativo'];
                }
                if (isset($data['direccion_residencia'])) {
                    $updateFields[] = "direccion_residencia = ?";
                    $params[] = $data['direccion_residencia'];
                }
                
                if (empty($updateFields)) {
                    return $this->errorResponse($response, 'No hay campos para actualizar', 400);
                }
                
                $params[] = $userData['data']->iden_pers;
                $sql = "UPDATE tecni_datos_contacto SET " . implode(", ", $updateFields) . " WHERE iden_pers = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            } else {
                // Insertar
                $stmt = $db->prepare("
                    INSERT INTO tecni_datos_contacto 
                    (iden_pers, celular, telefono_alternativo, email_institucional, email_alternativo, direccion_residencia) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userData['data']->iden_pers,
                    $data['celular'] ?? null,
                    $data['telefono_alternativo'] ?? null,
                    $data['email_institucional'] ?? null,
                    $data['email_alternativo'] ?? null,
                    $data['direccion_residencia'] ?? null
                ]);
            }

            return $this->successResponse($response, 'Datos de contacto actualizados correctamente', []);
            
        } catch (Exception $e) {
            error_log("Error en updateContacto: " . $e->getMessage());
            return $this->errorResponse($response, 'Error al actualizar datos de contacto', 500);
        }
    }
}
