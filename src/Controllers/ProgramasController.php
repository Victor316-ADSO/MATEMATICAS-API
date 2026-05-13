<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use PDO;
use Exception;
use Throwable;

/**
 * Controlador de programas académicos
 */
class ProgramasController extends BaseController
{
    public function __construct(ContainerInterface $c)
    {
        parent::__construct($c);
    }

    /**
     * Obtiene la lista de programas académicos (solo técnicos laborales)
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getProgramas(Request $request, Response $response, array $args): Response
    {
        try {
            $db = $this->getDatabase();
            
            // Lista de posibles consultas para diferentes esquemas
            $queries = [
                "SELECT EvalDCod_Prog AS codigo, EvalDNomb_Prog AS nombre FROM programa",
                "SELECT codi_prog AS codigo, nomb_prog AS nombre FROM programa",
                "SELECT codigo, nombre FROM programa",
                "SELECT * FROM programa"
            ];

            $programas = [];
            $lastError = null;
            
            foreach ($queries as $index => $sql) {
                try {
                    $stmt = $db->query($sql);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($result)) {
                        $programas = $result;
                        break;
                    }
                } catch (Throwable $e) {
                    $lastError = "Query " . ($index + 1) . ": " . $e->getMessage();
                    continue;
                }
            }

            if (empty($programas)) {
                return $this->successResponse($response, 'No se encontraron programas técnicos laborales en la base de datos', [
                    'programas' => [],
                    'debug' => $lastError
                ]);
            }

            return $this->successResponse($response, 'Programas técnicos laborales obtenidos correctamente', [
                'programas' => $programas
            ]);
            
        } catch (Throwable $e) {
            error_log("Error en getProgramas: " . $e->getMessage());
            return $this->errorResponse($response, 'Error de conexión a la base de datos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene un programa por su ID
     * 
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function getProgramaById(Request $request, Response $response, array $args): Response
    {
        try {
            $programaId = $args['programa_id'] ?? null;
            
            if (!$programaId) {
                return $this->errorResponse($response, 'ID de programa no proporcionado', 400);
            }

            $db = $this->getDatabase();
            
            // Intentar diferentes esquemas
            $queries = [
                "SELECT EvalDCod_Prog AS codigo, EvalDNomb_Prog AS nombre FROM programa WHERE EvalDCod_Prog = ?",
                "SELECT codi_prog AS codigo, nomb_prog AS nombre FROM programa WHERE codi_prog = ?",
                "SELECT * FROM programa WHERE codigo = ? OR codi_prog = ? OR EvalDCod_Prog = ?"
            ];

            $programa = null;
            
            foreach ($queries as $sql) {
                try {
                    $stmt = $db->prepare($sql);
                    // Algunos queries requieren un parámetro, otros 3
                    if (substr_count($sql, '?') === 1) {
                        $stmt->execute([$programaId]);
                    } else {
                        $stmt->execute([$programaId, $programaId, $programaId]);
                    }
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result) {
                        $programa = $result;
                        break;
                    }
                } catch (Throwable $e) {
                    continue;
                }
            }

            if (!$programa) {
                return $this->errorResponse($response, 'Programa no encontrado', 404);
            }

            return $this->successResponse($response, 'Programa encontrado', [
                'programa' => $programa
            ]);
            
        } catch (Throwable $e) {
            error_log("Error en getProgramaById: " . $e->getMessage());
            return $this->errorResponse($response, 'Error al obtener programa', 500);
        }
    }
}
