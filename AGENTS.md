# AGENTS.md - Contexto operativo de BuscaTips

Este documento es la base de contexto para cualquier agente (humano o IA) que trabaje en este repositorio.

Regla obligatoria: **cada vez que se modifique el proyecto, se debe actualizar este archivo en el mismo cambio** (codigo, docs, infra, estructura, flujo, API o dependencias).

## 1) Objetivo del proyecto

BuscaTips es una aplicacion web para guardar, buscar y administrar tips tecnicos en formato Markdown.

- Frontend SPA ligera en HTML/CSS/JS (modulos ES6)
- Backend PHP con API REST
- Persistencia en MySQL (tabla `tips`)

Casos principales:

- Buscar tips por texto en tiempo real
- Ver tip renderizado (Markdown -> HTML)
- Crear/editar/eliminar tips (CRUD)
- Uso responsive: en mobile se prioriza consulta (sin acciones de edicion)

## 2) Estado actual del repositorio (snapshot real)

Estructura detectada actualmente:

```text
BuscaTips/
|- .github/workflows/deploy.yml
|- api/config.php
|- api/tips.php
|- api/test_conexion.php
|- css/style.css
|- fonts/openSans.ttf
|- images/code.ico
|- index.html
|- js/libreria.js
|- js/script.js
|- README.md
```

Nota: el `README.md` menciona archivos que no aparecen en este snapshot (`css/fonts.css`, `test_api.html`, `tips.json`). Si se agregan o se eliminan oficialmente, actualizar `README.md` y este `AGENTS.md` para mantener consistencia.

## 3) Arquitectura y flujo

Flujo de datos general:

1. Usuario interactua con `index.html`
2. `js/script.js` orquesta eventos de UI y llama a `js/libreria.js`
3. `js/libreria.js` consume `api/tips.php` via `fetch`
4. `api/tips.php` enruta por metodo HTTP y usa `api/config.php`
5. `api/config.php` abre conexion PDO y responde JSON estandarizado
6. MySQL persiste la tabla `tips`
7. Frontend renderiza Markdown con `marked` desde CDN

## 4) Frontend (detalle operativo)

### 4.1 `index.html`

- Layout principal con `#sidebar` y `#contenido`
- Buscador: input `#buscador`
- Tabla de resultados: `#resultados-body`
- Boton crear: `#btn-crear-tip` (desktop)
- Toggle mobile de resultados: `#btn-toggle-resultados`
- Carga `marked.min.js` por CDN y luego `js/script.js` como modulo

### 4.2 `js/script.js` (orquestador)

Responsabilidades principales:

- Inicializar app en `DOMContentLoaded`
- Cargar cache local con `cargarTips()`
- Gestionar busqueda con:
  - filtro local inmediato
  - debounce (400ms)
  - `AbortController` para cancelar requests obsoletos
  - control de version de busqueda (`searchVersion`)
- Abrir editor unificado para crear/editar
- Manejar eliminacion con confirmacion
- Mostrar mensajes temporales de exito

Eventos custom usados:

- `activarEdicion`
- `activarEliminacion`

### 4.3 `js/libreria.js` (servicios + render)

Responsabilidades principales:

- Cache en memoria: `tipsData`
- CRUD API:
  - `cargarTips`
  - `buscarTipsAPI`
  - `crearTip`
  - `editarTip`
  - `eliminarTip`
- Filtrado local por nombre (normalizado sin acentos)
- Re-filtrado de resultados API por nombre: `filtrarResultadosAPI`
- Render tabla resultados: `renderizarTabla`
- Mostrar contenido renderizado en `#contenido`

Comportamiento clave:

- El sidebar muestra coincidencias por `nombre`
- La API puede buscar por `nombre` o `contenido`; por eso el frontend refiltra para evitar falsos positivos visibles

### 4.4 `css/style.css`

- Tema oscuro basado en tonos azul/petroleo
- Desktop: panel lateral fijo + visor
- Mobile (`max-width: 768px`):
  - acciones desktop ocultas
  - resultados colapsables
  - contenido con alto calculado

## 5) Backend/API (detalle operativo)

### 5.1 `api/config.php`

- Define entorno con `ENTORNO` (`local` o `produccion`)
- Define credenciales DB por entorno
- Provee:
  - `obtenerConexion(): PDO`
  - `responderJSON(int $codigoHttp, bool $exito, $datos = null, string $mensaje = '')`

### 5.2 `api/tips.php`

Headers y CORS:

- `Access-Control-Allow-Origin: *`
- metodos permitidos: `GET, POST, PUT, DELETE, OPTIONS`

Endpoints:

- `GET /api/tips.php` -> listar todos
- `GET /api/tips.php?id={id}` -> obtener por ID
- `GET /api/tips.php?buscar={texto}` -> buscar por nombre o contenido (SQL LIKE)
- `POST /api/tips.php` -> crear
- `PUT /api/tips.php?id={id}` -> editar
- `DELETE /api/tips.php?id={id}` -> eliminar

Formato de respuesta:

```json
{
  "success": true,
  "message": "...",
  "data": {}
}
```

Validaciones relevantes:

- `id` entero positivo
- `nombre` requerido, max 255 en creacion
- `contenido` requerido en creacion
- en `PUT`, al menos un campo para actualizar

### 5.3 `api/test_conexion.php`

- Script rapido para verificar conexion PDO y devolver JSON

## 6) Datos y modelo

Tabla esperada: `tips`

- `id` INT auto_increment PK
- `nombre` VARCHAR(255)
- `contenido` TEXT
- `fecha_creacion` DATETIME
- `fecha_modificacion` DATETIME (con update automatico)

Charset/collation esperados: `utf8mb4` / `utf8mb4_unicode_ci`.

## 7) Despliegue

Archivo: `.github/workflows/deploy.yml`

- Trigger: push a `main`
- Accion: `SamKirkland/FTP-Deploy-Action@v4.3.5`
- Secrets requeridos:
  - `FTP_SERVER`
  - `FTP_USERNAME`
  - `FTP_PASSWORD`

Destino actual configurado: `digitalbrain.girabienes.com/`

## 8) Riesgos y deuda tecnica detectada

1. Seguridad: hay credenciales de BD hardcodeadas en `api/config.php`. Deben migrarse a variables de entorno y rotarse si fueron expuestas.
2. CORS abierto (`*`) en API; revisar si debe restringirse en produccion.
3. Desfase documental entre `README.md` y archivos reales existentes.
4. No hay suite automatizada de tests en el repo.

## 9) Convenciones para futuros cambios

- Mantener respuestas API con shape consistente (`success`, `message`, `data`).
- Mantener separacion de responsabilidades:
  - `script.js`: UI/orquestacion
  - `libreria.js`: datos/API/render de lista
- Si cambia el criterio de busqueda en backend, reflejarlo tambien en el refiltrado frontend.
- Si se agregan nuevos endpoints, documentarlos en `README.md` y en este archivo.
- Si se agregan archivos o carpetas, actualizar snapshot de estructura.

## 10) Protocolo obligatorio de actualizacion de AGENTS.md

Aplicar siempre que se modifique cualquier parte del proyecto.

Checklist minimo por cambio:

1. Actualizar secciones afectadas (arquitectura, estructura, API, deploy, riesgos, convenciones).
2. Verificar que rutas y nombres de archivo sean reales.
3. Agregar entrada en el historial de abajo.
4. Confirmar que no haya contradicciones con `README.md`.

## 11) Historial de actualizaciones de este archivo

- 2026-06-21: Creacion inicial de `AGENTS.md` con analisis completo del estado actual del proyecto.
- 2026-06-21: Alineacion de `README.md` con el estado real del repo (se removieron referencias a `css/fonts.css`, `test_api.html` y `tips.json`).
