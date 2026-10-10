const routes = {
  home: document.getElementById("home-screen"),
  tips: document.getElementById("app"),
  drive: document.getElementById("drive-screen"),
};

const routeByHash = { "#home": "home", "#tips": "tips", "#drive": "drive" };
let tipsModuleLoaded = false;
let googleClientId = "";

async function asegurarModuloTips() {
  if (tipsModuleLoaded) return;
  try {
    await import("./script.js?v=20260906-03");
    tipsModuleLoaded = true;
  } catch (error) {
    console.error("No se pudo cargar el modulo Tips:", error);
  }
}

function irA(routeName) {
  Object.values(routes).forEach((el) => el.classList.add("screen-hidden"));
  routes[routeName].classList.remove("screen-hidden");
  if (routeName === "tips") asegurarModuloTips();
}

function resolverRuta() {
  const ruta = routeByHash[window.location.hash] || "home";
  irA(window.piaUsuario ? ruta : "home");
}

function actualizarInterfazSesion() {
  const cerrarSesion = document.getElementById("btn-cerrar-sesion");
  cerrarSesion.classList.toggle("hidden", !window.piaUsuario);
  document.dispatchEvent(new CustomEvent("pia-sesion-actualizada", { detail: window.piaUsuario }));
}

async function consultarSesion() {
  try {
    const response = await fetch("api/auth.php");
    const json = await response.json();
    window.piaUsuario = json.success ? json.data?.usuario || null : null;
    googleClientId = json.success ? json.data?.google_client_id || "" : "";
  } catch (error) {
    console.error("Error al consultar la sesion:", error);
    window.piaUsuario = null;
  }
  actualizarInterfazSesion();
  if (!window.piaUsuario) mostrarLoginGoogle();
}

function mostrarLoginGoogle() {
  const modal = document.getElementById("login-modal");
  const error = document.getElementById("login-error");
  modal.classList.remove("hidden");
  error.textContent = "";
  if (!googleClientId) {
    error.textContent = "El acceso con Google aun no esta configurado.";
    return;
  }
  esperarGoogleYRenderizar();
}

function esperarGoogleYRenderizar() {
  if (!window.google?.accounts?.id) {
    window.setTimeout(esperarGoogleYRenderizar, 100);
    return;
  }
  window.google.accounts.id.initialize({ client_id: googleClientId, callback: iniciarSesionGoogle });
  window.google.accounts.id.renderButton(document.getElementById("google-signin-button"), {
    theme: "outline", size: "large", text: "continue_with", width: 300,
  });
}

async function iniciarSesionGoogle(respuestaGoogle) {
  const error = document.getElementById("login-error");
  try {
    const response = await fetch("api/auth.php", {
      method: "POST", headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ accion: "google_login", credential: respuestaGoogle.credential }),
    });
    const json = await response.json();
    if (!json.success || !json.data) {
      error.textContent = json.message || "No fue posible iniciar sesion.";
      return;
    }
    window.piaUsuario = json.data;
    document.getElementById("login-modal").classList.add("hidden");
    actualizarInterfazSesion();
    resolverRuta();
  } catch (errorLogin) {
    console.error("Error al iniciar sesion con Google:", errorLogin);
    error.textContent = "No fue posible conectar con el servicio de acceso.";
  }
}

async function cerrarSesion() {
  try {
    const response = await fetch("api/auth.php", {
      method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ accion: "logout" }),
    });
    const json = await response.json();
    if (!json.success) throw new Error(json.message);
    window.piaUsuario = null;
    window.location.hash = "home";
    actualizarInterfazSesion();
    mostrarLoginGoogle();
  } catch (error) {
    console.error("Error al cerrar sesion:", error);
    alert("No fue posible cerrar la sesion.");
  }
}

function configurarNavegacion() {
  document.getElementById("btn-modulo-tips").addEventListener("click", () => { window.location.hash = "tips"; });
  document.getElementById("btn-modulo-drive").addEventListener("click", () => { window.location.hash = "drive"; });
  document.getElementById("btn-volver-home-tips").addEventListener("click", cerrarSesion);
  document.getElementById("btn-volver-home-drive").addEventListener("click", cerrarSesion);
  document.getElementById("btn-cerrar-sesion").addEventListener("click", cerrarSesion);
  window.addEventListener("hashchange", resolverRuta);
  if (!window.location.hash) window.location.hash = "home";
  else resolverRuta();
}

document.addEventListener("DOMContentLoaded", () => {
  configurarNavegacion();
  consultarSesion();
});
