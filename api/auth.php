<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sesion.php';

iniciarSesionSegura();
$metodo = $_SERVER['REQUEST_METHOD'];

try {
    if ($metodo === 'GET') {
        responderJSON(200, true, [
            'usuario' => obtenerUsuarioSesion(),
            'google_client_id' => GOOGLE_CLIENT_ID,
        ], 'Estado de sesion obtenido.');
    }

    if ($metodo !== 'POST') {
        responderJSON(405, false, null, 'Metodo no permitido.');
    }

    $datos = json_decode(file_get_contents('php://input'), true);
    if (!is_array($datos)) {
        responderJSON(400, false, null, 'El cuerpo de la peticion debe ser JSON valido.');
    }

    if (($datos['accion'] ?? '') === 'google_login') {
        iniciarSesionGoogle($datos);
    }
    if (($datos['accion'] ?? '') === 'logout') {
        cerrarSesion();
    }
    responderJSON(400, false, null, 'La accion solicitada no es valida.');
} catch (PDOException $e) {
    error_log('PIA Auth DB Error: ' . $e->getMessage());
    responderJSON(500, false, null, 'Error interno del servidor.');
}

function iniciarSesionGoogle(array $datos): void
{
    if (GOOGLE_CLIENT_ID === '') {
        responderJSON(503, false, null, 'El acceso con Google aun no esta configurado.');
    }
    $credencial = trim((string) ($datos['credential'] ?? ''));
    if ($credencial === '') {
        responderJSON(400, false, null, 'Google no entrego una credencial valida.');
    }

    $respuesta = consultarTokenGoogle($credencial);
    if (!$respuesta || ($respuesta['aud'] ?? '') !== GOOGLE_CLIENT_ID || !in_array($respuesta['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true) || !in_array($respuesta['email_verified'] ?? '', [true, 'true', '1', 1], true) || (int) ($respuesta['exp'] ?? 0) <= time()) {
        responderJSON(401, false, null, 'No fue posible validar la identidad de Google.');
    }

    $sub = trim((string) ($respuesta['sub'] ?? ''));
    $correo = validarCorreoGoogle($respuesta['email'] ?? '');
    $nombre = trim((string) ($respuesta['name'] ?? $correo));
    if ($sub === '' || $nombre === '' || mb_strlen($nombre) > 150) {
        responderJSON(401, false, null, 'La identidad de Google esta incompleta.');
    }

    $db = obtenerConexion();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT usuario_id FROM identidades_usuario WHERE proveedor = :proveedor AND identificador_proveedor = :identificador LIMIT 1');
        $stmt->execute([':proveedor' => 'google', ':identificador' => $sub]);
        $usuarioId = $stmt->fetchColumn();

        if (!$usuarioId) {
            $stmt = $db->prepare('SELECT id FROM usuarios WHERE correo = :correo LIMIT 1');
            $stmt->execute([':correo' => $correo]);
            $usuarioId = $stmt->fetchColumn();
        }
        if (!$usuarioId) {
            $usuarioGoogle = 'google_' . $sub;
            $stmt = $db->prepare('INSERT INTO usuarios (usuario, correo, nombre_mostrado, contrasena_hash) VALUES (:usuario, :correo, :nombre, NULL)');
            $stmt->execute([':usuario' => $usuarioGoogle, ':correo' => $correo, ':nombre' => $nombre]);
            $usuarioId = (int) $db->lastInsertId();
        } else {
            $stmt = $db->prepare('UPDATE usuarios SET correo = :correo, nombre_mostrado = :nombre, activo = 1 WHERE id = :id');
            $stmt->execute([':correo' => $correo, ':nombre' => $nombre, ':id' => $usuarioId]);
        }

        $stmt = $db->prepare('INSERT IGNORE INTO identidades_usuario (usuario_id, proveedor, identificador_proveedor, correo_proveedor) VALUES (:usuario_id, :proveedor, :identificador, :correo)');
        $stmt->execute([':usuario_id' => $usuarioId, ':proveedor' => 'google', ':identificador' => $sub, ':correo' => $correo]);

        $rol = strtolower($correo) === 'hardevkoder@gmail.com' ? 'admin' : 'invitado';
        $stmt = $db->prepare('SELECT id FROM roles WHERE nombre = :nombre LIMIT 1');
        $stmt->execute([':nombre' => $rol]);
        $rolId = $stmt->fetchColumn();
        if (!$rolId) {
            throw new RuntimeException("El rol $rol no existe.");
        }
        // Cada acceso Google recibe el rol determinado por la cuenta autenticada.
        $stmt = $db->prepare('DELETE ur FROM usuarios_roles ur INNER JOIN roles r ON r.id = ur.rol_id WHERE ur.usuario_id = :usuario_id AND r.nombre != :rol');
        $stmt->execute([':usuario_id' => $usuarioId, ':rol' => $rol]);
        $stmt = $db->prepare('INSERT IGNORE INTO usuarios_roles (usuario_id, rol_id) VALUES (:usuario_id, :rol_id)');
        $stmt->execute([':usuario_id' => $usuarioId, ':rol_id' => $rolId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    guardarUsuarioSesion((int) $usuarioId);
    responderJSON(200, true, obtenerUsuarioSesion(), 'Inicio de sesion con Google realizado.');
}

function consultarTokenGoogle(string $credencial): ?array
{
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credencial);
    $contexto = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $resultado = @file_get_contents($url, false, $contexto);
    $datos = is_string($resultado) ? json_decode($resultado, true) : null;
    return is_array($datos) ? $datos : null;
}

function validarCorreoGoogle($valor): string
{
    $correo = trim((string) $valor);
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 255) {
        responderJSON(401, false, null, 'Google no entrego un correo valido.');
    }
    return $correo;
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
