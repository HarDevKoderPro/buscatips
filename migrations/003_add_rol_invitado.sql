-- Ejecutar una sola vez en instalaciones existentes antes de habilitar Google Sign-In.
INSERT INTO roles (nombre, descripcion) VALUES
  ('invitado', 'Consulta autenticada de contenido')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);
