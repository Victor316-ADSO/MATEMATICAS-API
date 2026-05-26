<?php
namespace App\Services;

/**
 * Análisis de adopción tecnológica mediante cálculo diferencial.
 *
 * Modelo polinómico de usuarios U(t):
 *   U(t)  = -2t⁴ + 32t³ - 180t² + 432t + 100
 *   U'(t) = -8t³ + 96t² - 360t + 432
 *   U''(t)= -24t² + 192t - 360
 *
 * t representa semanas desde el inicio de la plataforma (escala configurable).
 */
class MathematicalAnalysisService
{
    /** Evalúa U(t): usuarios modelados por la función de adopción. */
    public function u(float $t): float
    {
        return -2 * $t ** 4 + 32 * $t ** 3 - 180 * $t ** 2 + 432 * $t + 100;
    }

    /** Primera derivada U'(t): velocidad de crecimiento. */
    public function uPrime(float $t): float
    {
        return -8 * $t ** 3 + 96 * $t ** 2 - 360 * $t + 432;
    }

    /** Segunda derivada U''(t): aceleración / concavidad del crecimiento. */
    public function uDoublePrime(float $t): float
    {
        return -24 * $t ** 2 + 192 * $t - 360;
    }

    /**
     * Genera puntos para graficar U, U' y U'' en un intervalo.
     */
    public function generateCurve(float $tMin, float $tMax, int $steps = 50): array
    {
        $steps = max(2, $steps);
        $dt = ($tMax - $tMin) / ($steps - 1);
        $curve = [];

        for ($i = 0; $i < $steps; $i++) {
            $t = round($tMin + $dt * $i, 2);
            $curve[] = [
                't' => $t,
                'u' => round($this->u($t), 2),
                'u_prime' => round($this->uPrime($t), 2),
                'u_double_prime' => round($this->uDoublePrime($t), 2),
            ];
        }

        return $curve;
    }

    /**
     * Análisis en el instante t: clasificación según derivadas.
     */
    public function analyzeAt(float $t): array
    {
        $up = $this->uPrime($t);
        $upp = $this->uDoublePrime($t);
        $u = $this->u($t);

        $growthRate = $this->classifyGrowthRate($up);
        $acceleration = $this->classifyAcceleration($upp);
        $stagnation = abs($up) < 5;

        return [
            't' => $t,
            'u' => round($u, 2),
            'u_prime' => round($up, 2),
            'u_double_prime' => round($upp, 2),
            'growth_rate' => $growthRate,
            'acceleration' => $acceleration,
            'is_stagnant' => $stagnation,
            'is_growing' => $up > 0,
            'is_decelerating' => $upp < 0 && $up > 0,
            'is_accelerating' => $upp > 0,
        ];
    }

    /**
     * Encuentra puntos críticos (U'(t) ≈ 0) y máximos/mínimos locales.
     */
    public function findCriticalPoints(float $tMin = 0, float $tMax = 12, float $step = 0.05): array
    {
        $points = [];
        $prevSign = null;
        $prevT = $tMin;
        $prevPrime = $this->uPrime($tMin);

        for ($t = $tMin + $step; $t <= $tMax; $t += $step) {
            $prime = $this->uPrime($t);
            $sign = $prime >= 0 ? 1 : -1;

            if ($prevSign !== null && $sign !== $prevSign) {
                $criticalT = $this->bisectZero($prevT, $t);
                $second = $this->uDoublePrime($criticalT);
                $type = $second < 0 ? 'maximo_local' : ($second > 0 ? 'minimo_local' : 'punto_silla');

                $points[] = [
                    't' => round($criticalT, 2),
                    'u' => round($this->u($criticalT), 2),
                    'u_prime' => round($this->uPrime($criticalT), 4),
                    'u_double_prime' => round($second, 2),
                    'tipo' => $type,
                ];
            }

            $prevSign = $sign;
            $prevT = $t;
            $prevPrime = $prime;
        }

        return $points;
    }

    /**
     * Puntos de inflexión donde U'' cambia de signo.
     */
    public function findInflectionPoints(float $tMin = 0, float $tMax = 12, float $step = 0.05): array
    {
        $points = [];
        $prevSign = null;
        $prevT = $tMin;

        for ($t = $tMin + $step; $t <= $tMax; $t += $step) {
            $second = $this->uDoublePrime($t);
            $sign = $second >= 0 ? 1 : -1;

            if ($prevSign !== null && $sign !== $prevSign) {
                $inflectionT = $this->bisectZeroSecond($prevT, $t);
                $points[] = [
                    't' => round($inflectionT, 2),
                    'u' => round($this->u($inflectionT), 2),
                    'u_double_prime' => round($this->uDoublePrime($inflectionT), 4),
                    'concavidad_antes' => $prevSign > 0 ? 'concava_arriba' : 'concava_abajo',
                    'concavidad_despues' => $sign > 0 ? 'concava_arriba' : 'concava_abajo',
                ];
            }

            $prevSign = $sign;
            $prevT = $t;
        }

        return $points;
    }

    /**
     * Alertas inteligentes basadas en U''(t) y comportamiento de U'(t).
     */
    public function generateAlerts(float $t): array
    {
        $analysis = $this->analyzeAt($t);
        $upp = $analysis['u_double_prime'];
        $up = $analysis['u_prime'];
        $alerts = [];

        if ($analysis['is_accelerating'] && $up > 0) {
            $alerts[] = [
                'tipo' => 'aceleracion',
                'nivel' => 'success',
                'mensaje' => 'La plataforma está creciendo aceleradamente.',
                'criterio' => "U''(t) = {$upp} > 0 y U'(t) > 0",
            ];
        }

        if ($analysis['is_decelerating']) {
            $alerts[] = [
                'tipo' => 'desaceleracion',
                'nivel' => 'warning',
                'mensaje' => 'Existe una desaceleración en el crecimiento.',
                'criterio' => "U''(t) = {$upp} < 0 con crecimiento positivo",
            ];
        }

        if ($upp < -10 && abs($up) < 15) {
            $alerts[] = [
                'tipo' => 'saturacion',
                'nivel' => 'danger',
                'mensaje' => 'Se detectó una posible saturación tecnológica.',
                'criterio' => "U''(t) muy negativa y U'(t) cercana a cero",
            ];
        }

        $criticos = $this->findCriticalPoints(max(0, $t - 2), $t + 0.5);
        foreach ($criticos as $c) {
            if ($c['tipo'] === 'maximo_local' && abs($c['t'] - $t) < 0.5) {
                $alerts[] = [
                    'tipo' => 'maximo_local',
                    'nivel' => 'info',
                    'mensaje' => 'El sistema alcanzó un máximo local de adopción.',
                    'criterio' => "U'(t) = 0 y U''(t) < 0 en t ≈ {$c['t']}",
                ];
            }
        }

        if ($analysis['is_stagnant']) {
            $alerts[] = [
                'tipo' => 'estancamiento',
                'nivel' => 'secondary',
                'mensaje' => 'Se detectó estancamiento en la curva de adopción.',
                'criterio' => '|U\'(t)| < 5',
            ];
        }

        $inflexiones = $this->findInflectionPoints(max(0, $t - 1), $t + 1);
        foreach ($inflexiones as $inf) {
            if (abs($inf['t'] - $t) < 0.3) {
                $alerts[] = [
                    'tipo' => 'inflexion',
                    'nivel' => 'primary',
                    'mensaje' => 'Punto de inflexión: cambio en la concavidad del crecimiento.',
                    'criterio' => "U''(t) cambia de signo en t ≈ {$inf['t']}",
                ];
            }
        }

        if (empty($alerts)) {
            $alerts[] = [
                'tipo' => 'estable',
                'nivel' => 'info',
                'mensaje' => 'La adopción tecnológica se mantiene en rango estable.',
                'criterio' => 'Sin condiciones críticas en el intervalo actual',
            ];
        }

        return $alerts;
    }

    /**
     * Módulo predictivo: proyección y riesgos.
     */
    /**
     * Proyección alineada con usuarios reales: el polinomio U(t) solo es interpretable en t ∈ [0, ~8].
     *
     * @param float $currentT Semanas en dominio del modelo (0.5–6.5)
     * @param float $usuariosBase Promedio de usuarios activos reales (escala de la plataforma)
     */
    public function predict(float $currentT, int $monthsAhead = 6, float $usuariosBase = 35.0): array
    {
        $currentT = max(0.5, min(6.5, $currentT));
        $usuariosBase = max(5, $usuariosBase);
        $uActual = max(1, $this->u($currentT));

        $predictions = [];
        $saturationMonths = [];
        $stabilityMonths = [];

        $prevProyectados = (int) round($usuariosBase);

        for ($m = 1; $m <= $monthsAhead; $m++) {
            $futureT = min(8.0, $currentT + ($m * 0.9));
            $analysis = $this->analyzeAt($futureT);
            $uFuturo = $this->u($futureT);
            $proyectados = (int) round(max(5, ($uFuturo / $uActual) * $usuariosBase));

            $predictions[] = [
                'mes' => $m,
                't' => round($futureT, 2),
                'usuarios_proyectados' => $proyectados,
                'velocidad' => $analysis['u_prime'],
                'aceleracion' => $analysis['u_double_prime'],
                'tendencia' => $this->classifyProjectionTrend(
                    $analysis['u_prime'],
                    $proyectados,
                    $prevProyectados
                ),
                'tendencia_modelo' => $analysis['growth_rate'],
            ];

            $prevProyectados = $proyectados;

            if ($analysis['u_double_prime'] < -15 && $analysis['u_prime'] < 40) {
                $saturationMonths[] = $m;
            }
            if ($analysis['is_stagnant'] || abs($analysis['u_prime']) < 8) {
                $stabilityMonths[] = $m;
            }
        }

        $current = $this->analyzeAt($currentT);
        $abandonRisk = 'bajo';
        if ($current['u_double_prime'] < 0 && $current['u_prime'] < 25) {
            $abandonRisk = 'medio';
        }
        if ($current['is_stagnant'] || ($current['u_prime'] < 10 && $current['u_double_prime'] < -10)) {
            $abandonRisk = 'alto';
        }

        $primerMes = $predictions[0]['usuarios_proyectados'] ?? $usuariosBase;
        $crecimientoFuturo = max(0, $primerMes - (int) round($usuariosBase));

        return [
            'proyeccion' => $predictions,
            'riesgo_abandono' => $abandonRisk,
            'meses_saturacion_estimados' => $saturationMonths,
            'meses_estabilizacion' => $stabilityMonths,
            'crecimiento_futuro_estimado' => $crecimientoFuturo,
        ];
    }

    /** Curva U(t) para visualización (dominio pedagógico 0–8 semanas, sin valores negativos en gráfica). */
    public function generateCurveForChart(float $tMin = 0, float $tMax = 8, int $steps = 50): array
    {
        $curve = $this->generateCurve($tMin, $tMax, $steps);
        foreach ($curve as &$p) {
            $p['u'] = round(max(0, $p['u']), 2);
        }
        unset($p);
        return $curve;
    }

    /** Puntos críticos relevantes para adopción tecnológica (t ≤ 8). */
    public function findCriticalPointsForDashboard(float $tMax = 8): array
    {
        $points = $this->findCriticalPoints(0, $tMax, 0.08);
        $filtered = [];
        foreach ($points as $p) {
            if ($p['t'] <= $tMax && $p['u'] >= 0) {
                $filtered[] = $p;
            }
        }
        return array_slice($filtered, 0, 5);
    }

    private function classifyGrowthRate(float $up): string
    {
        if ($up > 50) {
            return 'crecimiento_rapido';
        }
        if ($up > 0) {
            return 'crecimiento_moderado';
        }
        if (abs($up) < 5) {
            return 'estancamiento';
        }
        return 'decrecimiento';
    }

    /**
     * Etiqueta legible para la lista mensual: combina U'(t) del modelo y variación de usuarios proyectados.
     */
    private function classifyProjectionTrend(float $up, int $proyectados, int $proyectadosPrev): string
    {
        $delta = $proyectados - $proyectadosPrev;

        if ($delta >= 2) {
            return 'crecimiento';
        }
        if ($delta <= -2) {
            return 'decrecimiento';
        }
        if (abs($delta) <= 1) {
            return 'estabilización';
        }

        if ($up > 0) {
            return 'crecimiento moderado';
        }
        if (abs($up) < 8) {
            return 'estabilización';
        }

        return 'decrecimiento (modelo U\'<0)';
    }

    private function classifyAcceleration(float $upp): string
    {
        if ($upp > 5) {
            return 'aceleracion';
        }
        if ($upp < -5) {
            return 'desaceleracion';
        }
        return 'velocidad_constante';
    }

    /** Bisección para raíz de U'(t) = 0. */
    private function bisectZero(float $a, float $b): float
    {
        for ($i = 0; $i < 30; $i++) {
            $mid = ($a + $b) / 2;
            if ($this->uPrime($a) * $this->uPrime($mid) <= 0) {
                $b = $mid;
            } else {
                $a = $mid;
            }
        }
        return ($a + $b) / 2;
    }

    /** Bisección para raíz de U''(t) = 0. */
    private function bisectZeroSecond(float $a, float $b): float
    {
        for ($i = 0; $i < 30; $i++) {
            $mid = ($a + $b) / 2;
            if ($this->uDoublePrime($a) * $this->uDoublePrime($mid) <= 0) {
                $b = $mid;
            } else {
                $a = $mid;
            }
        }
        return ($a + $b) / 2;
    }
}
