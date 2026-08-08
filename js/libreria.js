// ============================================================
// BuscaTips - Librería principal (conexión con API REST)
// ============================================================
// Maneja: carga, búsqueda, CRUD y renderizado de tips
// API Base: /api/tips.php
// ============================================================
// FIX: filtrarTips() ahora solo busca en 'nombre' (lo que el
//      usuario ve en el sidebar). Se añade filtrarResultadosAPI()
//      para re-filtrar respuestas del servidor antes de renderizar.
// ============================================================

// URL base de la API (ajustar si cambia la ubicación)
const API_URL = "api/tips.php";
const CATEGORIAS_API_URL = "api/categorias.php";

// Almacén local de tips cargados
let tipsData = [];

// Datos del tip actualmente visualizado (para edición en desktop)
let tipActualId = null;
let contenidoOriginalMD = "";
let nombreTipActual = "";

// Detección de mobile
const esMobile = () => window.matchMedia("(max-width: 768px)").matches;

// ─── FUNCIONES AUXILIARES ───────────────────────────────────────

/**
 * Normalizar texto: eliminar tildes/acentos y convertir a minúsculas
 * Permite que "Línea" y "linea" produzcan las mismas coincidencias
 * @param {string} texto
 * @returns {string} Texto normalizado sin acentos y en minúsculas
 */
function normalizarTexto(texto) {
  return texto.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
}

/**
 * Ordenar tips alfabéticamente por nombre (case-insensitive)
 * @param {Array} tips - Lista de tips a ordenar
 * @returns {Array} Tips ordenados
 */
function ordenarAlfabeticamente(tips) {
  return [...tips].sort((a, b) =>
    a.nombre.toLowerCase().localeCompare(b.nombre.toLowerCase())
  );
}

// ─── FUNCIONES DE API (CRUD) ───────────────────────────────────

/**
 * Cargar todos los tips desde la API
 * GET /api/tips.php
 * @returns {Array} Lista de tips
 */
export async function cargarTips() {
  try {
    const response = await fetch(API_URL);
    const json = await response.json();

    if (json.success && json.data) {
      tipsData = json.data;
      return tipsData;
    }
    console.warn("API respondió sin datos:", json.message);
    return [];
  } catch (error) {
    console.error("Error al cargar tips:", error);
    return [];
  }
}

/**
 * Obtener todos los tips cargados, ordenados alfabéticamente
 * @returns {Array} Tips ordenados alfabéticamente por nombre
 */
export function obtenerTodosLosTips() {
  return ordenarAlfabeticamente(tipsData);
}

/**
 * Buscar tips en la API por texto
 * GET /api/tips.php?buscar=texto
 * @param {string} texto - Término de búsqueda
 * @param {AbortSignal|null} signal - Señal para cancelar la petición
 * @returns {Array} Tips encontrados (ordenados alfabéticamente)
 */
export async function buscarTipsAPI(texto, signal = null, categoriaId = "") {
  if (!texto.trim()) return [];
  try {
    const fetchOptions = signal ? { signal } : {};
    const parametros = new URLSearchParams({ buscar: texto.trim() });
    if (categoriaId) parametros.set("categoria_id", categoriaId);
    const response = await fetch(
      `${API_URL}?${parametros.toString()}`,
      fetchOptions
    );
    const json = await response.json();

    if (json.success && json.data) {
      return ordenarAlfabeticamente(json.data);
    }
    return [];
  } catch (error) {
    // Re-throw AbortError so caller can handle it
    if (error.name === "AbortError") throw error;
    console.error("Error al buscar tips:", error);
    return [];
  }
}

export async function cargarCategorias() {
  try {
    const response = await fetch(CATEGORIAS_API_URL);
    const json = await response.json();
    return json.success && json.data ? json.data : [];
  } catch (error) {
    console.error("Error al cargar categorias:", error);
    return [];
  }
}

export async function crearCategoria(nombre) {
  try {
    const response = await fetch(CATEGORIAS_API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ nombre }),
    });
    const json = await response.json();
    if (json.success && json.data) return json.data;
    alert("Error al crear categoria: " + (json.message || "Error desconocido"));
  } catch (error) {
    console.error("Error al crear categoria:", error);
    alert("Error de conexion al crear la categoria.");
  }
  return null;
}

export async function editarCategoria(id, nombre) {
  try {
    const response = await fetch(`${CATEGORIAS_API_URL}?id=${id}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ nombre }),
    });
    const json = await response.json();
    if (json.success && json.data) return json.data;
    alert("Error al actualizar categoria: " + (json.message || "Error desconocido"));
  } catch (error) {
    console.error("Error al actualizar categoria:", error);
    alert("Error de conexion al actualizar la categoria.");
  }
  return null;
}

export async function eliminarCategoria(id) {
  try {
    const response = await fetch(`${CATEGORIAS_API_URL}?id=${id}`, { method: "DELETE" });
    const json = await response.json();
    if (json.success) return true;
    alert(json.message || "No se pudo eliminar la categoria.");
  } catch (error) {
    console.error("Error al eliminar categoria:", error);
    alert("Error de conexion al eliminar la categoria.");
  }
  return false;
}

/**
 * Crear un nuevo tip
 * POST /api/tips.php
 * @param {string} nombre - Título del tip
 * @param {string} contenido - Contenido en Markdown
 * @param {number} categoriaId - Categoría obligatoria del tip
 * @returns {Object|null} Tip creado o null si falló
 */
export async function crearTip(nombre, contenido, categoriaId) {
  try {
    const response = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ nombre, contenido, categoria_id: categoriaId }),
    });
    const json = await response.json();

    if (json.success && json.data) {
      // Agregar al almacén local
      tipsData.unshift(json.data);
      return json.data;
    }
    alert("Error al crear tip: " + (json.message || "Error desconocido"));
    return null;
  } catch (error) {
    console.error("Error al crear tip:", error);
    alert("Error de conexión al crear el tip.");
    return null;
  }
}

/**
 * Editar un tip existente
 * PUT /api/tips.php?id=X
 * @param {number} id - ID del tip
 * @param {string} nombre - Nuevo título
 * @param {string} contenido - Nuevo contenido en Markdown
 * @param {number} categoriaId - Categoría obligatoria del tip
 * @returns {Object|null} Tip actualizado o null si falló
 */
export async function editarTip(id, nombre, contenido, categoriaId) {
  try {
    const response = await fetch(`${API_URL}?id=${id}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ nombre, contenido, categoria_id: categoriaId }),
    });
    const json = await response.json();

    if (json.success && json.data) {
      // Actualizar en el almacén local
      const idx = tipsData.findIndex((t) => t.id == id);
      if (idx !== -1) tipsData[idx] = json.data;
      return json.data;
    }
    alert("Error al editar tip: " + (json.message || "Error desconocido"));
    return null;
  } catch (error) {
    console.error("Error al editar tip:", error);
    alert("Error de conexión al editar el tip.");
    return null;
  }
}

/**
 * Eliminar un tip
 * DELETE /api/tips.php?id=X
 * @param {number} id - ID del tip a eliminar
 * @returns {boolean} true si se eliminó correctamente
 */
export async function eliminarTip(id) {
  try {
    const response = await fetch(`${API_URL}?id=${id}`, {
      method: "DELETE",
    });
    const json = await response.json();

    if (json.success) {
      // Remover del almacén local
      tipsData = tipsData.filter((t) => t.id != id);
      return true;
    }
    alert("Error al eliminar tip: " + (json.message || "Error desconocido"));
    return false;
  } catch (error) {
    console.error("Error al eliminar tip:", error);
    alert("Error de conexión al eliminar el tip.");
    return false;
  }
}

// ─── FUNCIONES DE FILTRADO LOCAL ───────────────────────────────

/**
 * Filtrar tips cargados localmente (búsqueda rápida sin API)
 * FIX: Solo busca en el campo 'nombre' del tip.
 *      El sidebar muestra nombres, así que el filtro debe coincidir
 *      con lo que el usuario ve. Evita falsos positivos donde un tip
 *      aparecía porque su contenido Markdown contenía el término.
 * @param {string} textoBusqueda
 * @returns {Array} Tips filtrados y ordenados
 */
export function filtrarTips(textoBusqueda) {
  if (!textoBusqueda.trim()) return [];
  const textoNorm = normalizarTexto(textoBusqueda.trim());
  const palabras = textoNorm.split(/\s+/);
  const filtrados = tipsData.filter((tip) => {
    const nombreNorm = normalizarTexto(tip.nombre);
    // FIX: Solo buscar en nombre — no en contenido
    return palabras.every((p) => nombreNorm.includes(p));
  });
  return ordenarAlfabeticamente(filtrados);
}

export function filtrarPorCategoria(tips, categoriaId) {
  if (!categoriaId) return ordenarAlfabeticamente(tips);
  return ordenarAlfabeticamente(tips.filter((tip) => String(tip.categoria_id) === String(categoriaId)));
}

/**
 * Re-filtrar resultados devueltos por la API para garantizar que
 * solo se muestren tips cuyo NOMBRE contenga el texto buscado.
 * FIX: La API del servidor puede hacer LIKE en nombre+contenido,
 *      devolviendo tips que coinciden solo en contenido. Esta función
 *      aplica el mismo criterio que filtrarTips() sobre los resultados
 *      de la API antes de renderizarlos.
 * @param {Array} tipsAPI - Tips devueltos por la API
 * @param {string} textoBusqueda - Texto actual del buscador
 * @returns {Array} Tips filtrados cuyo nombre coincide
 */
export function filtrarResultadosAPI(tipsAPI, textoBusqueda) {
  if (!textoBusqueda.trim()) return [];
  const textoNorm = normalizarTexto(textoBusqueda.trim());
  const palabras = textoNorm.split(/\s+/);
  const filtrados = tipsAPI.filter((tip) => {
    const nombreNorm = normalizarTexto(tip.nombre);
    return palabras.every((p) => nombreNorm.includes(p));
  });
  return ordenarAlfabeticamente(filtrados);
}

// ─── FUNCIONES DE RENDERIZADO ──────────────────────────────────

/**
 * Resaltar coincidencias de búsqueda en el texto
 */
function resaltarCoincidencias(texto, textoBusqueda) {
  if (!textoBusqueda || !textoBusqueda.trim()) return texto;
  const palabras = normalizarTexto(textoBusqueda.trim()).split(/\s+/);
  const textoNorm = normalizarTexto(texto);

  // Construir resultado carácter por carácter, marcando las coincidencias
  // Esto permite resaltar correctamente aunque el texto original tenga tildes
  let resultado = "";
  let i = 0;
  const marcas = new Array(texto.length).fill(false);

  // Marcar las posiciones que coinciden con alguna palabra de búsqueda
  palabras.forEach((p) => {
    if (!p) return;
    let pos = 0;
    while ((pos = textoNorm.indexOf(p, pos)) !== -1) {
      for (let j = pos; j < pos + p.length; j++) {
        marcas[j] = true;
      }
      pos++;
    }
  });

  // Reconstruir el HTML con <mark> en las posiciones marcadas
  let enMark = false;
  for (i = 0; i < texto.length; i++) {
    if (marcas[i] && !enMark) {
      resultado += "<mark>";
      enMark = true;
    } else if (!marcas[i] && enMark) {
      resultado += "</mark>";
      enMark = false;
    }
    resultado += texto[i];
  }
  if (enMark) resultado += "</mark>";

  return resultado;
}

/**
 * Mostrar el contenido de un tip en el panel principal
 * Ahora usa el contenido directamente del objeto tip (campo 'contenido')
 * @param {Object} tip - Objeto tip con id, nombre, contenido
 */
function mostrarContenido(tip) {
  const contenedor = document.getElementById("contenido");

  // Guardamos datos para posible edición (solo desktop)
  tipActualId = tip.id;
  contenidoOriginalMD = tip.contenido;
  nombreTipActual = tip.nombre;

  const htmlContenido = marked.parse(tip.contenido || "");

  // Área principal solo muestra el contenido renderizado (sin botones de acción)
  contenedor.innerHTML = `
    <div id="visor-markdown" class="markdown-body">
      ${htmlContenido}
    </div>
  `;
  resaltarBloquesCodigo(contenedor);
  document.querySelectorAll("#resultados tbody tr").forEach((fila) => {
    fila.classList.toggle("tip-seleccionado", String(fila.dataset.tipId) === String(tip.id));
  });
}

export function resaltarBloquesCodigo(contenedor) {
  if (!window.hljs) return;
  contenedor.querySelectorAll("pre code").forEach((bloque) => {
    window.hljs.highlightElement(bloque);
  });
}

/**
 * Renderizar la tabla de resultados en el sidebar
 * @param {Array} tips - Lista de tips a mostrar
 * @param {string} textoBusqueda - Texto para resaltar coincidencias
 */
export function renderizarTabla(tips, textoBusqueda = "") {
  const tbody = document.getElementById("resultados-body");
  tbody.innerHTML = "";

  tips.forEach((tip) => {
    const tr = document.createElement("tr");
    tr.dataset.tipId = tip.id;
    tr.classList.toggle("tip-seleccionado", String(tip.id) === String(tipActualId));
    const td = document.createElement("td");

    // Enlace con nombre del tip
    const a = document.createElement("a");
    a.innerHTML = resaltarCoincidencias(tip.nombre, textoBusqueda);
    a.href = "#";
    a.addEventListener("click", (e) => {
      e.preventDefault();
      mostrarContenido(tip);
    });
    td.appendChild(a);

    // Botones de acción (solo desktop)
    if (!esMobile()) {
      const acciones = document.createElement("span");
      acciones.className = "tip-acciones desktop-only";

      if (tip.categoria_id) {
        const categoriaAsignada = document.createElement("span");
        categoriaAsignada.className = "tip-categoria-asignada";
        categoriaAsignada.textContent = "✓";
        categoriaAsignada.title = `Categoria: ${tip.categoria_nombre || "asignada"}`;
        acciones.appendChild(categoriaAsignada);
      }

      // Botón editar
      const btnEditar = document.createElement("button");
      btnEditar.className = "btn-accion-tip btn-editar-tip";
      btnEditar.title = "Editar tip";
      btnEditar.textContent = "✏️";
      btnEditar.addEventListener("click", (e) => {
        e.stopPropagation();
        const evento = new CustomEvent("activarEdicion", {
          detail: {
            id: tip.id,
            titulo: tip.nombre,
            contenido: tip.contenido,
            categoriaId: tip.categoria_id,
          },
        });
        document.dispatchEvent(evento);
      });

      // Botón eliminar
      const btnEliminar = document.createElement("button");
      btnEliminar.className = "btn-accion-tip btn-eliminar-tip";
      btnEliminar.title = "Eliminar tip";
      btnEliminar.textContent = "🗑️";
      btnEliminar.addEventListener("click", (e) => {
        e.stopPropagation();
        const evento = new CustomEvent("activarEliminacion", {
          detail: { id: tip.id, nombre: tip.nombre },
        });
        document.dispatchEvent(evento);
      });

      acciones.appendChild(btnEditar);
      acciones.appendChild(btnEliminar);
      td.appendChild(acciones);
    }

    tr.appendChild(td);
    tbody.appendChild(tr);
  });
}
