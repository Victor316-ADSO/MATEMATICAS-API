-- Administradores del panel Analytics (sin registro público)
-- Insertar usuarios manualmente en esta tabla.

CREATE TABLE IF NOT EXISTS admin_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL DEFAULT 'Administrador',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IMPORTANTE: password_hash debe ser bcrypt ($2y$...), NO texto plano.
-- Generar hash: php database/hash_admin_password.php "TuContraseña"

-- Usuario de ejemplo (contraseña: Admin2025!)
INSERT INTO admin_usuarios (email, password_hash, nombre, activo) VALUES (
    'admin@analytics.local',
    '$2y$10$OFl1JNRHNZdz/1DZOlB5QOb9sZARhNCs0d3VSX.IwDv6PmW951./W',
    'Administrador Analytics',
    1
) ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);
