const routes = {
  home: document.getElementById("home-screen"),
  tips: document.getElementById("app"),
  drive: document.getElementById("drive-screen"),
};

const routeByHash = {
  "#home": "home",
  "#tips": "tips",
  "#drive": "drive",
};

let tipsModuleLoaded = false;

async function asegurarModuloTips() {
  if (tipsModuleLoaded) return;
  try {
    await import("./script.js");
    tipsModuleLoaded = true;
  } catch (error) {
    console.error("No se pudo cargar el modulo Tips:", error);
  }
}

function irA(routeName) {
  Object.values(routes).forEach((el) => el.classList.add("screen-hidden"));
  routes[routeName].classList.remove("screen-hidden");

  if (routeName === "tips") {
    asegurarModuloTips();
  }
}

function resolverRuta() {
  const routeName = routeByHash[window.location.hash] || "home";
  irA(routeName);
}

function configurarNavegacion() {
  const btnTips = document.getElementById("btn-modulo-tips");
  const btnDrive = document.getElementById("btn-modulo-drive");
  const btnVolverTips = document.getElementById("btn-volver-home-tips");
  const btnVolverDrive = document.getElementById("btn-volver-home-drive");

  btnTips.addEventListener("click", () => {
    window.location.hash = "tips";
  });

  btnDrive.addEventListener("click", () => {
    window.location.hash = "drive";
  });

  btnVolverTips.addEventListener("click", () => {
    window.location.hash = "home";
  });

  btnVolverDrive.addEventListener("click", () => {
    window.location.hash = "home";
  });

  window.addEventListener("hashchange", resolverRuta);

  if (!window.location.hash) {
    window.location.hash = "home";
  } else {
    resolverRuta();
  }
}

document.addEventListener("DOMContentLoaded", () => {
  configurarNavegacion();
});
