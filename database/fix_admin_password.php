<?php
/**
 * Corrige password_hash de un administrador (bcrypt).
 * Uso: php database/fix_admin_password.php victor@gmail.com 316277
 */

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$email = $argv[1] ?? '';
$password = $argv[2] ?? '';

if ($email === '' || $password === '') {
    fwrite(STDERR, "Uso: php database/fix_admin_password.php email@ejemplo.com \"Contraseña\"\n");
    exit(1);
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'curn';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

$pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $pdo->prepare('UPDATE admin_usuarios SET password_hash = ? WHERE email = ?');
$stmt->execute([$hash, strtolower(trim($email))]);

if ($stmt->rowCount() === 0) {
    fwrite(STDERR, "No se encontró el email: {$email}\n");
    exit(1);
}

echo "OK: password_hash actualizado para {$email}\n";
echo "Hash: {$hash}\n";
