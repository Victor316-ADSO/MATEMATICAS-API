-- Módulo Analytics Matemático: métricas diarias de adopción tecnológica
-- Ejecutar en la misma base de datos del proyecto (MySQL)

CREATE TABLE IF NOT EXISTS usuarios_estadisticas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    usuarios_nuevos INT NOT NULL DEFAULT 0,
    usuarios_activos INT NOT NULL DEFAULT 0,
    quizzes_completados INT NOT NULL DEFAULT 0,
    tiempo_promedio DECIMAL(8,2) NOT NULL DEFAULT 0 COMMENT 'Minutos promedio de estudio',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fecha (fecha),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crecimiento diario
-- SELECT fecha, usuarios_nuevos, usuarios_activos, quizzes_completados
-- FROM usuarios_estadisticas ORDER BY fecha DESC;

-- Crecimiento semanal
-- SELECT YEARWEEK(fecha, 1) AS semana,
--        MIN(fecha) AS inicio_semana,
--        SUM(usuarios_nuevos) AS nuevos,
--        AVG(usuarios_activos) AS activos_promedio,
--        SUM(quizzes_completados) AS quizzes
-- FROM usuarios_estadisticas
-- GROUP BY YEARWEEK(fecha, 1)
-- ORDER BY semana DESC;

-- Promedio mensual
-- SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes,
--        AVG(usuarios_activos) AS activos_promedio,
--        SUM(quizzes_completados) AS quizzes,
--        AVG(tiempo_promedio) AS estudio_promedio
-- FROM usuarios_estadisticas
-- GROUP BY DATE_FORMAT(fecha, '%Y-%m')
-- ORDER BY mes DESC;

-- Tendencia de uso (últimos 30 días con variación)
-- SELECT fecha, usuarios_activos,
--        usuarios_activos - LAG(usuarios_activos) OVER (ORDER BY fecha) AS variacion
-- FROM usuarios_estadisticas
-- WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
-- ORDER BY fecha;
