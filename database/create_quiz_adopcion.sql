-- Quiz de adopción tecnológica U(t)
-- Ejecutar una vez en MySQL antes de usar el endpoint (o usar database/seed_quiz_adopcion.php)

CREATE TABLE IF NOT EXISTS quiz_adopcion_preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden INT NOT NULL UNIQUE,
    pregunta TEXT NOT NULL,
    retroalimentacion TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quiz_adopcion_opciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pregunta INT NOT NULL,
    texto TEXT NOT NULL,
    es_correcta TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_pregunta) REFERENCES quiz_adopcion_preguntas(id) ON DELETE CASCADE,
    INDEX idx_pregunta (id_pregunta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quiz_adopcion_intentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_persona VARCHAR(32) NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aciertos INT NOT NULL DEFAULT 0,
    total INT NOT NULL DEFAULT 0,
    INDEX idx_persona_fecha (id_persona, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quiz_adopcion_respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_intento INT NOT NULL,
    id_pregunta INT NOT NULL,
    texto_respuesta TEXT NOT NULL,
    es_correcta TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_intento) REFERENCES quiz_adopcion_intentos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_pregunta) REFERENCES quiz_adopcion_preguntas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
