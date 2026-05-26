<?php
/**
 * Pobla la BD con métricas realistas y alineadas al modelo U(t) para Analytics.
 * Ejecutar: php database/seed_analytics_datos.php --reset
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Data\QuizAdopcionSeedData;
use App\Services\MathematicalAnalysisService;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$reset = in_array('--reset', $argv ?? [], true);
$DAYS_HISTORY = 90;

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'],
    $_ENV['DB_NAME']
);
$db = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$math = new MathematicalAnalysisService();

echo "=== Seed Analytics (métricas optimizadas) ===\n";

foreach (['create_usuarios_estadisticas.sql', 'create_quiz_adopcion.sql'] as $file) {
    $path = __DIR__ . '/' . $file;
    if (!is_readable($path)) {
        continue;
    }
    foreach (array_filter(array_map('trim', explode(';', file_get_contents($path)))) as $stmt) {
        if (stripos($stmt, 'CREATE TABLE') !== false) {
            $db->exec($stmt);
        }
    }
}

$db->exec("
    CREATE TABLE IF NOT EXISTS tecni_encuesta_realizada (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_persona VARCHAR(32) NOT NULL,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_persona_fecha (id_persona, fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if ($reset) {
    echo "Limpiando datos demo...\n";
    $db->exec('DELETE FROM quiz_adopcion_respuestas');
    $db->exec('DELETE FROM quiz_adopcion_intentos');
    $db->exec('DELETE FROM usuarios_estadisticas');
    $db->exec('DELETE FROM tecni_encuesta_realizada');
}

// Programas
$programas = [];
foreach ([
    'SELECT EvalDCod_Prog AS codigo FROM programa LIMIT 30',
    'SELECT codi_prog AS codigo FROM programa LIMIT 30',
] as $sql) {
    try {
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($rows)) {
            $programas = array_values(array_unique(array_map('strval', $rows)));
            break;
        }
    } catch (Throwable $e) {
        continue;
    }
}
if (empty($programas)) {
    $programas = ['TLMA', 'TLSI', 'TLAD', 'TLEN', 'TLPS', 'TLGT'];
}

$nombres = [
    ['Carlos', 'Mendoza'], ['Laura', 'Gómez'], ['Andrés', 'Rivas'], ['María', 'Torres'],
    ['Julián', 'Pardo'], ['Diana', 'Suárez'], ['Felipe', 'Castro'], ['Valentina', 'López'],
    ['Santiago', 'Herrera'], ['Camila', 'Vargas'], ['Diego', 'Muñoz'], ['Isabella', 'Rojas'],
    ['Mateo', 'Salazar'], ['Sofía', 'Quintero'], ['Nicolás', 'Ortiz'], ['Paula', 'Jiménez'],
    ['Sebastián', 'Cardona'], ['Daniela', 'Mejía'], ['Tomás', 'Giraldo'], ['Lucía', 'Parra'],
];

$tieneCodiIden = (int) $db->query("
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'personas' AND column_name = 'codi_iden'
")->fetchColumn() > 0;

$insPersona = $tieneCodiIden
    ? $db->prepare('INSERT IGNORE INTO personas (iden_pers, codi_iden, nomb_pers, ape1_pers) VALUES (?, ?, ?, ?)')
    : $db->prepare('INSERT IGNORE INTO personas (iden_pers, nomb_pers, ape1_pers) VALUES (?, ?, ?)');
$insEgresado = $db->prepare('INSERT IGNORE INTO egresados (iden_pers, codi_prog) VALUES (?, ?)');

$targetUsuarios = 96;
$existentes = (int) $db->query('SELECT COUNT(*) FROM egresados')->fetchColumn();
$nuevos = 0;

for ($i = $existentes; $i < $targetUsuarios; $i++) {
    $iden = (string) (1001000000 + $i);
    $n = $nombres[$i % count($nombres)];
    $prog = $programas[$i % count($programas)];
    if ($tieneCodiIden) {
        $insPersona->execute([$iden, 'CC', $n[0], $n[1]]);
    } else {
        $insPersona->execute([$iden, $n[0], $n[1]]);
    }
    $insEgresado->execute([$iden, $prog]);
    if ($insEgresado->rowCount() > 0) {
        $nuevos++;
    }
}

$totalEgresados = (int) $db->query('SELECT COUNT(*) FROM egresados')->fetchColumn();
echo "Egresados: {$totalEgresados} (+{$nuevos} nuevos)\n";

if ((int) $db->query('SELECT COUNT(*) FROM quiz_adopcion_preguntas')->fetchColumn() === 0) {
    $insP = $db->prepare('INSERT INTO quiz_adopcion_preguntas (orden, pregunta, retroalimentacion) VALUES (?, ?, ?)');
    $insO = $db->prepare('INSERT INTO quiz_adopcion_opciones (id_pregunta, texto, es_correcta) VALUES (?, ?, ?)');
    foreach (QuizAdopcionSeedData::preguntas() as $p) {
        $insP->execute([$p['orden'], $p['pregunta'], $p['retroalimentacion']]);
        $idP = (int) $db->lastInsertId();
        foreach ($p['opciones'] as $o) {
            $insO->execute([$idP, $o['texto'], $o['correcta'] ? 1 : 0]);
        }
    }
}

$idsPersonas = $db->query('SELECT iden_pers FROM egresados')->fetchAll(PDO::FETCH_COLUMN);
$capUsuarios = count($idsPersonas);

$insQuiz = $db->prepare('
    INSERT INTO quiz_adopcion_intentos (id_persona, fecha, aciertos, total) VALUES (?, ?, ?, 10)
');
$insEncuesta = $db->prepare('INSERT INTO tecni_encuesta_realizada (id_persona, fecha) VALUES (?, ?)');

$actividadPorDia = [];
$intentosGenerados = 0;

/**
 * Curva de adopción: más actividad en días recientes (fase de viralización U(t) baja-media).
 * $d = días atrás (0 = hoy).
 */
$activosPlaneados = static function (int $d, int $totalDias, int $cap, MathematicalAnalysisService $math): int {
    $progreso = 1 - ($d / max(1, $totalDias));
    $t = $progreso * 5.2;
    $base = (int) round($math->u($t) / 14);
    $minimo = 10 + (int) round($progreso * 18);
    $conRuido = $base + mt_rand(-2, 3);
    return (int) max($minimo, min($cap, $conRuido));
};

for ($d = $DAYS_HISTORY; $d >= 0; $d--) {
    $fecha = date('Y-m-d', strtotime("-{$d} days"));
    $activosDia = $activosPlaneados($d, $DAYS_HISTORY, $capUsuarios, $math);

    shuffle($idsPersonas);
    $seleccionados = array_slice($idsPersonas, 0, $activosDia);
    $quizzesDia = 0;

    foreach ($seleccionados as $iden) {
        if (mt_rand(1, 100) > 82) {
            continue;
        }
        $hora = sprintf('%02d:%02d:00', mt_rand(8, 21), mt_rand(0, 59));
        $fechaHora = "{$fecha} {$hora}";
        $aciertos = mt_rand(6, 10);
        $insQuiz->execute([$iden, $fechaHora, $aciertos]);
        $insEncuesta->execute([$iden, $fechaHora]);
        $quizzesDia++;
        $intentosGenerados++;
    }

    $actividadPorDia[$fecha] = [
        'activos' => $activosDia,
        'quizzes' => $quizzesDia,
    ];
}

echo "Intentos quiz: {$intentosGenerados}\n";

$upsertStats = $db->prepare('
    INSERT INTO usuarios_estadisticas (fecha, usuarios_nuevos, usuarios_activos, quizzes_completados, tiempo_promedio)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        usuarios_nuevos = VALUES(usuarios_nuevos),
        usuarios_activos = VALUES(usuarios_activos),
        quizzes_completados = VALUES(quizzes_completados),
        tiempo_promedio = VALUES(tiempo_promedio)
');

for ($d = $DAYS_HISTORY; $d >= 0; $d--) {
    $fecha = date('Y-m-d', strtotime("-{$d} days"));
    $act = $actividadPorDia[$fecha];

    $nuevos = 0;
    if ($d >= $DAYS_HISTORY - 14) {
        $nuevos = mt_rand(2, 6);
    } elseif ($d % 10 === 0) {
        $nuevos = mt_rand(1, 4);
    } elseif ($d % 5 === 0) {
        $nuevos = mt_rand(0, 2);
    }

    $progreso = 1 - ($d / $DAYS_HISTORY);
    $tiempo = round(20 + $progreso * 12 + mt_rand(-2, 3), 1);

    $upsertStats->execute([
        $fecha,
        $nuevos,
        $act['activos'],
        $act['quizzes'],
        min(42.0, $tiempo),
    ]);
}

// Resumen verificación
$stmt = $db->query("
    SELECT COUNT(*) AS dias,
           ROUND(AVG(usuarios_activos),1) AS prom_activos,
           SUM(quizzes_completados) AS total_quizzes
    FROM usuarios_estadisticas
    WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$resumen = $stmt->fetch(PDO::FETCH_ASSOC);
$quizzes30 = (int) $db->query("
    SELECT COUNT(*) FROM quiz_adopcion_intentos
    WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetchColumn();

echo "Últimos 30d — días: {$resumen['dias']}, activos prom/día: {$resumen['prom_activos']}, quizzes (stats): {$resumen['total_quizzes']}, quizzes (tabla): {$quizzes30}\n";
echo "=== Listo. Abre /admin/analytics y pulsa Actualizar ===\n";
