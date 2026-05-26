<?php
namespace App\Controllers;

use App\Data\QuizAdopcionSeedData;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Exception;

class QuizAdopcionController extends BaseController
{
    public function __construct(ContainerInterface $c)
    {
        parent::__construct($c);
    }

    /**
     * GET /api/quiz-adopcion — Preguntas y opciones (sin respuesta correcta).
     */
    public function getPreguntas(Request $request, Response $response, array $args): Response
    {
        try {
            $auth = $this->requireAuth($request, $response);
            if ($auth instanceof Response) {
                return $auth;
            }

            $this->ensureSchema();
            $this->seedIfEmpty();

            $db = $this->getDatabase();
            $stmt = $db->query('
                SELECT id, orden, pregunta
                FROM quiz_adopcion_preguntas
                ORDER BY orden ASC
            ');
            $preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtOp = $db->query('
                SELECT id, id_pregunta, texto
                FROM quiz_adopcion_opciones
                ORDER BY id ASC
            ');
            $opcionesRaw = $stmtOp->fetchAll(PDO::FETCH_ASSOC);
            $opcionesPorPregunta = [];
            foreach ($opcionesRaw as $op) {
                $pid = (int) $op['id_pregunta'];
                if (!isset($opcionesPorPregunta[$pid])) {
                    $opcionesPorPregunta[$pid] = [];
                }
                $opcionesPorPregunta[$pid][] = [
                    'id' => (int) $op['id'],
                    'texto' => $op['texto'],
                ];
            }

            $resultado = array_map(function ($p) use ($opcionesPorPregunta) {
                $id = (int) $p['id'];
                return [
                    'id' => $id,
                    'orden' => (int) $p['orden'],
                    'pregunta' => $p['pregunta'],
                    'opciones' => $opcionesPorPregunta[$id] ?? [],
                ];
            }, $preguntas);

            return $this->successResponse($response, 'Preguntas del quiz obtenidas', [
                'preguntas' => $resultado,
                'total' => count($resultado),
            ]);
        } catch (Exception $e) {
            error_log('QuizAdopcion getPreguntas: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error al obtener preguntas del quiz', 500);
        }
    }

    /**
     * GET /api/quiz-adopcion/estado — Cooldown de 5 días entre intentos.
     */
    public function getEstado(Request $request, Response $response, array $args): Response
    {
        try {
            $auth = $this->requireAuth($request, $response);
            if ($auth instanceof Response) {
                return $auth;
            }
            $idenPers = $auth['iden_pers'];

            $this->ensureSchema();

            $db = $this->getDatabase();
            $cooldown = QuizAdopcionSeedData::COOLDOWN_DAYS;

            $stmt = $db->prepare('
                SELECT id, fecha, aciertos, total
                FROM quiz_adopcion_intentos
                WHERE id_persona = ?
                ORDER BY fecha DESC
                LIMIT 1
            ');
            $stmt->execute([$idenPers]);
            $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ultimo) {
                return $this->successResponse($response, 'Sin intentos previos', [
                    'puede_iniciar' => true,
                    'ultimo_intento' => null,
                    'dias_restantes' => 0,
                    'cooldown_dias' => $cooldown,
                ]);
            }

            $stmtDiff = $db->prepare('
                SELECT DATEDIFF(NOW(), ?) AS dias_transcurridos
            ');
            $stmtDiff->execute([$ultimo['fecha']]);
            $diasTranscurridos = (int) $stmtDiff->fetchColumn();
            $diasRestantes = max(0, $cooldown - $diasTranscurridos);
            $puedeIniciar = $diasTranscurridos >= $cooldown;

            return $this->successResponse($response, 'Estado del quiz', [
                'puede_iniciar' => $puedeIniciar,
                'ultimo_intento' => [
                    'fecha' => $ultimo['fecha'],
                    'aciertos' => (int) $ultimo['aciertos'],
                    'total' => (int) $ultimo['total'],
                ],
                'dias_restantes' => $diasRestantes,
                'cooldown_dias' => $cooldown,
            ]);
        } catch (Exception $e) {
            error_log('QuizAdopcion getEstado: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error al consultar estado del quiz', 500);
        }
    }

    /**
     * GET /api/quiz-adopcion/ultimo-resultado — Respuestas del último intento del usuario autenticado.
     */
    public function getUltimoResultado(Request $request, Response $response, array $args): Response
    {
        try {
            $auth = $this->requireAuth($request, $response);
            if ($auth instanceof Response) {
                return $auth;
            }
            $idenPers = $auth['iden_pers'];

            $this->ensureSchema();
            $db = $this->getDatabase();

            $stmt = $db->prepare('
                SELECT id, fecha, aciertos, total
                FROM quiz_adopcion_intentos
                WHERE id_persona = ?
                ORDER BY fecha DESC
                LIMIT 1
            ');
            $stmt->execute([$idenPers]);
            $intento = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$intento) {
                return $this->successResponse($response, 'Sin intentos previos', [
                    'intento' => null,
                    'aciertos' => 0,
                    'total' => 0,
                    'porcentaje' => 0,
                    'detalle' => [],
                ]);
            }

            $idIntento = (int) $intento['id'];
            $stmtDet = $db->prepare('
                SELECT r.id_pregunta,
                       p.pregunta,
                       p.retroalimentacion,
                       r.texto_respuesta AS respuesta_usuario,
                       r.es_correcta,
                       (
                           SELECT o.texto
                           FROM quiz_adopcion_opciones o
                           WHERE o.id_pregunta = r.id_pregunta AND o.es_correcta = 1
                           LIMIT 1
                       ) AS respuesta_correcta
                FROM quiz_adopcion_respuestas r
                INNER JOIN quiz_adopcion_preguntas p ON p.id = r.id_pregunta
                WHERE r.id_intento = ?
                ORDER BY p.orden ASC
            ');
            $stmtDet->execute([$idIntento]);
            $rows = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            $detalle = [];
            foreach ($rows as $row) {
                $detalle[] = [
                    'id_pregunta' => (int) $row['id_pregunta'],
                    'pregunta' => $row['pregunta'],
                    'respuesta_usuario' => $row['respuesta_usuario'],
                    'respuesta_correcta' => $row['respuesta_correcta'] ?? '',
                    'es_correcta' => (bool) $row['es_correcta'],
                    'retroalimentacion' => $row['retroalimentacion'],
                ];
            }

            $total = (int) $intento['total'];
            $aciertos = (int) $intento['aciertos'];

            return $this->successResponse($response, 'Último resultado del usuario', [
                'intento' => [
                    'id' => $idIntento,
                    'fecha' => $intento['fecha'],
                    'id_persona' => $idenPers,
                ],
                'aciertos' => $aciertos,
                'total' => $total,
                'porcentaje' => $total > 0 ? round(($aciertos / $total) * 100) : 0,
                'detalle' => $detalle,
            ]);
        } catch (Exception $e) {
            error_log('QuizAdopcion getUltimoResultado: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error al obtener el último resultado', 500);
        }
    }

    /**
     * POST /api/quiz-adopcion/enviar
     * Body: { "respuestas": [ { "id_pregunta": 1, "texto": "..." }, ... ] }
     */
    public function enviar(Request $request, Response $response, array $args): Response
    {
        try {
            $auth = $this->requireAuth($request, $response);
            if ($auth instanceof Response) {
                return $auth;
            }
            $idenPers = $auth['iden_pers'];

            $data = $this->getJsonInput($request);
            if (!$data || !isset($data['respuestas']) || !is_array($data['respuestas'])) {
                return $this->errorResponse($response, 'Se requiere el array respuestas', 400);
            }

            $this->ensureSchema();
            $this->seedIfEmpty();

            $db = $this->getDatabase();
            $cooldown = QuizAdopcionSeedData::COOLDOWN_DAYS;

            $stmtUlt = $db->prepare('
                SELECT fecha FROM quiz_adopcion_intentos
                WHERE id_persona = ?
                ORDER BY fecha DESC LIMIT 1
            ');
            $stmtUlt->execute([$idenPers]);
            $fechaUlt = $stmtUlt->fetchColumn();
            if ($fechaUlt) {
                $stmtDiff = $db->prepare('SELECT DATEDIFF(NOW(), ?) AS d');
                $stmtDiff->execute([$fechaUlt]);
                $dias = (int) $stmtDiff->fetchColumn();
                if ($dias < $cooldown) {
                    $restantes = $cooldown - $dias;
                    return $this->errorResponse(
                        $response,
                        "Debes esperar {$restantes} día(s) más para repetir el quiz.",
                        409
                    );
                }
            }

            $stmtPreg = $db->query('
                SELECT p.id, p.pregunta, p.retroalimentacion,
                       o.id AS id_opcion, o.texto, o.es_correcta
                FROM quiz_adopcion_preguntas p
                INNER JOIN quiz_adopcion_opciones o ON o.id_pregunta = p.id
                ORDER BY p.orden ASC, o.id ASC
            ');
            $rows = $stmtPreg->fetchAll(PDO::FETCH_ASSOC);

            $preguntasDb = [];
            foreach ($rows as $row) {
                $pid = (int) $row['id'];
                if (!isset($preguntasDb[$pid])) {
                    $preguntasDb[$pid] = [
                        'id' => $pid,
                        'pregunta' => $row['pregunta'],
                        'retroalimentacion' => $row['retroalimentacion'],
                        'correcta' => null,
                    ];
                }
                if ((int) $row['es_correcta'] === 1) {
                    $preguntasDb[$pid]['correcta'] = $row['texto'];
                }
            }

            $totalEsperado = count($preguntasDb);
            if ($totalEsperado === 0) {
                return $this->errorResponse($response, 'No hay preguntas configuradas en el servidor', 500);
            }

            $respuestasMap = [];
            foreach ($data['respuestas'] as $r) {
                $idP = isset($r['id_pregunta']) ? (int) $r['id_pregunta'] : 0;
                $texto = isset($r['texto']) ? trim((string) $r['texto']) : '';
                if ($idP > 0 && $texto !== '') {
                    $respuestasMap[$idP] = $texto;
                }
            }

            if (count($respuestasMap) !== $totalEsperado) {
                return $this->errorResponse(
                    $response,
                    "Debes responder las {$totalEsperado} preguntas. Recibidas: " . count($respuestasMap),
                    400
                );
            }

            foreach (array_keys($preguntasDb) as $idReq) {
                if (!isset($respuestasMap[$idReq])) {
                    return $this->errorResponse($response, 'Faltan respuestas para completar el quiz', 400);
                }
            }

            $aciertos = 0;
            $detalle = [];
            foreach ($preguntasDb as $pid => $info) {
                $elegida = $respuestasMap[$pid];
                $esCorrecta = $elegida === $info['correcta'];
                if ($esCorrecta) {
                    $aciertos++;
                }
                $detalle[] = [
                    'id_pregunta' => $pid,
                    'pregunta' => $info['pregunta'],
                    'respuesta_usuario' => $elegida,
                    'respuesta_correcta' => $info['correcta'],
                    'es_correcta' => $esCorrecta,
                    'retroalimentacion' => $info['retroalimentacion'],
                ];
            }

            $db->beginTransaction();

            $stmtIns = $db->prepare('
                INSERT INTO quiz_adopcion_intentos (id_persona, fecha, aciertos, total)
                VALUES (?, NOW(), ?, ?)
            ');
            $stmtIns->execute([$idenPers, $aciertos, $totalEsperado]);
            $idIntento = (int) $db->lastInsertId();

            $stmtResp = $db->prepare('
                INSERT INTO quiz_adopcion_respuestas (id_intento, id_pregunta, texto_respuesta, es_correcta)
                VALUES (?, ?, ?, ?)
            ');
            foreach ($detalle as $d) {
                $stmtResp->execute([
                    $idIntento,
                    $d['id_pregunta'],
                    $d['respuesta_usuario'],
                    $d['es_correcta'] ? 1 : 0,
                ]);
            }

            if ($this->tableExists($db, 'tecni_encuesta_realizada')) {
                $stmtEnc = $db->prepare('
                    INSERT INTO tecni_encuesta_realizada (id_persona, fecha)
                    VALUES (?, NOW())
                ');
                $stmtEnc->execute([$idenPers]);
            }

            $db->commit();

            return $this->successResponse($response, 'Quiz enviado correctamente', [
                'aciertos' => $aciertos,
                'total' => $totalEsperado,
                'porcentaje' => $totalEsperado > 0 ? round(($aciertos / $totalEsperado) * 100) : 0,
                'detalle' => $detalle,
                'proximo_intento_en_dias' => $cooldown,
            ]);
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('QuizAdopcion enviar: ' . $e->getMessage());
            return $this->errorResponse($response, 'Error al guardar el quiz: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @return array{iden_pers: string}|Response
     */
    private function requireAuth(Request $request, Response $response): array|Response
    {
        $token = $this->getBearerToken($request);
        if (!$token) {
            return $this->errorResponse($response, 'Token de autorización requerido', 401);
        }
        $decoded = $this->verifyJwtToken($token);
        if (!$decoded || !isset($decoded->data->iden_pers)) {
            return $this->errorResponse($response, 'Token inválido o expirado', 401);
        }
        return ['iden_pers' => (string) $decoded->data->iden_pers];
    }

    private function ensureSchema(): void
    {
        $db = $this->getDatabase();
        $sqlFile = __DIR__ . '/../../database/create_quiz_adopcion.sql';
        if (!is_readable($sqlFile)) {
            return;
        }
        $raw = file_get_contents($sqlFile);
        foreach (array_filter(array_map('trim', explode(';', $raw))) as $stmt) {
            if (stripos($stmt, 'CREATE TABLE') !== false) {
                try {
                    $db->exec($stmt);
                } catch (Exception $e) {
                    error_log('ensureSchema: ' . $e->getMessage());
                }
            }
        }
    }

    private function seedIfEmpty(): void
    {
        $db = $this->getDatabase();
        if (!$this->tableExists($db, 'quiz_adopcion_preguntas')) {
            return;
        }
        $count = (int) $db->query('SELECT COUNT(*) FROM quiz_adopcion_preguntas')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $insP = $db->prepare('INSERT INTO quiz_adopcion_preguntas (orden, pregunta, retroalimentacion) VALUES (?, ?, ?)');
        $insO = $db->prepare('INSERT INTO quiz_adopcion_opciones (id_pregunta, texto, es_correcta) VALUES (?, ?, ?)');

        foreach (QuizAdopcionSeedData::preguntas() as $p) {
            $insP->execute([$p['orden'], $p['pregunta'], $p['retroalimentacion']]);
            $idPregunta = (int) $db->lastInsertId();
            foreach ($p['opciones'] as $o) {
                $insO->execute([$idPregunta, $o['texto'], $o['correcta'] ? 1 : 0]);
            }
        }
    }

    private function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ');
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
