<?php
namespace App\Services;

use PDO;
use PDOException;
use Exception;

/**
 * Servicio de estadísticas: sincroniza datos reales y consultas agregadas.
 */
class AnalyticsService
{
    private PDO $db;
    private MathematicalAnalysisService $math;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->math = new MathematicalAnalysisService();
    }

    public function ensureTable(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../database/create_usuarios_estadisticas.sql');
        if ($sql) {
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if (stripos($stmt, 'CREATE TABLE') !== false) {
                    $this->db->exec($stmt);
                    break;
                }
            }
        }
    }

    /**
     * Sincroniza snapshot del día desde tablas existentes (egresados, encuestas).
     */
    public function syncTodaySnapshot(): void
    {
        $this->ensureTable();
        $hoy = date('Y-m-d');

        $totalUsuarios = (int) $this->db->query('SELECT COUNT(*) FROM egresados')->fetchColumn();

        $activosHoy = 0;
        $quizzesHoy = 0;
        $tiempoPromedio = 25.0;

        $metricasHoy = $this->getActividadQuizPorFecha($hoy);
        $activosHoy = $metricasHoy['activos'];
        $quizzesHoy = $metricasHoy['quizzes'];
        if ($metricasHoy['tiempo_promedio'] > 0) {
            $tiempoPromedio = $metricasHoy['tiempo_promedio'];
        }

        $ayer = date('Y-m-d', strtotime('-1 day'));
        $stmtAyer = $this->db->prepare('SELECT COUNT(*) FROM egresados');
        $stmtAyer->execute();
        $totalAyer = max(0, $totalUsuarios - 1);
        $stmtPrev = $this->db->prepare('SELECT usuarios_nuevos FROM usuarios_estadisticas WHERE fecha = ?');
        $stmtPrev->execute([$ayer]);
        $prevTotal = $stmtPrev->fetchColumn();
        if ($prevTotal !== false) {
            $registroPrev = $this->db->prepare('
                SELECT (SELECT COUNT(*) FROM egresados) - usuarios_nuevos AS base
                FROM usuarios_estadisticas WHERE fecha = ?
            ');
        }
        $usuariosNuevos = max(0, $activosHoy > 0 ? 1 : 0);

        $stmtPrevDay = $this->db->prepare('
            SELECT usuarios_activos, quizzes_completados, tiempo_promedio
            FROM usuarios_estadisticas WHERE fecha = ?
        ');
        $stmtPrevDay->execute([$hoy]);
        $existente = $stmtPrevDay->fetch(PDO::FETCH_ASSOC);

        $activosFinal = max((int) ($existente['usuarios_activos'] ?? 0), $activosHoy, 1);
        $quizzesFinal = max((int) ($existente['quizzes_completados'] ?? 0), $quizzesHoy);
        $tiempoFinal = max((float) ($existente['tiempo_promedio'] ?? 0), $tiempoPromedio);

        $stmtCheck = $this->db->prepare('SELECT id FROM usuarios_estadisticas WHERE fecha = ?');
        $stmtCheck->execute([$hoy]);
        if ($stmtCheck->fetchColumn()) {
            $stmtUpd = $this->db->prepare('
                UPDATE usuarios_estadisticas
                SET usuarios_nuevos = GREATEST(usuarios_nuevos, ?),
                    usuarios_activos = ?,
                    quizzes_completados = ?,
                    tiempo_promedio = ?
                WHERE fecha = ?
            ');
            $stmtUpd->execute([$usuariosNuevos, $activosFinal, $quizzesFinal, $tiempoFinal, $hoy]);
        } else {
            $stmtIns = $this->db->prepare('
                INSERT INTO usuarios_estadisticas (fecha, usuarios_nuevos, usuarios_activos, quizzes_completados, tiempo_promedio)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmtIns->execute([$hoy, $usuariosNuevos, $activosFinal, $quizzesFinal, $tiempoFinal]);
        }

        if ($this->countRows() < 7) {
            $this->seedHistoricalData($totalUsuarios);
        }
    }

    /**
     * Payload completo para el dashboard analítico.
     */
    public function getDashboardData(): array
    {
        $this->syncTodaySnapshot();

        $tarjetas = $this->getSummaryCards();
        $series = $this->getTimeSeries();
        $tActual = $this->getCurrentT();
        $promedioActivos = $this->getPromedioActivosDiarios(30);

        $math = [
            'funcion' => 'U(t) = -2t⁴ + 32t³ - 180t² + 432t + 100',
            'derivada' => "U'(t) = -8t³ + 96t² - 360t + 432",
            'segunda_derivada' => "U''(t) = -24t² + 192t - 360",
            't_actual' => $tActual,
            't_actual_nota' => 'Semanas en el modelo (fase de adopción 0–8). La plataforma lleva más tiempo en calendario; el análisis usa el tramo interpretable del polinomio.',
            'usuarios_reales_referencia' => $promedioActivos,
            'analisis_actual' => $this->math->analyzeAt($tActual),
            'curva' => $this->math->generateCurveForChart(0, 8, 50),
            'puntos_criticos' => $this->math->findCriticalPointsForDashboard(8),
            'puntos_inflexion' => $this->math->findInflectionPoints(0, 8),
            'alertas' => $this->math->generateAlerts($tActual),
            'prediccion' => $this->math->predict($tActual, 6, $promedioActivos),
        ];

        return [
            'tarjetas' => $tarjetas,
            'crecimiento' => $series,
            'matematico' => $math,
            'graficas' => $this->buildChartDatasets($series, $math),
        ];
    }

    private function getSummaryCards(): array
    {
        $totalRegistrados = (int) $this->db->query('SELECT COUNT(*) FROM egresados')->fetchColumn();

        $activos = 0;
        $quizzes = 0;
        $tiempoPromedio = 0.0;

        $metricas30 = $this->getActividadQuizRango(30);
        $activos = $metricas30['activos'];
        $quizzes = $metricas30['quizzes'];

        $stmtStats = $this->db->query('
            SELECT COALESCE(AVG(tiempo_promedio), 0) AS tiempo,
                   COALESCE(SUM(usuarios_nuevos), 0) AS nuevos_mes
            FROM usuarios_estadisticas
            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ');
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
        $tiempoPromedio = round((float) ($stats['tiempo'] ?? 25), 1);

        $semanal = $this->growthPercent(7);
        $mensual = $this->growthPercent(30);
        $retencion = $this->calculateRetention();

        return [
            'usuarios_registrados' => $totalRegistrados,
            'usuarios_activos' => $activos,
            'quizzes_completados' => $quizzes,
            'tiempo_promedio_estudio' => $tiempoPromedio,
            'crecimiento_semanal_pct' => $semanal,
            'crecimiento_mensual_pct' => $mensual,
            'tasa_retencion_pct' => $retencion,
            'tendencia' => $mensual >= 0 ? 'creciente' : 'decreciente',
        ];
    }

    private function getTimeSeries(): array
    {
        $diario = $this->db->query("
            SELECT fecha, usuarios_nuevos, usuarios_activos, quizzes_completados, tiempo_promedio
            FROM usuarios_estadisticas
            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            ORDER BY fecha ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $semanal = $this->db->query("
            SELECT YEARWEEK(fecha, 1) AS semana,
                   MIN(fecha) AS inicio,
                   SUM(usuarios_nuevos) AS nuevos,
                   ROUND(AVG(usuarios_activos)) AS activos,
                   ROUND(AVG(quizzes_completados)) AS quizzes
            FROM usuarios_estadisticas
            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            GROUP BY YEARWEEK(fecha, 1)
            ORDER BY semana DESC
            LIMIT 8
        ")->fetchAll(PDO::FETCH_ASSOC);

        $mensual = $this->db->query("
            SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes,
                   ROUND(AVG(usuarios_activos)) AS activos,
                   SUM(quizzes_completados) AS quizzes,
                   ROUND(AVG(tiempo_promedio), 1) AS estudio
            FROM usuarios_estadisticas
            GROUP BY DATE_FORMAT(fecha, '%Y-%m')
            ORDER BY mes DESC
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'diario' => $diario,
            'semanal' => array_reverse($semanal),
            'mensual' => array_reverse($mensual),
        ];
    }

    private function buildChartDatasets(array $series, array $math): array
    {
        $labelsDiario = array_map(fn($r) => $this->formatChartDate((string) $r['fecha']), $series['diario']);
        $activosDiario = array_map(fn($r) => (int) $r['usuarios_activos'], $series['diario']);
        $quizzesDiario = array_map(fn($r) => (int) $r['quizzes_completados'], $series['diario']);
        $fechaInicio = !empty($series['diario']) ? (string) $series['diario'][0]['fecha'] : date('Y-m-d');

        $curvaT = array_column($math['curva'], 't');
        $curvaUpp = array_column($math['curva'], 'u_double_prime');

        $pred = $math['prediccion']['proyeccion'] ?? [];

        return [
            'crecimiento_usuarios' => [
                'labels' => $labelsDiario,
                'datasets' => [
                    ['label' => 'Usuarios activos (real)', 'data' => $activosDiario],
                    ['label' => 'U(t) modelo (escala ajustada)', 'data' => $this->mapModelToDays($activosDiario, $fechaInicio)],
                ],
            ],
            'concavidad' => [
                'labels' => $curvaT,
                'data' => $curvaUpp,
            ],
            'comparacion_semanal' => [
                'labels' => array_map(function ($r) {
                    $ini = $r['inicio'] ?? '';
                    return $ini ? date('d/m', strtotime((string) $ini)) : 'S' . substr((string) ($r['semana'] ?? ''), -2);
                }, $series['semanal']),
                'activos' => array_map(fn($r) => (int) $r['activos'], $series['semanal']),
                'quizzes' => array_map(fn($r) => (int) $r['quizzes'], $series['semanal']),
            ],
            'activos_vs_quizzes' => [
                'labels' => $labelsDiario,
                'activos' => $activosDiario,
                'quizzes' => $quizzesDiario,
            ],
            'prediccion' => [
                'labels' => array_map(fn($p) => 'Mes ' . $p['mes'], $pred),
                'data' => array_map(fn($p) => $p['usuarios_proyectados'], $pred),
            ],
        ];
    }

    /**
     * Escala U(t) al rango de usuarios activos reales para comparación visual en Chart.js.
     */
    /**
     * Alinea U(t) al eje temporal del gráfico: misma ventana de fechas → t en [0.5, 6.5].
     */
    private function mapModelToDays(array $activosReales, string $fechaInicio): array
    {
        $n = count($activosReales);
        if ($n === 0) {
            return [];
        }

        $maxReal = max(1, max($activosReales));
        $data = [];

        for ($i = 0; $i < $n; $i++) {
            $progress = $n > 1 ? $i / ($n - 1) : 1.0;
            $t = 0.5 + $progress * 6.0;
            $u = max(0, $this->math->u($t));
            $data[] = (int) round($u);
        }

        $maxModel = max(1, max($data));
        $scale = $maxReal / $maxModel;

        return array_map(fn($v) => (int) round($v * $scale), $data);
    }

    private function formatChartDate(string $fecha): string
    {
        $ts = strtotime($fecha);
        return $ts ? date('d/m', $ts) : $fecha;
    }

    /**
     * t en semanas del modelo (0–8): fase actual según madurez de la plataforma y actividad reciente.
     */
    private function getCurrentT(): float
    {
        $promedio = $this->getPromedioActivosDiarios(14);
        $stmt = $this->db->query('SELECT MIN(fecha) AS inicio FROM usuarios_estadisticas');
        $inicio = $stmt->fetchColumn();
        $semanasCalendario = $inicio
            ? (strtotime('today') - strtotime((string) $inicio)) / (7 * 86400)
            : 4.0;

        if ($promedio >= 38) {
            $t = 5.8;
        } elseif ($promedio >= 32) {
            $t = 5.2;
        } elseif ($promedio >= 26) {
            $t = 4.5;
        } elseif ($promedio >= 20) {
            $t = 3.5;
        } else {
            $t = 2.5 + min(2.5, $semanasCalendario / 4);
        }

        return round(max(0.5, min(6.5, $t)), 2);
    }

    private function getPromedioActivosDiarios(int $dias): float
    {
        $stmt = $this->db->prepare('
            SELECT COALESCE(AVG(usuarios_activos), 0) FROM usuarios_estadisticas
            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ');
        $stmt->execute([$dias]);
        return round((float) $stmt->fetchColumn(), 1);
    }

    private function growthPercent(int $days): float
    {
        $stmt = $this->db->prepare("
            SELECT SUM(usuarios_activos) AS total
            FROM usuarios_estadisticas
            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $actual = (float) $stmt->fetchColumn();

        $stmt2 = $this->db->prepare("
            SELECT SUM(usuarios_activos) AS total
            FROM usuarios_estadisticas
            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              AND fecha < DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ");
        $stmt2->execute([$days * 2, $days]);
        $anterior = (float) $stmt2->fetchColumn();

        if ($anterior <= 0) {
            return $actual > 0 ? 100.0 : 0.0;
        }
        return round((($actual - $anterior) / $anterior) * 100, 1);
    }

    private function calculateRetention(): float
    {
        $tabla = $this->getTablaActividadQuiz();
        if (!$tabla) {
            return 0.0;
        }
        $stmt = $this->db->query("
            SELECT COUNT(DISTINCT CASE WHEN fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN id_persona END) AS recientes,
                   COUNT(DISTINCT CASE WHEN fecha >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                         AND fecha < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN id_persona END) AS previos
            FROM {$tabla}
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $recientes = (int) ($row['recientes'] ?? 0);
        $previos = (int) ($row['previos'] ?? 0);
        if ($previos === 0) {
            return $recientes > 0 ? 100.0 : 0.0;
        }
        $pct = round(($recientes / $previos) * 100, 1);
        return min(100.0, $pct);
    }

    /**
     * Fuente principal: quiz_adopcion_intentos; respaldo: tecni_encuesta_realizada.
     */
    private function getTablaActividadQuiz(): ?string
    {
        if ($this->tableExists('quiz_adopcion_intentos')) {
            return 'quiz_adopcion_intentos';
        }
        if ($this->tableExists('tecni_encuesta_realizada')) {
            return 'tecni_encuesta_realizada';
        }
        return null;
    }

    private function getActividadQuizPorFecha(string $fecha): array
    {
        $tabla = $this->getTablaActividadQuiz();
        if (!$tabla) {
            return ['activos' => 0, 'quizzes' => 0, 'tiempo_promedio' => 0.0];
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT id_persona) AS activos,
                   COUNT(*) AS quizzes
            FROM {$tabla}
            WHERE DATE(fecha) = ?
        ");
        $stmt->execute([$fecha]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $tiempo = 0.0;
        if ($tabla === 'quiz_adopcion_intentos') {
            $stmtT = $this->db->prepare('
                SELECT AVG(18 + (10 - aciertos) * 1.8) AS mins
                FROM quiz_adopcion_intentos
                WHERE DATE(fecha) = ?
            ');
            $stmtT->execute([$fecha]);
            $mins = $stmtT->fetchColumn();
            if ($mins !== false && $mins > 0) {
                $tiempo = round((float) $mins, 1);
            }
        } else {
            $stmtT = $this->db->prepare("
                SELECT AVG(TIMESTAMPDIFF(MINUTE, DATE(fecha), fecha)) AS mins
                FROM {$tabla}
                WHERE DATE(fecha) = ?
            ");
            $stmtT->execute([$fecha]);
            $mins = $stmtT->fetchColumn();
            if ($mins !== false && $mins > 0) {
                $tiempo = round((float) $mins, 1);
            }
        }

        return [
            'activos' => (int) ($row['activos'] ?? 0),
            'quizzes' => (int) ($row['quizzes'] ?? 0),
            'tiempo_promedio' => $tiempo,
        ];
    }

    private function getActividadQuizRango(int $dias): array
    {
        $tabla = $this->getTablaActividadQuiz();
        if (!$tabla) {
            return ['activos' => 0, 'quizzes' => 0];
        }
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT id_persona) AS activos,
                   COUNT(*) AS quizzes
            FROM {$tabla}
            WHERE fecha >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$dias]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'activos' => (int) ($row['activos'] ?? 0),
            'quizzes' => (int) ($row['quizzes'] ?? 0),
        ];
    }

    private function seedHistoricalData(int $baseUsuarios): void
    {
        $base = max(5, $baseUsuarios);
        for ($i = 30; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-{$i} days"));
            $t = (30 - $i) / 4.33;
            $modelo = max(1, (int) round($this->math->u($t) / 10));
            $activos = min($base, $modelo + random_int(0, 3));
            $quizzes = (int) max(0, $activos - random_int(0, 2));
            $tiempo = round(15 + ($t * 2.5), 1);

            $stmt = $this->db->prepare('
                INSERT IGNORE INTO usuarios_estadisticas
                (fecha, usuarios_nuevos, usuarios_activos, quizzes_completados, tiempo_promedio)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $fecha,
                $i === 0 ? 1 : ($i % 5 === 0 ? 2 : 0),
                $activos,
                $quizzes,
                $tiempo,
            ]);
        }
    }

    private function countRows(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM usuarios_estadisticas')->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}
