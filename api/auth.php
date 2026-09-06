<?php

require_once __DIR__ . '/config.php';

iniciarSesionSegura();

$metodo = $_SERVER['REQUEST_METHOD'];

try {
    if ($metodo === 'GET') {
        responderJSON(200, true, obtenerUsuarioSesion(), 'Estado de sesion obtenido.');
    }

    if ($metodo !== 'POST') {
        responderJSON(405, false, null, 'Metodo no permitido.');
    }

    $datos = json_decode(file_get_contents('php://input'), true);
    if (!is_array($datos)) {
        responderJSON(400, false, null, 'El cuerpo de la peticion debe ser JSON valido.');
    }

    $accion = $datos['accion'] ?? '';
    if ($accion === 'configurar_admin') {
        configurarAdministrador($datos);
    }
    if ($accion === 'login') {
        iniciarSesion($datos);
    }
    if ($accion === 'logout') {
        cerrarSesion();
    }

    responderJSON(400, false, null, 'La accion solicitada no es valida.');
} catch (PDOException $e) {
    error_log('PIA Auth DB Error: ' . $e->getMessage());
    responderJSON(500, false, null, 'Error interno del servidor.');
}

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('pia_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => ENTORNO === 'produccion',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function configurarAdministrador(array $datos): void
{
    $db = obtenerConexion();
    $totalUsuarios = (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($totalUsuarios > 0) {
        responderJSON(403, false, null, 'El administrador inicial ya fue configurado.');
    }

    $usuario = validarTexto($datos['usuario'] ?? null, 'usuario', 100);
    $correo = validarCorreo($datos['correo'] ?? null);
    $nombre = validarTexto($datos['nombre_mostrado'] ?? null, 'nombre_mostrado', 150);
    $contrasena = validarContrasena($datos['contrasena'] ?? null);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO usuarios (usuario, correo, nombre_mostrado, contrasena_hash) VALUES (:usuario, :correo, :nombre, :contrasena_hash)');
        $stmt->execute([
            ':usuario' => $usuario,
            ':correo' => $correo,
            ':nombre' => $nombre,
            ':contrasena_hash' => password_hash($contrasena, PASSWORD_DEFAULT),
        ]);

        $usuarioId = (int) $db->lastInsertId();
        $rolId = (int) $db->query("SELECT id FROM roles WHERE nombre = 'admin'")->fetchColumn();
        if ($rolId === 0) {
            throw new RuntimeException('El rol admin no existe.');
        }
        $stmt = $db->prepare('INSERT INTO usuarios_roles (usuario_id, rol_id) VALUES (:usuario_id, :rol_id)');
        $stmt->execute([':usuario_id' => $usuarioId, ':rol_id' => $rolId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    guardarUsuarioSesion($usuarioId);
    responderJSON(201, true, obtenerUsuarioSesion(), 'Administrador inicial creado e inicio de sesion realizado.');
}

function iniciarSesion(array $datos): void
{
    $identificador = trim((string) ($datos['identificador'] ?? ''));
    $contrasena = (string) ($datos['contrasena'] ?? '');
    if ($identificador === '' || $contrasena === '') {
        responderJSON(400, false, null, 'Debe indicar usuario o correo y contrasena.');
    }

    $db = obtenerConexion();
    $stmt = $db->prepare('SELECT id, contrasena_hash FROM usuarios WHERE (usuario = :identificador OR correo = :identificador) AND activo = 1 LIMIT 1');
    $stmt->execute([':identificador' => $identificador]);
    $usuario = $stmt->fetch();
    if (!$usuario || !$usuario['contrasena_hash'] || !password_verify($contrasena, $usuario['contrasena_hash'])) {
        responderJSON(401, false, null, 'Usuario, correo o contrasena incorrectos.');
    }

    guardarUsuarioSesion((int) $usuario['id']);
    responderJSON(200, true, obtenerUsuarioSesion(), 'Inicio de sesion realizado.');
}

function cerrarSesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
    }
    session_destroy();
    responderJSON(200, true, null, 'Sesion cerrada.');
}

function guardarUsuarioSesion(int $usuarioId): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuarioId;
}

function obtenerUsuarioSesion()
{
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }

    $db = obtenerConexion();
    $stmt = $db->prepare('SELECT u.id, u.usuario, u.correo, u.nombre_mostrado, GROUP_CONCAT(r.nombre ORDER BY r.nombre) AS roles FROM usuarios u LEFT JOIN usuarios_roles ur ON ur.usuario_id = u.id LEFT JOIN roles r ON r.id = ur.rol_id WHERE u.id = :id AND u.activo = 1 GROUP BY u.id, u.usuario, u.correo, u.nombre_mostrado');
    $stmt->execute([':id' => $_SESSION['usuario_id']]);
    $usuario = $stmt->fetch();
    if (!$usuario) {
        $_SESSION = [];
        return null;
    }
    $usuario['roles'] = $usuario['roles'] ? explode(',', $usuario['roles']) : [];
    return $usuario;
}

function validarTexto($valor, string $campo, int $maximo): string
{
    $texto = trim((string) $valor);
    if ($texto === '' || mb_strlen($texto) > $maximo) {
        responderJSON(400, false, null, "El campo $campo es obligatorio y no puede exceder $maximo caracteres.");
    }
    return $texto;
}

function validarCorreo($valor): string
{
    $correo = trim((string) $valor);
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 255) {
        responderJSON(400, false, null, 'Debe indicar un correo valido de maximo 255 caracteres.');
    }
    return $correo;
}

function validarContrasena($valor): string
{
    $contrasena = (string) $valor;
    if (strlen($contrasena) < 12) {
        responderJSON(400, false, null, 'La contrasena debe tener al menos 12 caracteres.');
    }
    return $contrasena;
}
