-- Esquema base para instalaciones nuevas de PIA Tips.
CREATE TABLE IF NOT EXISTS categorias (
  id INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
  id INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(50) NOT NULL COLLATE utf8mb4_unicode_ci,
  descripcion VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT NOT NULL AUTO_INCREMENT,
  usuario VARCHAR(100) NOT NULL COLLATE utf8mb4_unicode_ci,
  correo VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  nombre_mostrado VARCHAR(150) NOT NULL COLLATE utf8mb4_unicode_ci,
  contrasena_hash VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_usuario (usuario),
  UNIQUE KEY uq_usuarios_correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios_roles (
  usuario_id INT NOT NULL,
  rol_id INT NOT NULL,
  PRIMARY KEY (usuario_id, rol_id),
  KEY idx_usuarios_roles_rol (rol_id),
  CONSTRAINT fk_usuarios_roles_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_usuarios_roles_rol
    FOREIGN KEY (rol_id) REFERENCES roles(id)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS identidades_usuario (
  id INT NOT NULL AUTO_INCREMENT,
  usuario_id INT NOT NULL,
  proveedor VARCHAR(50) NOT NULL COLLATE utf8mb4_unicode_ci,
  identificador_proveedor VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  correo_proveedor VARCHAR(255) NULL COLLATE utf8mb4_unicode_ci,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_identidad_proveedor (proveedor, identificador_proveedor),
  KEY idx_identidades_usuario (usuario_id),
  CONSTRAINT fk_identidades_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (nombre, descripcion) VALUES
  ('admin', 'Administracion completa de la plataforma'),
  ('invitado', 'Consulta autenticada de contenido'),
  ('docente', 'Gestion de contenido, cursos y estudiantes asignados'),
  ('estudiante', 'Consulta de contenido y seguimiento academico propio')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

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
