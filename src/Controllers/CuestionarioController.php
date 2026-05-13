<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use PDO;
use Exception;

/**
 * Controlador de cuestionario
 */
class CuestionarioController extends BaseController
{
    public function __construct(ContainerInterface $c)
    {
        parent::__construct($c);
    }

    /**
     * Obtiene las respuestas previas del usuario autenticado
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getMisRespuestas(Request $request, Response $response, array $args): Response
    {
        try {
            // Verificar autenticación
            $jwt = $request->getHeaderLine('Authorization');
            if (empty($jwt)) {
                return $this->errorResponse($response, 'Token de autorización requerido', 401);
            }
            
            $jwt = str_replace('Bearer ', '', $jwt);
            
            // Verificar y decodificar el token JWT
            $decoded = $this->verifyJwtToken($jwt);
            
            if (!$decoded) {
                return $this->errorResponse($response, 'Token inválido o expirado', 401);
            }

            $userData = (array) $decoded;
            
            // Obtener respuestas del usuario desde tecni_respuestas_usuario
            $db = $this->getDatabase();
            // Nota: Necesitamos el id_encuesta_realizada para filtrar por usuario
            // Por ahora retornamos array vacío ya que no tenemos forma de relacionar usuario con encuesta
            $resultado = [];
            
            // TODO: Implementar lógica de relación usuario-encuesta cuando esté disponible
            // $stmt = $db->prepare("SELECT tru.id_pregunta, tru.valor_respuesta 
            //                      FROM tecni_respuestas_usuario tru 
            //                      WHERE tru.id_encuesta_realizada = ?");
            // $stmt->execute([$id_encuesta]);
            // $respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // foreach ($respuestas as $r) {
            //     $resultado[$r['id_pregunta']] = $r['valor_respuesta'];
            // }

            return $this->successResponse($response, 'Respuestas obtenidas correctamente', $resultado);
            
        } catch (Exception $e) {
            error_log("Error en getMisRespuestas: " . $e->getMessage());
            return $this->errorResponse($response, 'Error al obtener respuestas', 500);
        }
    }

    /**
     * Guarda múltiples respuestas del cuestionario
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function responder(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->getJsonInput($request);

            if (!$data) {
                return $this->errorResponse($response, 'Datos JSON inválidos', 400);
            }

            // Verificar autenticación
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
            $iden_pers = $userData['data']->iden_pers;
            $codi_prog = $userData['data']->codi_prog;

            // Extraer respuestas
            $respuestas = $data['respuestas'] ?? null;

            if (!$respuestas || !is_array($respuestas) || empty($respuestas)) {
                return $this->errorResponse($response, 'Se requiere un array de respuestas', 400);
            }

            $db = $this->getDatabase();
            
            // Crear o actualizar registro de encuesta realizada
            $stmtEncuesta = $db->prepare("
                INSERT INTO tecni_encuesta_realizada (id_persona, fecha) 
                VALUES (?, NOW())
                ON DUPLICATE KEY UPDATE fecha = NOW()
            ");
            $stmtEncuesta->execute([$iden_pers]);
            
            // Obtener el ID de la encuesta (último insert o existente)
            $id_encuesta = $db->lastInsertId();
            if (!$id_encuesta) {
                // Si no hay lastInsertId, buscar el id existente
                $stmtBuscar = $db->prepare("SELECT id FROM tecni_encuesta_realizada WHERE id_persona = ? ORDER BY id DESC LIMIT 1");
                $stmtBuscar->execute([$iden_pers]);
                $id_encuesta = $stmtBuscar->fetchColumn();
            }

            // Guardar todas las respuestas
            $stmtDelete = $db->prepare("DELETE FROM tecni_respuestas_usuario WHERE id_encuesta_realizada = ?");
            $stmtDelete->execute([$id_encuesta]);
            
            $stmtInsert = $db->prepare("
                INSERT INTO tecni_respuestas_usuario 
                (id_encuesta_realizada, id_pregunta, valor_respuesta) 
                VALUES (?, ?, ?)
            ");

            $guardadas = 0;
            foreach ($respuestas as $resp) {
                $id_pregunta = $resp['id_pregunta'] ?? null;
                $valor = $resp['respuesta'] ?? null;

                if ($id_pregunta && $valor !== null && $valor !== '') {
                    // Si es array (multiple), convertir a JSON
                    if (is_array($valor)) {
                        $valor = json_encode($valor);
                    }
                    
                    $stmtInsert->execute([$id_encuesta, $id_pregunta, $valor]);
                    $guardadas++;
                }
            }

            return $this->successResponse($response, "Se guardaron $guardadas respuestas correctamente", [
                'respuestas_guardadas' => $guardadas
            ]);
            
        } catch (Exception $e) {
            error_log("Error en responder: " . $e->getMessage());
            return $this->errorResponse($response, 'Error al guardar respuestas: ' . $e->getMessage(), 500);
        }
    }
}
