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
|- AGENTS.md
|- api/config.php
|- api/categorias.php
|- api/tips.php
|- api/test_conexion.php
|- css/style.css
|- fonts/openSans.ttf
|- images/code.ico
|- index.html
|- js/app.js
|- js/libreria.js
|- js/script.js
|- migrations/001_add_categorias.sql
|- README.md
```

Nota de transicion: el nombre funcional del producto pasa a `PIA (Personal Information Admin)`, manteniendo BuscaTips como modulo interno de tips durante la migracion.

## 3) Arquitectura y flujo

Flujo de datos general:

1. Usuario aterriza en `Home` (PIA) en `index.html`
2. `js/app.js` maneja rutas hash (`#home`, `#tips`, `#drive`)
3. Al entrar a Tips, `js/script.js` orquesta UI de tips y usa `js/libreria.js`
4. `js/libreria.js` consume `api/tips.php` via `fetch`
5. `api/tips.php` enruta por metodo HTTP y usa `api/config.php`
6. `api/config.php` abre conexion PDO y responde JSON estandarizado
7. MySQL persiste las tablas `tips` y `categorias`
8. Frontend renderiza Markdown con `marked` desde CDN

## 4) Frontend (detalle operativo)

### 4.1 `index.html`

- Define 3 pantallas en la misma SPA:
  - `#home-screen` (modulos PIA)
  - `#app` (modulo Tips heredado)
  - `#drive-screen` (placeholder Drive Fase 1)
- Fondo visual global oscuro minimalista
- Layout Tips conserva `#sidebar` y `#contenido`
- Buscador: input `#buscador`
- Filtro de categoría: select `#filtro-categoria`; combina categoría y texto con criterio AND
- Cabecera de resultados: muestra categoría activa, contador total de esa categoría y control para colapsar o expandir su lista
- Tabla de resultados: `#resultados-body`
- Boton crear: `#btn-crear-tip` (desktop)
- Toggle mobile de resultados: `#btn-toggle-resultados`
- Carga `marked.min.js` por CDN y `js/app.js` como modulo principal

### 4.2 `js/app.js` (shell PIA + router)

Responsabilidades principales:

- Gestionar rutas hash (`#home`, `#tips`, `#drive`)
- Mostrar/ocultar pantallas via clase `screen-hidden`
- Conectar botones de modulos (`Tips`, `Drive`) y retorno a Home
- Cargar modulo Tips de forma diferida al entrar a `#tips`
- Importar `script.js` con el mismo parámetro de versión de recursos estáticos para invalidar caché tras deploy

### 4.3 `js/script.js` (orquestador Tips)

Correccion de inicializacion relevante:

- El modulo ahora inicializa tanto si se carga antes como despues de `DOMContentLoaded`
- Evita perdida de eventos/UI cuando `script.js` se importa de forma diferida desde `js/app.js`

Responsabilidades principales:

- Inicializar app cuando el modulo esta disponible (antes o despues de `DOMContentLoaded`)
- Importar `libreria.js` con el parámetro de versión compartido para invalidar caché tras deploy
- Cargar cache local con `cargarTips()`
- Gestionar busqueda con:
  - filtro local inmediato
  - debounce (400ms)
  - `AbortController` para cancelar requests obsoletos
  - control de version de busqueda (`searchVersion`)
- Abrir editor unificado para crear/editar
- Crear categorías desde el selector del editor mediante `+ Crear nueva categoria...`
- Manejar eliminacion con confirmacion
- Mostrar mensajes temporales de exito
- Mantener el estado de colapso de resultados por categoría durante la sesión

Eventos custom usados:

- `activarEdicion`
- `activarEliminacion`

### 4.4 `js/libreria.js` (servicios + render Tips)

Responsabilidades principales:

- Cache en memoria: `tipsData`
- CRUD API:
  - `cargarTips`
  - `buscarTipsAPI`
  - `crearTip`
  - `editarTip`
  - `eliminarTip`
- Categorías API: `cargarCategorias`, `crearCategoria`
- Filtrado local por nombre (normalizado sin acentos)
- Filtrado local por categoría: `filtrarPorCategoria`
- Re-filtrado de resultados API por nombre: `filtrarResultadosAPI`
- Render tabla resultados: `renderizarTabla`
- Mostrar contenido renderizado en `#contenido`

Comportamiento clave:

- El sidebar muestra coincidencias por `nombre`
- La API puede buscar por `nombre` o `contenido`; por eso el frontend refiltra para evitar falsos positivos visibles
- Con el buscador vacío, el sidebar lista todos los tips; el filtro de categoría reduce ese catálogo

### 4.5 `css/style.css`

- Tema oscuro minimalista para PIA (home + tips + drive placeholder)
- Estilos de Home con tarjetas de modulo e iconografia SVG
- Desktop: panel lateral responsivo (`clamp(400px, 35vw, 520px)`) + visor flexible, para priorizar titulos de tips y acciones visibles
- Acciones de editar y eliminar visibles permanentemente al final de cada resultado de tip
- Titulo `PIA Tips` centrado en el panel lateral
- Se carga desde `index.html` con parámetro de versión para evitar CSS desactualizado en caché
- Mobile (`max-width: 768px`):
  - Home colapsa tarjetas en una columna
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

Categorias:

- `GET /api/categorias.php` -> listar categorías
- `POST /api/categorias.php` -> crear categoría (`nombre` requerido, max 100)

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
- `categoria_id` INT nullable FK a `categorias.id` (`ON DELETE SET NULL`)
- `fecha_creacion` DATETIME
- `fecha_modificacion` DATETIME (con update automatico)

Charset/collation esperados: `utf8mb4` / `utf8mb4_unicode_ci`.

Tabla `categorias`:

- `id` INT auto_increment PK
- `nombre` VARCHAR(100) unico
- `fecha_creacion` DATETIME

Migracion para instalaciones existentes: ejecutar una sola vez `migrations/001_add_categorias.sql` antes de desplegar la funcionalidad.

## 7) Despliegue

Archivo: `.github/workflows/deploy.yml`

- Trigger: push a `main`
- Accion: `SamKirkland/FTP-Deploy-Action@v4.3.5`
- Secrets requeridos:
  - `FTP_SERVER`
  - `FTP_USERNAME`
  - `FTP_PASSWORD`

Destino actual configurado: `digitalbrain.girabienes.com/`

Recursos estáticos: `index.html`, `js/app.js` y `js/script.js` comparten un parámetro `v=` en sus importaciones de CSS/JS. Incrementarlo en los tres archivos cuando un cambio frontend deba invalidar la caché del navegador.

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
- Las categorías son únicas por nombre y cada tip tiene como máximo una categoría opcional.
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
- 2026-06-21: Fase 1 de PIA: se agrego `js/app.js`, Home con modulos `Tips/Drive`, rutas hash, fondo matrix con silueta hacker y placeholder de Drive; Tips queda operativo como modulo interno.
- 2026-06-21: Ajuste visual Home Fase 1: titulo principal cambiado a `SmarTekSoft PIA`, subtitulo `Personal Information Admin` centrado, y aumento de tamano de iconos en tarjetas de modulos.
- 2026-06-21: Refinamiento visual Home Fase 1: iconos de modulos aumentados a 44px y efecto glow en hover para mejorar legibilidad y foco visual.
- 2026-06-21: Microinteracciones Home Fase 1: hover de tarjetas reforzado (elevacion, fondo y sombra) y pulso sutil de iconos con glow para feedback visual mas claro.
- 2026-06-21: Ajuste de fondo Home Fase 1: overlay matrix uniformado para eliminar exceso de luz lateral derecha y equilibrar el contraste general.
- 2026-06-21: Ajuste de contraste matrix Home Fase 1: se redujo opacidad del overlay y se incremento brillo/estela de caracteres para mejorar visibilidad del efecto sin perder uniformidad.
- 2026-06-21: Correccion de carga Home Fase 1: el modulo Tips se paso a importacion diferida en `js/app.js` para evitar bloquear la inicializacion del fondo matrix cuando exista un error en el modulo de Tips.
- 2026-06-21: Simplificacion visual Home Fase 1: se removio fondo matrix/silueta y se adopto fondo oscuro minimalista para mayor estabilidad visual y tecnica.
- 2026-06-21: Correccion funcional Tips Fase 1: `js/script.js` ahora soporta inicializacion post `DOMContentLoaded` para que busqueda y CRUD funcionen correctamente al entrar desde Home con importacion diferida.
- 2026-08-07: Layout desktop de Tips: el panel lateral pasa a ancho responsivo limitado (`400px` a `520px`) para dar mas espacio a titulos y acciones de cada tip; el visor usa el espacio restante.
- 2026-08-07: Acciones de Tips: los iconos de editar y eliminar quedan visibles permanentemente en cada resultado del panel lateral.
- 2026-08-07: Interfaz Tips: se centro el titulo `PIA Tips` del panel lateral.
- 2026-08-08: Categorías de Tips: se agregan migración SQL, API de categorías, asignación opcional por tip, creación desde el editor y filtrado combinado por categoría y texto.
- 2026-08-08: Caché de frontend: se agrega versionado manual compartido a CSS y módulos JavaScript para forzar la carga de recursos actualizados tras cada deploy.
- 2026-08-08: Resultados por categoría: se agregan contadores en el filtro y una cabecera con control de expandir o colapsar la lista por categoría; se completa su inclusión en HTML y JavaScript tras detectar un despliegue incompleto.
