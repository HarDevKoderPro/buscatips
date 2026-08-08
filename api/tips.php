<?php
/**
 * ============================================================
 * BuscaTips - API REST de Tips
 * ============================================================
 * 
 * Endpoints disponibles:
 * 
 *   GET    /api/tips.php              → Listar todos los tips
 *   GET    /api/tips.php?buscar=texto&categoria_id=X → Buscar por texto y categoría
 *   GET    /api/tips.php?id=X         → Obtener un tip específico
 *   POST   /api/tips.php              → Crear un nuevo tip
 *   PUT    /api/tips.php?id=X         → Editar un tip existente
 *   DELETE /api/tips.php?id=X         → Eliminar un tip
 * 
 * Formato de respuesta:
 *   { "success": true/false, "data": [...], "message": "..." }
 * 
 * ============================================================
 */

// ─── INCLUIR CONFIGURACIÓN ────────────────────────────────────
require_once __DIR__ . '/config.php';

// ─── HEADERS CORS ─────────────────────────────────────────────
// Permitir peticiones desde cualquier origen (ajustar en producción)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Manejar peticiones preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ─── ROUTER PRINCIPAL ─────────────────────────────────────────
$metodo = $_SERVER['REQUEST_METHOD'];

try {
    switch ($metodo) {
        case 'GET':
            manejarGET();
            break;

        case 'POST':
            manejarPOST();
            break;

        case 'PUT':
            manejarPUT();
            break;

        case 'DELETE':
            manejarDELETE();
            break;

        default:
            responderJSON(405, false, null, "Método $metodo no permitido.");
    }
} catch (PDOException $e) {
    // Error de base de datos no capturado
    if (ENTORNO === 'local') {
        responderJSON(500, false, null, 'Error de base de datos: ' . $e->getMessage());
    } else {
        error_log('BuscaTips API Error: ' . $e->getMessage());
        responderJSON(500, false, null, 'Error interno del servidor.');
    }
} catch (Exception $e) {
    responderJSON(500, false, null, 'Error inesperado: ' . $e->getMessage());
}


// ═══════════════════════════════════════════════════════════════
//  FUNCIONES DE MANEJO POR MÉTODO HTTP
// ═══════════════════════════════════════════════════════════════

/**
 * GET - Listar tips, buscar, u obtener uno por ID
 * 
 * Ejemplos:
 *   GET /api/tips.php              → Todos los tips
 *   GET /api/tips.php?id=5         → Tip con id=5
 *   GET /api/tips.php?buscar=react → Tips que contengan "react"
 */
function manejarGET(): void
{
    $db = obtenerConexion();

    // ── Caso 1: Obtener tip por ID ──
    if (isset($_GET['id'])) {
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

        if ($id === false || $id <= 0) {
            responderJSON(400, false, null, 'El parámetro "id" debe ser un número entero positivo.');
        }

        $stmt = $db->prepare('
            SELECT t.id, t.nombre, t.contenido, t.categoria_id, c.nombre AS categoria_nombre,
                   t.fecha_creacion, t.fecha_modificacion
            FROM tips t LEFT JOIN categorias c ON c.id = t.categoria_id
            WHERE t.id = :id
        ');
        $stmt->execute([':id' => $id]);
        $tip = $stmt->fetch();

        if (!$tip) {
            responderJSON(404, false, null, "No se encontró el tip con id=$id.");
        }

        responderJSON(200, true, $tip);
    }

    $categoriaId = isset($_GET['categoria_id']) ? filter_var($_GET['categoria_id'], FILTER_VALIDATE_INT) : null;
    if (isset($_GET['categoria_id']) && ($categoriaId === false || $categoriaId <= 0)) {
        responderJSON(400, false, null, 'El parametro "categoria_id" debe ser un numero entero positivo.');
    }

    // ── Caso 2: Buscar tips por texto y/o categoria ──
    if (isset($_GET['buscar']) && trim($_GET['buscar']) !== '') {
        $termino = '%' . trim($_GET['buscar']) . '%';
        $sql = 'SELECT t.id, t.nombre, t.contenido, t.categoria_id, c.nombre AS categoria_nombre,
                       t.fecha_creacion, t.fecha_modificacion
                FROM tips t LEFT JOIN categorias c ON c.id = t.categoria_id
                WHERE (t.nombre LIKE :termino OR t.contenido LIKE :termino2)';
        $parametros = [
            ':termino'  => $termino,
            ':termino2' => $termino,
        ];
        if ($categoriaId !== null) {
            $sql .= ' AND t.categoria_id = :categoria_id';
            $parametros[':categoria_id'] = $categoriaId;
        }
        $stmt = $db->prepare($sql . ' ORDER BY t.fecha_modificacion DESC');
        $stmt->execute($parametros);
        $tips = $stmt->fetchAll();

        responderJSON(200, true, $tips, count($tips) . ' tip(s) encontrado(s).');
    }

    // ── Caso 3: Listar todos los tips ──
    $sql = 'SELECT t.id, t.nombre, t.contenido, t.categoria_id, c.nombre AS categoria_nombre,
                   t.fecha_creacion, t.fecha_modificacion
            FROM tips t LEFT JOIN categorias c ON c.id = t.categoria_id';
    if ($categoriaId !== null) {
        $stmt = $db->prepare($sql . ' WHERE t.categoria_id = :categoria_id ORDER BY t.fecha_modificacion DESC');
        $stmt->execute([':categoria_id' => $categoriaId]);
    } else {
        $stmt = $db->query($sql . ' ORDER BY t.fecha_modificacion DESC');
    }
    $tips = $stmt->fetchAll();

    responderJSON(200, true, $tips, count($tips) . ' tip(s) en total.');
}


/**
 * POST - Crear un nuevo tip
 * 
 * Body JSON esperado:
 *   { "nombre": "Título del tip", "contenido": "Contenido del tip", "categoria_id": 1 }
 * 
 * Respuesta exitosa: 201 Created
 */
function manejarPOST(): void
{
    $db = obtenerConexion();

    // Leer y decodificar el body JSON
    $bodyRaw = file_get_contents('php://input');
    $datos = json_decode($bodyRaw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        responderJSON(400, false, null, 'El cuerpo de la petición no es JSON válido.');
    }

    // Validar campos requeridos
    $errores = validarCamposTip($datos);
    if (!empty($errores)) {
        responderJSON(400, false, $errores, 'Errores de validación.');
    }

    $nombre    = trim($datos['nombre']);
    $contenido = trim($datos['contenido']);
    $categoriaId = obtenerCategoriaId($db, $datos);

    $stmt = $db->prepare('
        INSERT INTO tips (nombre, contenido, categoria_id, fecha_creacion, fecha_modificacion)
        VALUES (:nombre, :contenido, :categoria_id, NOW(), NOW())
    ');
    $stmt->execute([
        ':nombre'    => $nombre,
        ':contenido' => $contenido,
        ':categoria_id' => $categoriaId,
    ]);

    $nuevoId = (int) $db->lastInsertId();

    // Obtener el tip recién creado para devolverlo completo
    $stmt = $db->prepare('
        SELECT t.id, t.nombre, t.contenido, t.categoria_id, c.nombre AS categoria_nombre,
               t.fecha_creacion, t.fecha_modificacion
        FROM tips t LEFT JOIN categorias c ON c.id = t.categoria_id
        WHERE t.id = :id
    ');
    $stmt->execute([':id' => $nuevoId]);
    $tipCreado = $stmt->fetch();

    responderJSON(201, true, $tipCreado, 'Tip creado exitosamente.');
}


/**
 * PUT - Editar un tip existente
 * 
 * URL: /api/tips.php?id=X
 * Body JSON esperado:
 *   { "nombre": "Nuevo título", "contenido": "Nuevo contenido", "categoria_id": 1 }
 * 
 * Se pueden enviar ambos campos o solo uno de ellos.
 */
function manejarPUT(): void
{
    $db = obtenerConexion();

    // Validar que se proporcionó un ID
    if (!isset($_GET['id'])) {
        responderJSON(400, false, null, 'Debe proporcionar el parámetro "id" en la URL.');
    }

    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        responderJSON(400, false, null, 'El parámetro "id" debe ser un número entero positivo.');
    }

    // Verificar que el tip existe
    $stmt = $db->prepare('
        SELECT t.id, t.nombre, t.contenido, t.categoria_id, c.nombre AS categoria_nombre,
               t.fecha_creacion, t.fecha_modificacion
        FROM tips t LEFT JOIN categorias c ON c.id = t.categoria_id
        WHERE t.id = :id
    ');
    $stmt->execute([':id' => $id]);
    $tipExistente = $stmt->fetch();

    if (!$tipExistente) {
        responderJSON(404, false, null, "No se encontró el tip con id=$id.");
    }

    // Leer y decodificar el body JSON
    $bodyRaw = file_get_contents('php://input');
    $datos = json_decode($bodyRaw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        responderJSON(400, false, null, 'El cuerpo de la petición no es JSON válido.');
    }
    if (!is_array($datos)) {
        responderJSON(400, false, null, 'Los datos enviados no son válidos.');
    }

    // Validar que al menos un campo venga para actualizar
    $nombre    = isset($datos['nombre'])    ? trim($datos['nombre'])    : null;
    $contenido = isset($datos['contenido']) ? trim($datos['contenido']) : null;
    $categoriaId = obtenerCategoriaId($db, $datos);

    if ($nombre === null && $contenido === null && !array_key_exists('categoria_id', $datos)) {
        responderJSON(400, false, null, 'Debe enviar al menos "nombre", "contenido" o "categoria_id" para actualizar.');
    }

    // Validar campos que se envían
    if ($nombre !== null && $nombre === '') {
        responderJSON(400, false, null, 'El campo "nombre" no puede estar vacío.');
    }
    if ($contenido !== null && $contenido === '') {
        responderJSON(400, false, null, 'El campo "contenido" no puede estar vacío.');
    }

    // Construir la consulta dinámicamente según los campos proporcionados
    $campos = [];
    $parametros = [':id' => $id];

    if ($nombre !== null) {
        $campos[] = 'nombre = :nombre';
        $parametros[':nombre'] = $nombre;
    }
    if ($contenido !== null) {
        $campos[] = 'contenido = :contenido';
        $parametros[':contenido'] = $contenido;
    }
    $campos[] = 'categoria_id = :categoria_id';
    $parametros[':categoria_id'] = $categoriaId;

    // fecha_modificacion se actualiza automáticamente por ON UPDATE CURRENT_TIMESTAMP
    // pero lo forzamos por si acaso solo cambia un campo
    $campos[] = 'fecha_modificacion = NOW()';

    $sql = 'UPDATE tips SET ' . implode(', ', $campos) . ' WHERE id = :id';
    $stmt = $db->prepare($sql);
    $stmt->execute($parametros);

    // Devolver el tip actualizado
    $stmt = $db->prepare('SELECT * FROM tips WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $tipActualizado = $stmt->fetch();

    responderJSON(200, true, $tipActualizado, 'Tip actualizado exitosamente.');
}


/**
 * DELETE - Eliminar un tip
 * 
 * URL: /api/tips.php?id=X
 */
function manejarDELETE(): void
{
    $db = obtenerConexion();

    // Validar que se proporcionó un ID
    if (!isset($_GET['id'])) {
        responderJSON(400, false, null, 'Debe proporcionar el parámetro "id" en la URL.');
    }

    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        responderJSON(400, false, null, 'El parámetro "id" debe ser un número entero positivo.');
    }

    // Verificar que el tip existe antes de eliminarlo
    $stmt = $db->prepare('SELECT id, nombre FROM tips WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $tip = $stmt->fetch();

    if (!$tip) {
        responderJSON(404, false, null, "No se encontró el tip con id=$id.");
    }

    // Eliminar el tip
    $stmt = $db->prepare('DELETE FROM tips WHERE id = :id');
    $stmt->execute([':id' => $id]);

    responderJSON(200, true, $tip, "Tip '{$tip['nombre']}' eliminado exitosamente.");
}


// ═══════════════════════════════════════════════════════════════
//  FUNCIONES AUXILIARES
// ═══════════════════════════════════════════════════════════════

/**
 * Valida los campos requeridos para crear un tip.
 * 
 * @param  array|null $datos Datos decodificados del body JSON
 * @return array      Lista de errores (vacía si todo es válido)
 */
function validarCamposTip($datos): array
{
    $errores = [];

    if (!is_array($datos)) {
        return ['Los datos enviados no son válidos.'];
    }

    // Validar 'nombre'
    if (!isset($datos['nombre']) || trim($datos['nombre']) === '') {
        $errores[] = 'El campo "nombre" es obligatorio y no puede estar vacío.';
    } elseif (mb_strlen(trim($datos['nombre'])) > 255) {
        $errores[] = 'El campo "nombre" no puede exceder 255 caracteres.';
    }

    // Validar 'contenido'
    if (!isset($datos['contenido']) || trim($datos['contenido']) === '') {
        $errores[] = 'El campo "contenido" es obligatorio y no puede estar vacío.';
    }

    return $errores;
}

function obtenerCategoriaId(PDO $db, array $datos): int
{
    $valor = $datos['categoria_id'] ?? null;
    if ($valor === null || $valor === '') {
        responderJSON(400, false, null, 'Debe seleccionar una categoria para guardar el tip.');
    }

    $categoriaId = filter_var($valor, FILTER_VALIDATE_INT);
    if ($categoriaId === false || $categoriaId <= 0) {
        responderJSON(400, false, null, 'El campo "categoria_id" debe ser un entero positivo.');
    }
    $stmt = $db->prepare('SELECT id FROM categorias WHERE id = :id');
    $stmt->execute([':id' => $categoriaId]);
    if (!$stmt->fetch()) {
        responderJSON(400, false, null, 'La categoria seleccionada no existe.');
    }
    return $categoriaId;
}
