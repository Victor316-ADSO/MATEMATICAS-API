<?php
/** Verifica coherencia analytics ↔ gráficas. php database/verify_analytics.php */
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $_ENV['DB_HOST'], $_ENV['DB_NAME']);
$db = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$svc = new App\Services\AnalyticsService($db);
$data = $svc->getDashboardData();

echo "=== COHERENCIA ANALYTICS ===\n\n";

$t = $data['matematico'];
$a = $t['analisis_actual'];
echo "t_actual (modelo): {$t['t_actual']}\n";
echo "U(t): {$a['u']} | U'(t): {$a['u_prime']} | U''(t): {$a['u_double_prime']}\n";
echo "Tendencia: {$a['growth_rate']} | Activos reales ref: {$t['usuarios_reales_referencia']}\n\n";

echo "Predicción mes 1: " . ($t['prediccion']['proyeccion'][0]['usuarios_proyectados'] ?? 0) . " usuarios\n";
echo "Crecimiento futuro: {$t['prediccion']['crecimiento_futuro_estimado']}\n\n";

$g = $data['graficas'];
$real = $g['crecimiento_usuarios']['datasets'][0]['data'] ?? [];
$modelo = $g['crecimiento_usuarios']['datasets'][1]['data'] ?? [];
echo "Crecimiento chart: " . count($real) . " puntos real, " . count($modelo) . " modelo\n";
echo "  Real min/max: " . min($real) . "/" . max($real) . "\n";
echo "  Modelo min/max: " . min($modelo) . "/" . max($modelo) . "\n";
$ultimos5Real = array_slice($real, -5);
$ultimos5Mod = array_slice($modelo, -5);
echo "  Últimos 5 real: " . implode(',', $ultimos5Real) . "\n";
echo "  Últimos 5 modelo: " . implode(',', $ultimos5Mod) . "\n\n";

$semA = $g['comparacion_semanal']['activos'] ?? [];
$semQ = $g['comparacion_semanal']['quizzes'] ?? [];
$diaA = array_slice($g['activos_vs_quizzes']['activos'] ?? [], -7);
$diaQ = array_slice($g['activos_vs_quizzes']['quizzes'] ?? [], -7);
echo "Semanal activos avg: ~" . round(array_sum($semA) / max(1, count($semA))) . "\n";
echo "Semanal quizzes avg: ~" . round(array_sum($semQ) / max(1, count($semQ))) . "\n";
echo "Diario últimos 7 activos avg: ~" . round(array_sum($diaA) / max(1, count($diaA))) . "\n";
echo "Diario últimos 7 quizzes avg: ~" . round(array_sum($diaQ) / max(1, count($diaQ))) . "\n\n";

$pred = $g['prediccion']['data'] ?? [];
echo "Predicción chart data: " . implode(', ', $pred) . "\n";

$curva = $t['curva'];
$uMin = min(array_column($curva, 'u'));
$uMax = max(array_column($curva, 'u'));
echo "Curva U(t) display: min={$uMin} max={$uMax} (debe ser >= 0)\n";

$ok = $a['u'] >= 0 && max($pred) > 0 && max($modelo) > 0
    && abs(round(array_sum($semQ) / max(1, count($semQ))) - round(array_sum($diaQ) / max(1, count($diaQ)))) < 15;
echo $ok ? "\n✓ Verificación OK\n" : "\n✗ Revisar inconsistencias\n";
