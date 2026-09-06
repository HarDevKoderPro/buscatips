<?php

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('pia_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => ENTORNO === 'production',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
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

function exigirUsuarioAutenticado()
{
    iniciarSesionSegura();
    $usuario = obtenerUsuarioSesion();
    if (!$usuario) {
        responderJSON(401, false, null, 'Debes iniciar sesion para modificar informacion.');
    }
    return $usuario;
}
