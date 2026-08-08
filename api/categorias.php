<?php

require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $db = obtenerConexion();
    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET') {
        $categorias = $db->query('
            SELECT c.id, c.nombre, c.fecha_creacion, COUNT(t.id) AS total_tips
            FROM categorias c LEFT JOIN tips t ON t.categoria_id = c.id
            GROUP BY c.id, c.nombre, c.fecha_creacion
            ORDER BY c.nombre ASC
        ')->fetchAll();
        responderJSON(200, true, $categorias, count($categorias) . ' categoria(s) en total.');
    }

    if ($metodo === 'POST') {
        $nombre = obtenerNombreCategoria();
        $stmt = $db->prepare('SELECT id, nombre FROM categorias WHERE nombre = :nombre');
        $stmt->execute([':nombre' => $nombre]);
        $existente = $stmt->fetch();
        if ($existente) {
            responderJSON(200, true, $existente, 'La categoria ya existia.');
        }
        $stmt = $db->prepare('INSERT INTO categorias (nombre) VALUES (:nombre)');
        $stmt->execute([':nombre' => $nombre]);
        responderJSON(201, true, obtenerCategoria($db, (int) $db->lastInsertId()), 'Categoria creada exitosamente.');
    }

    $id = obtenerIdCategoria();
    $categoria = obtenerCategoria($db, $id);
    if (!$categoria) {
        responderJSON(404, false, null, 'La categoria no existe.');
    }

    if ($metodo === 'PUT') {
        $nombre = obtenerNombreCategoria();
        $stmt = $db->prepare('SELECT id FROM categorias WHERE nombre = :nombre AND id != :id');
        $stmt->execute([':nombre' => $nombre, ':id' => $id]);
        if ($stmt->fetch()) {
            responderJSON(400, false, null, 'Ya existe una categoria con ese nombre.');
        }
        $stmt = $db->prepare('UPDATE categorias SET nombre = :nombre WHERE id = :id');
        $stmt->execute([':nombre' => $nombre, ':id' => $id]);
        responderJSON(200, true, obtenerCategoria($db, $id), 'Categoria actualizada exitosamente.');
    }

    if ($metodo === 'DELETE') {
        if ((int) $categoria['total_tips'] > 0) {
            responderJSON(400, false, $categoria, 'No se puede eliminar una categoria con tips asignados.');
        }
        $stmt = $db->prepare('DELETE FROM categorias WHERE id = :id');
        $stmt->execute([':id' => $id]);
        responderJSON(200, true, $categoria, 'Categoria eliminada exitosamente.');
    }

    responderJSON(405, false, null, 'Metodo no permitido.');
} catch (PDOException $e) {
    responderJSON(500, false, null, ENTORNO === 'local' ? $e->getMessage() : 'Error interno del servidor.');
}

function obtenerIdCategoria(): int
{
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : false;
    if ($id === false || $id <= 0) {
        responderJSON(400, false, null, 'El parametro "id" debe ser un numero entero positivo.');
    }
    return $id;
}

function obtenerNombreCategoria(): string
{
    $datos = json_decode(file_get_contents('php://input'), true);
    $nombre = is_array($datos) && isset($datos['nombre']) ? trim($datos['nombre']) : '';
    if ($nombre === '') {
        responderJSON(400, false, null, 'El nombre de la categoria es obligatorio.');
    }
    if (mb_strlen($nombre) > 100) {
        responderJSON(400, false, null, 'El nombre de la categoria no puede exceder 100 caracteres.');
    }
    return $nombre;
}

function obtenerCategoria(PDO $db, int $id)
{
    $stmt = $db->prepare('
        SELECT c.id, c.nombre, c.fecha_creacion, COUNT(t.id) AS total_tips
        FROM categorias c LEFT JOIN tips t ON t.categoria_id = c.id
        WHERE c.id = :id
        GROUP BY c.id, c.nombre, c.fecha_creacion
    ');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}
