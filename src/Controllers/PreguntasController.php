<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use PDO;
use Exception;

/**
 * Controlador de preguntas
 */
class PreguntasController extends BaseController
{
    public function __construct(ContainerInterface $c)
    {
        parent::__construct($c);
    }

    /**
     * Obtiene todas las preguntas
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getPreguntas(Request $request, Response $response, array $args): Response
    {
        try {
            $db = $this->getDatabase();
            
            // Obtener preguntas (incluir hijas aunque tengan estado=0)
            $stmt = $db->query("
                SELECT 
                    id as id_pregunta,
                    numero as orden,
                    id_pregunta_padre,
                    valor_activacion,
                    pregunta,
                    tipo,
                    estado
                FROM tecni_preguntas 
                WHERE estado = 1 OR id_pregunta_padre IS NOT NULL
                ORDER BY numero ASC
            ");
            $preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener todas las opciones de respuesta (incluir opciones de preguntas hijas)
            $stmtOpciones = $db->query("
                SELECT 
                    r.id_respuesta,
                    r.id_pregunta,
                    r.respuesta as opcion,
                    r.respuesta as valor
                FROM tecni_respuestas r
                WHERE r.estado = 1 
                   OR r.id_pregunta IN (SELECT id FROM tecni_preguntas WHERE id_pregunta_padre IS NOT NULL)
            ");
            $todasOpciones = $stmtOpciones->fetchAll(PDO::FETCH_ASSOC);
            
            // Agrupar opciones por id_pregunta y convertir tipos
            $opcionesPorPregunta = [];
            foreach ($todasOpciones as $opcion) {
                $idPregunta = (int)$opcion['id_pregunta'];
                if (!isset($opcionesPorPregunta[$idPregunta])) {
                    $opcionesPorPregunta[$idPregunta] = [];
                }
                // Asegurar que valor sea string limpio
                $opcion['id_respuesta'] = (int)$opcion['id_respuesta'];
                $opcion['id_pregunta'] = $idPregunta;
                $opcion['valor'] = trim($opcion['valor']);
                $opcion['opcion'] = trim($opcion['opcion']);
                $opcionesPorPregunta[$idPregunta][] = $opcion;
            }
            
            // Convertir tipos de respuesta al formato esperado por el front-end
            $preguntasMapeadas = array_map(function($p) use ($opcionesPorPregunta) {
                // Mapear tipos de respuesta de la BD al formato del front-end
                $tipoMapeado = match($p['tipo']) {
                    'cerrada' => 'opcion_unica',  // pregunta cerrada = opción única (radio buttons)
                    'abierta' => 'abierta',       // texto libre
                    'escala' => 'escala',         // dropdown con opciones
                    'multiple' => 'opcion_multiple', // checkboxes
                    default => $p['tipo']
                };
                
                $p['tipo_pregunta'] = $tipoMapeado;
                $p['obligatoria'] = true; // Todas obligatorias por defecto
                
                // Convertir id_pregunta_padre a entero si existe, o null si no
                $p['id_pregunta_padre'] = $p['id_pregunta_padre'] ? (int)$p['id_pregunta_padre'] : null;
                
                // Asegurar que valor_activacion sea string o null
                $p['valor_activacion'] = $p['valor_activacion'] ? trim($p['valor_activacion']) : null;
                
                // Convertir id_pregunta a entero
                $p['id_pregunta'] = (int)$p['id_pregunta'];
                
                // Asignar opciones si existen para esta pregunta
                $p['opciones'] = $opcionesPorPregunta[$p['id_pregunta']] ?? [];
                
                // Eliminar el campo 'tipo' antiguo
                unset($p['tipo']);
                
                return $p;
            }, $preguntas);

            return $this->successResponse($response, 'Preguntas obtenidas correctamente', [
                'preguntas' => $preguntasMapeadas
            ]);
            
        } catch (Exception $e) {
            error_log("Error en getPreguntas: " . $e->getMessage());
            return $this->errorResponse($response, 'Error al obtener preguntas', 500);
        }
    }
}
