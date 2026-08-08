-- Ejecutar una sola vez en cada base de datos existente antes del despliegue.
CREATE TABLE IF NOT EXISTS categorias (
  id INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tips
  ADD COLUMN categoria_id INT NULL AFTER contenido,
  ADD CONSTRAINT fk_tips_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
    ON DELETE SET NULL;
