<?php
/**
 * Genera hash bcrypt para insertar administradores en admin_usuarios.
 *
 * Uso: php database/hash_admin_password.php "TuContraseña"
 * Ejemplo INSERT:
 *   INSERT INTO admin_usuarios (email, password_hash, nombre, activo)
 *   VALUES ('correo@ejemplo.com', 'PEGAR_HASH_AQUI', 'Mi Nombre', 1);
 */

if ($argc < 2) {
    fwrite(STDERR, "Uso: php database/hash_admin_password.php \"TuContraseña\"\n");
    exit(1);
}

$password = $argv[1];
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Contraseña: {$password}\n";
echo "Hash (copiar en password_hash):\n{$hash}\n";
