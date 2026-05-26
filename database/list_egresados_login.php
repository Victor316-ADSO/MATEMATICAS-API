<?php
/**
 * Lista usuarios de prueba para login (programa + identificación).
 * Ejecutar: php database/list_egresados_login.php
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $_ENV['DB_HOST'], $_ENV['DB_NAME']);
$db = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$total = (int) $db->query('SELECT COUNT(*) FROM egresados')->fetchColumn();
echo "Total egresados: {$total}\n\n";

if ($total === 0) {
    echo "No hay usuarios. Opciones:\n";
    echo "  1) Registrarse en /registro (programa + documento)\n";
    echo "  2) php database/seed_analytics_datos.php\n";
    exit(0);
}

echo "Ejemplos para iniciar sesión (programa | identificación):\n";
$stmt = $db->query('SELECT codi_prog, iden_pers FROM egresados ORDER BY iden_pers LIMIT 10');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  Programa: {$row['codi_prog']}  |  ID: {$row['iden_pers']}\n";
}

echo "\nUsa el MISMO programa que aparece en la columna codi_prog.\n";
