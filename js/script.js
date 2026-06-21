// ====
// BuscaTips - Script principal (eventos y lógica de UI)
// ====
// Conecta la interfaz con las funciones de la API (libreria.js)
// Maneja: buscador, editor unificado (crear/editar), eliminación
// ====
// FIX: Los resultados de la API se re-filtran con filtrarResultadosAPI()
//      antes de renderizar, asegurando consistencia con lo que el
//      usuario ve en el sidebar (solo coincidencias por nombre).
// ====

import {
  cargarTips,
  filtrarTips,
  filtrarResultadosAPI,
  buscarTipsAPI,
  crearTip,
  editarTip,
  eliminarTip,
  renderizarTabla,
} from "./libreria.js";

// Detección de mobile
const esMobile = () => window.matchMedia("(max-width: 768px)").matches;

// Timer para debounce en búsqueda
let debounceTimer = null;
// Versión de búsqueda: se incrementa en cada keystroke para invalidar respuestas obsoletas
let searchVersion = 0;
// AbortController para cancelar peticiones API en vuelo
let abortController = null;
let tipsInicializado = false;

// ─── INICIALIZACIÓN ────

async function inicializarTips() {
  if (tipsInicializado) return;
  tipsInicializado = true;

  // Cargar todos los tips en memoria (sin mostrarlos en el sidebar)
  await cargarTips();

  // Configurar buscador (disponible en todas las plataformas)
  configurarBuscador();

  // Configurar toggle de resultados para mobile
  configurarToggleResultadosMobile();

  // Solo en desktop: editor y botón crear
  if (!esMobile()) {
    configurarBotonCrear();
  }

  // Escuchar eventos personalizados de edición y eliminación
  document.addEventListener("activarEdicion", (e) => {
    const { id, titulo, contenido } = e.detail;
    abrirEditor(titulo, contenido, true, id);
  });

  document.addEventListener("activarEliminacion", (e) => {
    manejarEliminacion(e.detail.id, e.detail.nombre);
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", inicializarTips, { once: true });
} else {
  inicializarTips();
}

/**
 * Limpia el campo de búsqueda, el sidebar y recarga los datos en memoria
 */
async function limpiarYRecargar() {
  document.getElementById("buscador").value = "";
  document.getElementById("resultados-body").innerHTML = "";
  await cargarTips();
}

// ─── BUSCADOR ────

function configurarBuscador() {
  const inputBuscador = document.getElementById("buscador");
  const panelContenido = document.getElementById("contenido");

  inputBuscador.addEventListener("input", (e) => {
    const texto = e.target.value;

    // Incrementar versión de búsqueda para invalidar cualquier respuesta en vuelo
    searchVersion++;
    const miVersion = searchVersion;

    // Cancelar timeout de debounce pendiente
    clearTimeout(debounceTimer);

    // Abortar cualquier petición API en vuelo
    if (abortController) {
      abortController.abort();
      abortController = null;
    }

    // Si el campo está vacío, limpiar sidebar y área principal
    if (!texto.trim()) {
      document.getElementById("resultados-body").innerHTML = "";
      panelContenido.innerHTML = "";
      return;
    }

    // Filtrado local inmediato (rápido, sin esperar API)
    // FIX: filtrarTips() ahora solo busca en 'nombre'
    const resultadosLocales = filtrarTips(texto);
    // Solo renderizar si esta sigue siendo la búsqueda vigente
    if (searchVersion === miVersion) {
      renderizarTabla(resultadosLocales, texto);
    }

    // Debounce para búsqueda en API (más completa, incluye contenido en servidor)
    debounceTimer = setTimeout(async () => {
      // Verificar que seguimos en la misma versión de búsqueda
      if (searchVersion !== miVersion) return;

      // FIX: Leer el valor ACTUAL del input como verificación extra
      const textoActual = inputBuscador.value.trim();
      if (!textoActual) return;

      // Crear nuevo AbortController para esta petición
      abortController = new AbortController();

      try {
        const resultadosAPI = await buscarTipsAPI(textoActual, abortController.signal);

        // Triple verificación: versión + texto actual coinciden
        if (searchVersion === miVersion && inputBuscador.value.trim() === textoActual) {
          // FIX: Re-filtrar resultados de la API para que solo contengan
          // tips cuyo NOMBRE coincida con la búsqueda. La API del servidor
          // puede devolver tips que coinciden solo en contenido (LIKE en
          // nombre+contenido), lo que causaba los falsos positivos.
          const resultadosFiltrados = filtrarResultadosAPI(resultadosAPI, textoActual);

          // Solo renderizar si hay resultados filtrados; si no, mantener
          // los resultados locales que ya están en pantalla.
          if (resultadosFiltrados.length > 0) {
            renderizarTabla(resultadosFiltrados, textoActual);
          }
        }
      } catch (err) {
        // Ignorar errores de abort (son esperados)
        if (err.name !== "AbortError") {
          console.error("Error en búsqueda API:", err);
        }
      }
    }, 400);
  });
}

// ─── TOGGLE RESULTADOS MOBILE ────

function configurarToggleResultadosMobile() {
  const btnToggle = document.getElementById("btn-toggle-resultados");
  const contResultados = document.getElementById("resultados-container");
  if (!btnToggle || !contResultados) return;

  btnToggle.classList.remove("collapsed");
  contResultados.classList.remove("collapsed");

  btnToggle.addEventListener("click", () => {
    const colapsado = contResultados.classList.toggle("collapsed");
    btnToggle.classList.toggle("collapsed", colapsado);
  });
}

// ─── BOTÓN CREAR (SOLO DESKTOP) ────

function configurarBotonCrear() {
  const btnCrear = document.getElementById("btn-crear-tip");
  if (!btnCrear) return;

  btnCrear.addEventListener("click", () => {
    // Abrir editor vacío para nuevo tip
    abrirEditor("", "", false, null);
  });
}

// ─── EDITOR UNIFICADO (CREAR / EDITAR) ────

/**
 * Abre el editor en el panel de contenido
 * @param {string} tituloInicial - Título prellenado
 * @param {string} contenidoInicial - Contenido Markdown prellenado
 * @param {boolean} esEdicion - true si es edición de un tip existente
 * @param {number|null} tipId - ID del tip a editar (null si es nuevo)
 */
function abrirEditor(
  tituloInicial = "",
  contenidoInicial = "",
  esEdicion = false,
  tipId = null
) {
  const panelContenido = document.getElementById("contenido");

  // Guardamos la vista previa original para restaurar si se cancela en modo edición
  const vistaPreviaOriginal = panelContenido.innerHTML;

  // Escapar comillas dobles para el atributo value del input
  const tituloEscapado = tituloInicial
    .replace(/&/g, "&amp;")
    .replace(/"/g, "&quot;");

  panelContenido.innerHTML = `
    <div class="editor-container">
      <div class="editor-header">
        <label style="color: #5f7e97; font-size: 0.8rem;">
          ${esEdicion ? "Editando tip — Título:" : "Nuevo tip — Título:"}
        </label>
        <input type="text" id="editor-title" class="editor-title-input" 
               placeholder="Nombre del tip..." value="${tituloEscapado}">
      </div>
      <div id="editor-workspace" style="flex: 1; display: flex; flex-direction: column;">
        <textarea id="editor-text" class="editor-textarea" 
                  placeholder="Escribe tu contenido en Markdown aquí...">${contenidoInicial}</textarea>
        <div id="editor-preview" class="editor-preview-area hidden"></div>
      </div>
      <div class="editor-actions">
        <button id="btn-cancelar-edicion" class="btn-cancel-edit">
          ${esEdicion ? "Volver a Vista" : "Cancelar"}
        </button>
        <button id="btn-toggle-preview" class="btn-preview">Vista Previa</button>
        <button id="btn-guardar-tip" class="btn-save">
          ${esEdicion ? "💾 Guardar Cambios" : "💾 Crear Tip"}
        </button>
      </div>
    </div>
  `;

  const textarea = document.getElementById("editor-text");
  const previewArea = document.getElementById("editor-preview");
  const btnPreview = document.getElementById("btn-toggle-preview");

  // Toggle vista previa / edición
  btnPreview.addEventListener("click", () => {
    const isPreview = !previewArea.classList.contains("hidden");
    if (isPreview) {
      previewArea.classList.add("hidden");
      textarea.classList.remove("hidden");
      btnPreview.textContent = "Vista Previa";
    } else {
      previewArea.innerHTML = marked.parse(textarea.value);
      textarea.classList.add("hidden");
      previewArea.classList.remove("hidden");
      btnPreview.textContent = "Editar";
    }
  });

  // Cancelar edición
  document
    .getElementById("btn-cancelar-edicion")
    .addEventListener("click", () => {
      if (esEdicion) {
        // Restaurar vista previa del tip que se estaba viendo
        panelContenido.innerHTML = vistaPreviaOriginal;
      } else {
        panelContenido.innerHTML = "";
      }
    });

  // Guardar tip (crear o editar)
  document
    .getElementById("btn-guardar-tip")
    .addEventListener("click", async () => {
      const titulo = document.getElementById("editor-title").value.trim();
      const contenido = textarea.value.trim();

      if (!titulo || !contenido) {
        alert("Completa título y contenido.");
        return;
      }

      if (esEdicion && tipId) {
        // ── EDITAR tip existente ──
        const tipActualizado = await editarTip(tipId, titulo, contenido);
        if (tipActualizado) {
          mostrarMensajeExito("¡Tip actualizado exitosamente!");
          // Limpiar búsqueda y recargar lista completa
          await limpiarYRecargar();
        }
      } else {
        // ── CREAR nuevo tip ──
        const tipCreado = await crearTip(titulo, contenido);
        if (tipCreado) {
          mostrarMensajeExito("¡Tip creado exitosamente!");
          // Limpiar búsqueda y recargar lista completa
          await limpiarYRecargar();
        }
      }
    });
}

// ─── ELIMINACIÓN ────

/**
 * Maneja la eliminación de un tip con confirmación
 * @param {number} id - ID del tip
 * @param {string} nombre - Nombre del tip (para mostrar en confirmación)
 */
async function manejarEliminacion(id, nombre) {
  const confirmado = confirm(
    `¿Estás seguro de eliminar el tip "${nombre}"?\n\nEsta acción no se puede deshacer.`
  );

  if (!confirmado) return;

  const eliminado = await eliminarTip(id);
  if (eliminado) {
    mostrarMensajeExito(`Tip "${nombre}" eliminado.`);
    // Limpiar búsqueda y recargar lista completa
    await limpiarYRecargar();
  }
}

// ─── UTILIDADES ────

/**
 * Muestra un mensaje de éxito temporal en el panel de contenido
 * @param {string} mensaje
 */
function mostrarMensajeExito(mensaje) {
  const panelContenido = document.getElementById("contenido");
  panelContenido.innerHTML = `<p style="color: #addb67; font-size: 1.1rem; padding: 20px;">✅ ${mensaje}</p>`;
  setTimeout(() => {
    panelContenido.innerHTML = "";
  }, 3000);
}
