<?php

define('ENTORNO', getenv('APP_ENV') ?: 'local');
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

date_default_timezone_set('America/Bogota');

function obtenerConexion(): PDO
{
  static $conexion = null;

  if ($conexion === null) {
    if (DB_NAME === '' || DB_USER === '' || DB_PASS === '') {
      responderJSON(500, false, null, 'La configuracion de base de datos no esta completa.');
    }

    $dsn = sprintf(
      'mysql:host=%s;port=%s;dbname=%s;charset=%s',
      DB_HOST,
      DB_PORT,
      DB_NAME,
      DB_CHARSET
    );

    $opciones = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
      $conexion = new PDO($dsn, DB_USER, DB_PASS, $opciones);
    } catch (PDOException $e) {
      error_log('BuscaTips DB Error: ' . $e->getMessage());
      responderJSON(500, false, null, 'Error interno del servidor.');
    }
  }

  return $conexion;
}

function responderJSON(int $codigoHttp, bool $exito, $datos = null, string $mensaje = ''): void
{
  http_response_code($codigoHttp);
  header('Content-Type: application/json; charset=utf-8');

  $respuesta = ['success' => $exito];

  if ($mensaje !== '') {
    $respuesta['message'] = $mensaje;
  }

  if ($datos !== null) {
    $respuesta['data'] = $datos;
  }

  echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
