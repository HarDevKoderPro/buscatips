-- Esquema base para instalaciones nuevas de PIA Tips.
CREATE TABLE IF NOT EXISTS categorias (
  id INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips (
  id INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  contenido TEXT NOT NULL COLLATE utf8mb4_unicode_ci,
  categoria_id INT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tips_categoria_fecha (categoria_id, fecha_modificacion),
  KEY idx_tips_fecha_modificacion (fecha_modificacion),
  CONSTRAINT fk_tips_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
