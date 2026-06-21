# 🔎 BuscaTips

> **Biblioteca personal de tips técnicos** con editor Markdown, búsqueda en tiempo real y API REST.  
> Consulta, crea, edita y elimina tips desde cualquier dispositivo.

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/Licencia-MIT-green)
![Deploy](https://img.shields.io/badge/Deploy-GitHub%20Actions-2088FF?logo=githubactions&logoColor=white)

---

## 📌 Tabla de Contenido

- [🎯 Descripción](#-descripción)
- [✨ Características](#-características)
- [🧱 Tecnologías](#-tecnologías)
- [🏗️ Arquitectura](#️-arquitectura)
- [📁 Estructura del Proyecto](#-estructura-del-proyecto)
- [⚙️ Instalación y Configuración](#️-instalación-y-configuración)
- [🚀 Uso](#-uso)
- [📡 API REST](#-api-rest)
- [🌐 Despliegue](#-despliegue)
- [📸 Capturas de Pantalla](#-capturas-de-pantalla)
- [🤝 Contribución](#-contribución)
- [📄 Licencia](#-licencia)

---

## 🎯 Descripción

**BuscaTips** es una aplicación web para centralizar tips técnicos (comandos, procedimientos, snippets de código) escritos en **Markdown**. Permite crear, buscar, editar y eliminar tips desde una interfaz limpia y responsiva, con un panel lateral de búsqueda en tiempo real y un visor principal estilo blog.

### ✅ Qué resuelve

| Problema | Solución |
|----------|----------|
| Tips dispersos en múltiples notas y archivos | Centralización en una sola base de datos |
| Dificultad para encontrar información rápidamente | Búsqueda en tiempo real con resaltado de coincidencias |
| Necesidad de consultar desde cualquier lugar | App web responsiva accesible desde cualquier dispositivo |
| Edición compleja de contenido técnico | Editor Markdown integrado con vista previa |

---

## ✨ Características

### 🔍 Búsqueda en Tiempo Real
- Resultados dinámicos a medida que escribes
- Resaltado de coincidencias en los títulos
- Búsqueda por nombre y contenido del tip
- Ordenamiento alfabético de resultados

### 📝 CRUD Completo
- **Crear** tips con editor Markdown integrado
- **Leer** tips renderizados como HTML con formato estilo blog
- **Editar** tips existentes con previsualización
- **Eliminar** tips con confirmación

### ✍️ Editor Markdown
- Escritura en Markdown con preview en tiempo real
- Soporte para bloques de código, listas, encabezados, negritas, etc.
- Renderizado con [Marked.js](https://marked.js.org/)

### 📱 Diseño Responsivo
- **Desktop**: layout de dos paneles (sidebar + visor principal)
- **Mobile**: interfaz optimizada para consulta con panel deslizante de resultados
- Creación y edición solo habilitados en desktop

### 🔄 Ordenamiento Automático
- Tips listados alfabéticamente (case-insensitive)
- Carga automática de todos los tips al iniciar

---

## 🧱 Tecnologías

### Frontend
| Tecnología | Uso |
|------------|-----|
| **HTML5** | Estructura semántica de la aplicación |
| **CSS3** | Estilos, tema oscuro, diseño responsivo |
| **JavaScript ES6+** | Lógica de la app con módulos ES6 (`import`/`export`) |
| **Marked.js** | Parser de Markdown a HTML (vía CDN) |

### Backend
| Tecnología | Uso |
|------------|-----|
| **PHP 7.4+** | API REST con manejo de rutas por método HTTP |
| **PDO** | Conexión segura a MySQL con prepared statements |

### Base de Datos
| Tecnología | Uso |
|------------|-----|
| **MySQL 5.7+** | Almacenamiento persistente de tips |

### DevOps
| Tecnología | Uso |
|------------|-----|
| **Git / GitHub** | Control de versiones |
| **GitHub Actions** | Despliegue continuo (CD) vía FTP |

---

## 🏗️ Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│                        FRONTEND                             │
│  ┌──────────┐   ┌──────────┐   ┌──────────────────────┐    │
│  │ index.html│   │ style.css│   │ script.js + libreria │    │
│  │ (Vista)  │   │ (Estilos)│   │ (Lógica + Módulos)   │    │
│  └──────────┘   └──────────┘   └──────────┬───────────┘    │
│                                            │                │
│                            fetch() / API REST               │
│                                            │                │
├────────────────────────────────────────────┼────────────────┤
│                        BACKEND             │                │
│                    ┌───────────┐           │                │
│                    │  tips.php │◄──────────┘                │
│                    │ (Router)  │                             │
│                    └─────┬─────┘                            │
│                          │                                  │
│                    ┌─────┴─────┐                            │
│                    │ config.php│                             │
│                    │   (PDO)   │                             │
│                    └─────┬─────┘                            │
│                          │                                  │
├──────────────────────────┼──────────────────────────────────┤
│                     BASE DE DATOS                           │
│                    ┌─────┴─────┐                            │
│                    │   MySQL   │                             │
│                    │   tips    │                             │
│                    └───────────┘                            │
└─────────────────────────────────────────────────────────────┘
```

### Flujo de Datos

1. El **usuario** interactúa con la interfaz (`index.html`)
2. **JavaScript** (`script.js` + `libreria.js`) envía peticiones `fetch()` a la API
3. **PHP** (`tips.php`) procesa la petición y consulta/modifica MySQL vía **PDO**
4. La **API** retorna respuestas JSON estandarizadas
5. El **frontend** renderiza los datos (Markdown → HTML con Marked.js)

---

## 📁 Estructura del Proyecto

```
BuscaTips/
├── .github/
│   └── workflows/
│       └── deploy.yml          # 🚀 GitHub Actions - Despliegue automático por FTP
├── api/
│   ├── config.php              # ⚙️ Configuración de BD y funciones helper (PDO, JSON response)
│   ├── tips.php                # 📡 API REST - Endpoints CRUD para tips
│   └── test_conexion.php       # 🧪 Script para verificar conexión a la base de datos
├── css/
│   └── style.css               # 🎨 Estilos principales (tema oscuro, responsivo)
├── fonts/
│   └── openSans.ttf            # 🔤 Fuente Open Sans embebida
├── images/
│   └── code.ico                # 🖼️ Favicon de la aplicación
├── js/
│   ├── libreria.js             # 📚 Módulo principal - API calls, cache, búsqueda, renderizado
│   └── script.js               # 🎮 Orquestador de UI - Eventos, DOM, flujo de la app
├── index.html                  # 🏠 Página principal (SPA)
└── README.md                   # 📖 Este archivo
```

---

## ⚙️ Instalación y Configuración

### Requisitos Previos

- **Servidor web** con soporte PHP (Apache, Nginx, XAMPP, Laragon, etc.)
- **PHP 7.4** o superior con extensión PDO habilitada
- **MySQL 5.7** o superior
- **Git** (opcional, para clonar el repositorio)

### 1. Clonar el Repositorio

```bash
git clone https://github.com/tu-usuario/BuscaTips.git
cd BuscaTips
```

### 2. Crear la Base de Datos

Ejecuta el siguiente SQL en MySQL (via phpMyAdmin, CLI o tu herramienta favorita):

```sql
-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS tucultur_buscatips_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tucultur_buscatips_db;

-- Crear la tabla de tips
CREATE TABLE IF NOT EXISTS tips (
  id                INT(11)       NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(255)  NOT NULL COLLATE utf8mb4_unicode_ci,
  contenido         TEXT          NOT NULL COLLATE utf8mb4_unicode_ci,
  fecha_creacion    DATETIME      DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. Configurar Credenciales

Edita el archivo `api/config.php` con tus credenciales:

```php
// ─── CONFIGURACIÓN DE ENTORNO ─────────────────────────────────
define('ENTORNO', 'local');  // Cambia a 'produccion' en hosting

// ─── CREDENCIALES ─────────────────────────────────────────────
if (ENTORNO === 'local') {
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'tucultur_buscatips_db');
    define('DB_USER', 'root');           // Tu usuario MySQL local
    define('DB_PASS', '');               // Tu contraseña MySQL local
} else {
    define('DB_HOST', 'localhost');       // En hosting compartido suele ser localhost
    define('DB_PORT', '3306');
    define('DB_NAME', 'tu_base_de_datos');
    define('DB_USER', 'tu_usuario');
    define('DB_PASS', 'tu_contraseña');
}
```

> ⚠️ **Seguridad**: Nunca subas credenciales reales al repositorio. Considera usar variables de entorno en producción.

### 4. Verificar la Conexión

Abre en el navegador:

```
http://localhost/BuscaTips/api/test_conexion.php
```

Si la conexión es exitosa, verás un mensaje de confirmación.

### 5. Abrir la Aplicación

```
http://localhost/BuscaTips/
```

---

## 🚀 Uso

### Crear un Tip
1. Haz clic en **"+ Crear Tip"** (solo disponible en desktop)
2. Escribe el **nombre** del tip (título)
3. Escribe el **contenido** en formato Markdown
4. Opcionalmente, haz clic en **"Vista Previa"** para ver cómo se renderizará
5. Haz clic en **"Guardar .md"** para guardarlo en la base de datos

### Buscar Tips
1. Escribe en el campo **"Buscar tips..."** del panel lateral
2. Los resultados aparecen en tiempo real con las coincidencias resaltadas
3. Haz clic en un resultado para ver el contenido completo en el panel principal

### Editar un Tip
1. Abre el tip que deseas editar
2. Haz clic en **"✏️ Editar este Tip"**
3. Modifica el nombre y/o contenido
4. Guarda los cambios

### Eliminar un Tip
1. Abre el tip que deseas eliminar
2. Haz clic en el botón de **eliminar** (🗑️)
3. Confirma la eliminación

---

## 📡 API REST

Base URL: `/api/tips.php`

Todas las respuestas siguen el formato:

```json
{
  "success": true,
  "data": [...],
  "message": "Descripción del resultado"
}
```

### Endpoints

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/tips.php` | Listar todos los tips |
| `GET` | `/api/tips.php?id={id}` | Obtener un tip por ID |
| `GET` | `/api/tips.php?buscar={texto}` | Buscar tips por nombre o contenido |
| `POST` | `/api/tips.php` | Crear un nuevo tip |
| `PUT` | `/api/tips.php?id={id}` | Editar un tip existente |
| `DELETE` | `/api/tips.php?id={id}` | Eliminar un tip |

---

#### 📋 `GET` — Listar todos los tips

```bash
curl -X GET http://localhost/BuscaTips/api/tips.php
```

**Respuesta** `200 OK`:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nombre": "Git Básico - Comandos Esenciales",
      "contenido": "# Git Básico\n## Comandos Principales\n...",
      "fecha_creacion": "2026-03-15 10:30:00",
      "fecha_modificacion": "2026-03-15 10:30:00"
    }
  ],
  "message": "1 tip(s) en total."
}
```

---

#### 🔍 `GET` — Buscar tips

```bash
curl -X GET "http://localhost/BuscaTips/api/tips.php?buscar=array"
```

**Respuesta** `200 OK`:
```json
{
  "success": true,
  "data": [
    {
      "id": 3,
      "nombre": "Buscar en array con Find",
      "contenido": "...",
      "fecha_creacion": "2026-03-14 09:00:00",
      "fecha_modificacion": "2026-03-14 09:00:00"
    }
  ],
  "message": "1 tip(s) encontrado(s)."
}
```

---

#### 🔎 `GET` — Obtener tip por ID

```bash
curl -X GET "http://localhost/BuscaTips/api/tips.php?id=1"
```

**Respuesta** `200 OK`:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "nombre": "Git Básico - Comandos Esenciales",
    "contenido": "# Git Básico\n## Comandos Principales\n...",
    "fecha_creacion": "2026-03-15 10:30:00",
    "fecha_modificacion": "2026-03-15 10:30:00"
  }
}
```

---

#### ➕ `POST` — Crear un tip

```bash
curl -X POST http://localhost/BuscaTips/api/tips.php \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Nuevo Tip de CSS",
    "contenido": "# CSS Grid\n\nUsa `display: grid` para layouts..."
  }'
```

**Respuesta** `201 Created`:
```json
{
  "success": true,
  "data": {
    "id": 5,
    "nombre": "Nuevo Tip de CSS",
    "contenido": "# CSS Grid\n\nUsa `display: grid` para layouts...",
    "fecha_creacion": "2026-03-18 14:00:00",
    "fecha_modificacion": "2026-03-18 14:00:00"
  },
  "message": "Tip creado exitosamente."
}
```

---

#### ✏️ `PUT` — Editar un tip

```bash
curl -X PUT "http://localhost/BuscaTips/api/tips.php?id=5" \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "CSS Grid - Guía Completa",
    "contenido": "# CSS Grid\n\n## Propiedades principales\n..."
  }'
```

**Respuesta** `200 OK`:
```json
{
  "success": true,
  "data": {
    "id": 5,
    "nombre": "CSS Grid - Guía Completa",
    "contenido": "# CSS Grid\n\n## Propiedades principales\n...",
    "fecha_creacion": "2026-03-18 14:00:00",
    "fecha_modificacion": "2026-03-18 14:05:00"
  },
  "message": "Tip actualizado exitosamente."
}
```

---

#### 🗑️ `DELETE` — Eliminar un tip

```bash
curl -X DELETE "http://localhost/BuscaTips/api/tips.php?id=5"
```

**Respuesta** `200 OK`:
```json
{
  "success": true,
  "data": {
    "id": 5,
    "nombre": "CSS Grid - Guía Completa"
  },
  "message": "Tip 'CSS Grid - Guía Completa' eliminado exitosamente."
}
```

---

### Códigos de Error

| Código | Significado | Ejemplo |
|--------|-------------|---------|
| `400` | Bad Request | Campos requeridos faltantes, JSON inválido |
| `404` | Not Found | Tip con el ID especificado no existe |
| `405` | Method Not Allowed | Método HTTP no soportado |
| `500` | Internal Server Error | Error de conexión a la base de datos |

---

## 🌐 Despliegue

### Despliegue Automático con GitHub Actions

El proyecto incluye un workflow de GitHub Actions (`.github/workflows/deploy.yml`) que despliega automáticamente al hacer `push` a la rama `main` mediante FTP.

#### Configurar Secrets en GitHub

Ve a **Settings → Secrets and variables → Actions** y agrega:

| Secret | Descripción |
|--------|-------------|
| `FTP_SERVER` | Dirección del servidor FTP (ej: `ftp.tudominio.com`) |
| `FTP_USERNAME` | Usuario FTP |
| `FTP_PASSWORD` | Contraseña FTP |

#### Flujo de Despliegue

```
git add .
git commit -m "feat: nueva funcionalidad"
git push origin main
```

GitHub Actions se encargará de sincronizar los archivos al servidor automáticamente. ✅

### Despliegue Manual

1. Sube todos los archivos al servidor vía FTP/SFTP
2. Asegúrate de que `api/config.php` tenga las credenciales de producción
3. Cambia `define('ENTORNO', 'local')` a `define('ENTORNO', 'produccion')`
4. Verifica la conexión con `api/test_conexion.php`

---

## 📸 Capturas de Pantalla

### Vista Principal (Desktop)
> Interfaz con sidebar de búsqueda y visor de contenido Markdown.

### Búsqueda en Tiempo Real
> Resultados dinámicos con coincidencias resaltadas al escribir.

### Editor de Tips (Crear/Editar)
> Editor Markdown con campos de nombre y contenido, botones de Vista Previa y Guardar.

### Vista Mobile
> Interfaz optimizada para consulta en dispositivos móviles.

## 🤝 Contribución

Las contribuciones son bienvenidas. Para contribuir:

1. Haz un **Fork** del repositorio
2. Crea una rama para tu feature:
   ```bash
   git checkout -b feature/nueva-funcionalidad
   ```
3. Realiza tus cambios y haz commit:
   ```bash
   git commit -m "feat: descripción del cambio"
   ```
4. Sube tu rama:
   ```bash
   git push origin feature/nueva-funcionalidad
   ```
5. Abre un **Pull Request**

### Convención de Commits

| Prefijo | Uso |
|---------|-----|
| `feat:` | Nueva funcionalidad |
| `fix:` | Corrección de bug |
| `docs:` | Cambios en documentación |
| `style:` | Cambios de formato (no afectan lógica) |
| `refactor:` | Refactorización de código |

---

## 📄 Licencia

Este proyecto está bajo la licencia **MIT**. Consulta el archivo [LICENSE](LICENSE) para más detalles.

---

<p align="center">
  Hecho con ❤️ por <strong>TuCultur</strong> · 2026
</p>
