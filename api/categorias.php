<?php

require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $db = obtenerConexion();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $categorias = $db->query('SELECT id, nombre, fecha_creacion FROM categorias ORDER BY nombre ASC')->fetchAll();
        responderJSON(200, true, $categorias, count($categorias) . ' categoria(s) en total.');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responderJSON(405, false, null, 'Metodo no permitido.');
    }

    $datos = json_decode(file_get_contents('php://input'), true);
    $nombre = is_array($datos) && isset($datos['nombre']) ? trim($datos['nombre']) : '';

    if ($nombre === '') {
        responderJSON(400, false, null, 'El nombre de la categoria es obligatorio.');
    }
    if (mb_strlen($nombre) > 100) {
        responderJSON(400, false, null, 'El nombre de la categoria no puede exceder 100 caracteres.');
    }

    $stmt = $db->prepare('SELECT id, nombre, fecha_creacion FROM categorias WHERE nombre = :nombre');
    $stmt->execute([':nombre' => $nombre]);
    $existente = $stmt->fetch();
    if ($existente) {
        responderJSON(200, true, $existente, 'La categoria ya existia.');
    }

    $stmt = $db->prepare('INSERT INTO categorias (nombre) VALUES (:nombre)');
    $stmt->execute([':nombre' => $nombre]);
    $id = (int) $db->lastInsertId();
    $stmt = $db->prepare('SELECT id, nombre, fecha_creacion FROM categorias WHERE id = :id');
    $stmt->execute([':id' => $id]);
    responderJSON(201, true, $stmt->fetch(), 'Categoria creada exitosamente.');
} catch (PDOException $e) {
    responderJSON(500, false, null, ENTORNO === 'local' ? $e->getMessage() : 'Error interno del servidor.');
}
