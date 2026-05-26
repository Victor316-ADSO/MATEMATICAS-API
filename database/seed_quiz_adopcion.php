<?php
/**
 * Sembrar preguntas del quiz de adopción.
 * Ejecutar: php database/seed_quiz_adopcion.php
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'],
    $_ENV['DB_NAME']
);
$db = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sqlFile = __DIR__ . '/create_quiz_adopcion.sql';
if (is_readable($sqlFile)) {
    $raw = file_get_contents($sqlFile);
    foreach (array_filter(array_map('trim', explode(';', $raw))) as $stmt) {
        if (stripos($stmt, 'CREATE TABLE') !== false) {
            $db->exec($stmt);
        }
    }
}

require __DIR__ . '/../src/Data/QuizAdopcionSeedData.php';

use App\Data\QuizAdopcionSeedData;

$count = (int) $db->query('SELECT COUNT(*) FROM quiz_adopcion_preguntas')->fetchColumn();
if ($count > 0) {
    echo "Ya existen {$count} preguntas. Seed omitido.\n";
    exit(0);
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

echo "Seed completado: " . count(QuizAdopcionSeedData::preguntas()) . " preguntas.\n";
