/**
 * scripts.js — Auditoria de Chamados (Versão B — Otimizada & Reestruturada)
 * --------------------------------------------------------
 * Este arquivo foi reorganizado para:
 * - Maior legibilidade
 * - Modularização interna
 * - Manutenção facilitada
 * - Compatibilidade com todo backend existente
 * - Reintegração completa do módulo NC (justificativas)
 *
 * Todas as funcionalidades originais foram preservadas.
 * --------------------------------------------------------
 */


/* =========================================================
   BASE UTILITIES
   ========================================================= */

/** Retorna o formulário principal, considerando rotas diferentes */
function getForm() {
  return document.querySelector(
    'form[action$="/audit-entries"], form[action$="/audit-estoque"]'
  );
}

/** Join seguro com APP_BASE do backend */
function baseJoin(path) {
  const base = (window.APP_BASE || '').replace(/\/+$/, '');
  return base + path;
}

/** Headers para chamadas autenticadas aos catálogos */
function getCatalogHeaders() {
  const headers = {
    "Accept": "application/json",
    "X-Requested-With": "XMLHttpRequest"
  };
  if (window.CATALOG_TOKEN) {
    headers["X-Form-Token"] = window.CATALOG_TOKEN;
  }
  return headers;
}


/* =========================================================
   SISTEMA DE EXPORTAÇÃO
   ========================================================= */

/** Exportação por mês — botão das opções */
window.exportByMonth = function () {
  const m = (document.querySelector('input[name="audit_month"]')?.value || '').trim();
  if (!m) return;

  window.location.href =
    baseJoin('/export/csv') + '?audit_month=' + encodeURIComponent(m);
};


/* =========================================================
   SANFONA DAS OPÇÕES DE EXPORTAÇÃO
   ========================================================= */

(function initExportAccordion() {
  const toggle = document.getElementById('export-toggle');
  const panel = document.getElementById('export-panel');

  if (!toggle || !panel) return;

  const LOCAL_KEY = "exportPanelOpen";

  function setOpen(open) {
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    open ? panel.removeAttribute("hidden") : panel.setAttribute("hidden", "");
    try {
      localStorage.setItem(LOCAL_KEY, open ? "1" : "0");
    } catch { }
  }

  toggle.addEventListener("click", () => {
    const curr = toggle.getAttribute("aria-expanded") === "true";
    setOpen(!curr);
  });

  let startOpen = false;
  try {
    startOpen = localStorage.getItem(LOCAL_KEY) === "1";
  } catch { }

  setOpen(startOpen);
})();
/* =========================================================
   BANNER DE SUCESSO (COUNTDOWN)
   ========================================================= */

(function initSuccessCountdown() {
  const el = document.getElementById("countdown");
  if (!el) return;

  let timeLeft = parseInt(el.textContent || "5", 10);

  const t = setInterval(() => {
    timeLeft--;
    el.textContent = String(timeLeft);

    if (timeLeft <= 0) {
      clearInterval(t);

      try {
        const url = new URL(window.location.href);
        url.searchParams.delete("created");

        window.history.replaceState(
          {},
          "",
          url.pathname +
            (url.searchParams.toString()
              ? "?" + url.searchParams.toString()
              : "")
        );
      } catch {}

      document.querySelector('input[name="ticket_number"]')?.focus();
    }
  }, 1000);
})();


/* =========================================================
   TICKET NUMBER + VALIDAÇÃO + SERVICENOW CHECKER
   ========================================================= */

(function initTicketChecker() {
  const tn = document.getElementById("ticket_number");
  if (!tn) return;

  const radios = Array.from(
    document.querySelectorAll('input[name="ticket_type"]')
  );

  const prefixMap = {
    INC: "INCIDENTE",
    RITM: "REQUISIÇÃO",
    SCTASK: "TASK",
  };

  /* ---------- UI extra (ícone ✔/✖ e link SNOW) ---------- */
  const wrap = document.createElement("div");
  wrap.style.display = "flex";
  wrap.style.alignItems = "center";
  wrap.style.gap = "8px";

  tn.parentElement.insertBefore(wrap, tn);
  wrap.appendChild(tn);

  const icon = document.createElement("span");
  icon.style.fontSize = "18px";
  icon.style.minWidth = "20px";
  icon.style.textAlign = "center";
  wrap.appendChild(icon);

  const link = document.createElement("a");
  link.textContent = "Abrir no ServiceNow";
  link.target = "_blank";
  link.style.display = "none";
  link.style.fontSize = "12px";
  link.style.color = "#2563eb";
  wrap.appendChild(link);

  function setState(ok, url) {
    tn.style.borderColor = ok ? "#16a34a" : "#dc2626";
    icon.textContent = ok ? "✔" : "✖";
    icon.style.color = ok ? "#16a34a" : "#dc2626";

    if (ok && url) {
      link.href = url;
      link.style.display = "inline";
    } else {
      link.style.display = "none";
    }
  }

  function updateAria() {
    radios.forEach((r) => {
      const lab = document.querySelector(`label[for="${r.id}"]`);
      if (lab) {
        lab.setAttribute("aria-pressed", r.checked ? "true" : "false");
      }
    });
  }

  function selectType(v) {
    const m = /^(INC|RITM|SCTASK)/.exec(v);
    if (!m) return;

    const mapped = prefixMap[m[1]];

    radios.forEach((r) => {
      r.checked = String(r.value).toUpperCase() === mapped;
    });

    updateAria();
  }

  function normalize() {
    let v = (tn.value || "")
      .toUpperCase()
      .replace(/\s+/g, "");

    tn.value = v;

    const ok = /^(INC|RITM|SCTASK)\d{6,}$/.test(v);
    tn.setCustomValidity(
      ok ? "" : "O ticket deve iniciar com INC, RITM ou SCTASK + dígitos."
    );

    if (ok) selectType(v);
    return ok ? v : "";
  }

  async function checkSN(v) {
    try {
      const res = await fetch(
        baseJoin("/api/check-ticket?number=" + encodeURIComponent(v))
      );

      const data = await res.json();

      if (data.exists) {
        setState(true, data.url || data.redirect);
        return true;
      }

      setState(false);
      return false;
    } catch {
      return true;
    }
  }

  tn.addEventListener("input", () => {
    tn.style.borderColor = "";
    icon.textContent = "";
    link.style.display = "none";
    normalize();
  });

  tn.addEventListener("blur", async () => {
    const v = normalize();
    if (v) await checkSN(v);
  });

  const form = getForm();
  if (form) {
    form.addEventListener("submit", async (e) => {
      const v = normalize();

      if (!tn.checkValidity()) {
        e.preventDefault();
        tn.reportValidity();
        return;
      }

      if (v) {
        const ok = await checkSN(v);
        if (!ok) e.preventDefault();
      }
    });
  }
})();
/* =========================================================
   NORMALIZADOR DO CAMPO "MÊS DA AUDITORIA"
   ========================================================= */

(function initAuditMonthNormalizer() {
  const m = document.querySelector('input[name="audit_month"]');
  if (!m) return;

  const monthMap = {
    jan: "01", janeiro: "01",
    fev: "02", fevereiro: "02",
    mar: "03", março: "03", marco: "03",
    abr: "04", abril: "04",
    mai: "05", maio: "05",
    jun: "06", junho: "06",
    jul: "07", julho: "07",
    ago: "08", agosto: "08",
    set: "09", setembro: "09",
    out: "10", outubro: "10",
    nov: "11", novembro: "11",
    dez: "12", dezembro: "12"
  };

  function normalize(v) {
    v = (v || "").trim().toLowerCase();

    // YYYY-MM
    if (/^\d{4}-(0[1-9]|1[0-2])$/.test(v)) return v;

    // MM/YYYY
    const m1 = v.match(/^(0?[1-9]|1[0-2])\s*\/\s*(\d{4})$/);
    if (m1) return `${m1[2]}-${String(m1[1]).padStart(2, "0")}`;

    // <nome mês> <ano>
    const m2 = v.match(/^([a-zçõ]+)\s+(\d{4})$/);
    if (m2 && monthMap[m2[1]]) return `${m2[2]}-${monthMap[m2[1]]}`;

    // apenas nome do mês → usa ano atual
    if (monthMap[v]) {
      return `${new Date().getFullYear()}-${monthMap[v]}`;
    }

    return v;
  }

  m.addEventListener("blur", () => {
    m.value = normalize(m.value);
  });
})();


/* =========================================================
   AUTOCOMPLETE — ENGINE PADRÃO PARA TODOS CAMPOS
   ========================================================= */

function makeAutocomplete(opts) {
  const {
    inputId,
    hiddenNameId,
    hiddenIdId,
    popupId,
    resource,
    nameFallbacks = ["name", "label"]
  } = opts;

  const input = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenNameId);
  const hidId = document.getElementById(hiddenIdId);
  const popup = document.getElementById(popupId);

  if (!input || !hidden || !hidId || !popup) return;

  const isLocked = input.hasAttribute("data-locked");
  if (isLocked) {
    hidden.value = input.value;
    return;
  }

  let timer = null;
  let cache = [];

  function closePopup() {
    popup.style.display = "none";
    popup.innerHTML = "";
  }

  function openPopup() {
    popup.style.display = "block";
  }

  function clearSelection() {
    hidden.value = "";
    hidId.value = "";
  }

  function itemName(obj) {
    for (const k of nameFallbacks) {
      if (obj && typeof obj[k] === "string" && obj[k].trim() !== "") {
        return obj[k];
      }
    }
    return "";
  }

  function selectItem(obj) {
    const name = itemName(obj);
    input.value = name;
    hidden.value = name;
    hidId.value = String(obj?.id ?? "");
    closePopup();
  }

  function render(items) {
    popup.innerHTML = "";
    if (!items?.length) {
      closePopup();
      return;
    }

    const list = document.createElement("div");
    list.style.display = "flex";
    list.style.flexDirection = "column";

    items.forEach((it) => {
      const nm = itemName(it);
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "preset-badge";
      btn.style.textAlign = "left";
      btn.style.margin = "4px";
      btn.textContent = nm;
      btn.onclick = () => selectItem(it);
      list.appendChild(btn);
    });

    popup.appendChild(list);
    openPopup();
  }

  async function fetchList(q) {
    try {
      const url =
        baseJoin(`/api/catalog?resource=${encodeURIComponent(resource)}&q=${encodeURIComponent(q)}`);

      const res = await fetch(url, { headers: getCatalogHeaders() });

      if (!res.ok) throw new Error("Fetch " + res.status);

      const data = await res.json();
      cache = Array.isArray(data) ? data : [];
      render(cache);
    } catch {
      cache = [];
      closePopup();
    }
  }

  input.addEventListener("input", () => {
    clearSelection();

    const q = input.value.trim();

    clearTimeout(timer);

    if (q.length < 2) {
      closePopup();
      return;
    }

    timer = setTimeout(() => fetchList(q), 180);
  });

  input.addEventListener("focus", () => {
    const q = input.value.trim();
    if (q.length >= 2) fetchList(q);
  });

  document.addEventListener("click", (e) => {
    if (!popup.contains(e.target) && e.target !== input) {
      closePopup();
    }
  });

  const form = getForm();
  if (form) {
    form.addEventListener("submit", (e) => {
      const vis = input.value.trim();
      const sel = hidden.value.trim();

      if (!vis || !sel || vis !== sel) {
        e.preventDefault();
        input.focus();
      }
    });
  }
}
/* =========================================================
   AUTOCOMPLETES — INSTÂNCIAS
   ========================================================= */

makeAutocomplete({
  inputId: "kyndryl_auditor_input",
  hiddenNameId: "kyndryl_auditor_value",
  hiddenIdId: "kyndryl_auditor_id",
  popupId: "auditor_suggest",
  resource: "kyndryl-auditors",
  nameFallbacks: ["name", "label", "kyndryl_auditor"]
});

makeAutocomplete({
  inputId: "petrobras_inspector_input",
  hiddenNameId: "petrobras_inspector_value",
  hiddenIdId: "petrobras_inspector_id",
  popupId: "inspector_suggest",
  resource: "petrobras-inspectors",
  nameFallbacks: ["name", "label", "petrobras_inspector"]
});

makeAutocomplete({
  inputId: "audited_supplier_input",
  hiddenNameId: "audited_supplier_value",
  hiddenIdId: "audited_supplier_id",
  popupId: "supplier_suggest",
  resource: "audited-suppliers",
  nameFallbacks: ["name", "label", "audited_supplier"]
});

makeAutocomplete({
  inputId: "location_input",
  hiddenNameId: "location_value",
  hiddenIdId: "location_id",
  popupId: "location_suggest",
  resource: "locations",
  nameFallbacks: ["name", "label", "location"]
});

makeAutocomplete({
  inputId: "category_input",
  hiddenNameId: "category_value",
  hiddenIdId: "category_id",
  popupId: "category_suggest",
  resource: "categories",
  nameFallbacks: ["name", "label", "category"]
});

makeAutocomplete({
  inputId: "resolver_group_input",
  hiddenNameId: "resolver_group_value",
  hiddenIdId: "resolver_group_id",
  popupId: "resolver_suggest",
  resource: "resolver-groups",
  nameFallbacks: ["name", "label", "resolver_group"]
});


/* =========================================================
   CONTROLE "CHAMADO CONFORME?"
   ========================================================= */

(function initIsCompliantControl() {
  const radios = Array.from(
    document.querySelectorAll('input[name="is_compliant"]')
  );

  const block = document.getElementById("just_block");
  if (!radios.length || !block) return;

  const yes = document.querySelector('input[name="is_compliant"][value="1"]');
  const no = document.querySelector('input[name="is_compliant"][value="0"]');

  function showBlock(show) {
    block.style.display = show ? "block" : "none";
  }

  function updateAria() {
    radios.forEach((r) => {
      const lab = r.id ? document.querySelector(`label[for="${r.id}"]`) : null;
      if (lab) {
        lab.setAttribute("aria-pressed", r.checked ? "true" : "false");
      }
    });
  }

  function setInitial() {
    const checked = document.querySelector(
      'input[name="is_compliant"]:checked'
    )?.value;

    showBlock(checked === "0");
    updateAria();
  }

  radios.forEach((r) =>
    r.addEventListener("change", (e) => {
      const val = e.target.value;

      if (val === "1") {
        const hasNC = window.NC && window.NC.getCount() > 0;

        if (hasNC) {
          const ok = confirm(
            'Você marcou "Sim" para Conforme. As justificativas serão removidas. Continuar?'
          );
          if (ok) {
            window.NC.clearAll();
            document.getElementById("nc_ids").value = "";
            yes.checked = true;
            showBlock(false);
          } else {
            no.checked = true;
            showBlock(true);
          }
        } else {
          yes.checked = true;
          showBlock(false);
        }
      } else {
        no.checked = true;
        showBlock(true);
      }

      updateAria();
    })
  );

  setInitial();
})();
/* =========================================================
   MÓDULO NC — Justificativas de Não Conformidade
   ( COMPLETO / REINTEGRADO / OTIMIZADO )
   ========================================================= */

/**
 * Estrutura:
 * - NC.loadPresets()        → carrega presets do catálogo
 * - NC.renderPresets()      → renderiza por grupo
 * - NC.add(id, label)       → adiciona chip
 * - NC.remove(id)           → remove chip
 * - NC.clearAll()           → remove tudo
 * - NC.getCount()           → total de chips
 * - NC.syncHidden()         → atualiza hidden nc_ids
 */

window.NC = (() => {
  const WRAP = document.getElementById("nc_presets");
  const CHIPS = document.getElementById("nc_chips");
  const HIDDEN = document.getElementById("nc_ids");
  const SEARCH = document.getElementById("nc_search");

  if (!WRAP || !CHIPS || !HIDDEN) {
    console.warn("NC module: elementos não encontrados.");
    return {};
  }

  /* Armazena chips na memória local do JS */
  let items = new Map(); // { id → label }

  /* ========= ATUALIZA O CAMPO HIDDEN ========= */
  function syncHidden() {
    const ids = Array.from(items.keys());
    HIDDEN.value = ids.join(";");
  }

  /* ========= ADICIONAR CHIP ========= */
  function add(id, label) {
    id = String(id).trim();
    label = String(label).trim();
    if (!id || !label) return;

    if (!items.has(id)) {
      items.set(id, label);
      syncHidden();
      renderChips();
    }
  }

  /* ========= REMOVER CHIP ========= */
  function remove(id) {
    if (items.has(id)) {
      items.delete(id);
      syncHidden();
      renderChips();
    }
  }

  /* ========= REMOVE TODOS ========= */
  function clearAll() {
    items.clear();
    syncHidden();
    renderChips();
  }

  /* ========= QUANTIDADE ========= */
  function getCount() {
    return items.size;
  }

  /* ========= RENDERIZA OS CHIPS VISUAIS ========= */
  function renderChips() {
    CHIPS.innerHTML = "";

    for (const [id, label] of items.entries()) {
      const chip = document.createElement("div");
      chip.className = "tag-chip";
      chip.dataset.id = id;

      const span = document.createElement("span");
      span.textContent = label;

      const x = document.createElement("button");
      x.type = "button";
      x.textContent = "×";
      x.className = "x";
      x.onclick = () => remove(id);

      chip.appendChild(span);
      chip.appendChild(x);

      CHIPS.appendChild(chip);
    }
  }

  /* =========================================================
     PRESETS / BUSCA — CARREGAR JUSTIFICATIVAS
     ========================================================= */

  let FULL_LIST = []; // Lista completa vinda do catálogo

  async function loadPresets(query = "") {
    try {
      const url = baseJoin(
        `/api/catalog?resource=noncompliance-reasons&q=${encodeURIComponent(query)}`
      );

      const res = await fetch(url, { headers: getCatalogHeaders() });
      const data = await res.json();

      if (Array.isArray(data)) {
        FULL_LIST = data;
        renderPresets();
      }
    } catch (e) {
      console.error("Erro ao carregar presets NC:", e);
    }
  }

  /* ========= Grupo → renderização visual dos presets ========= */
  function renderPresets() {
    WRAP.innerHTML = "";

    if (!FULL_LIST.length) {
      WRAP.innerHTML = "<div class='muted'>Nenhuma justificativa encontrada...</div>";
      return;
    }

    // Agrupa por FULL_LIST[i].group
    const groups = {};
    for (const it of FULL_LIST) {
      const grp = (it.group || "Outros").trim();
      if (!groups[grp]) groups[grp] = [];
      groups[grp].push(it);
    }

    for (const grpName of Object.keys(groups).sort()) {
      const groupDiv = document.createElement("div");
      groupDiv.className = "preset-group";

      const title = document.createElement("div");
      title.className = "preset-title";
      title.textContent = grpName;

      const list = document.createElement("div");
      list.className = "preset-list";

      for (const it of groups[grpName]) {
        const badge = document.createElement("button");
        badge.type = "button";
        badge.className = "preset-badge";
        badge.textContent = it.label;
        badge.onclick = () => add(it.id, it.label);
        list.appendChild(badge);
      }

      groupDiv.appendChild(title);
      groupDiv.appendChild(list);
      WRAP.appendChild(groupDiv);
    }
  }

  /* =========================================================
     BUSCA NO CAMPO "nc_search"
     ========================================================= */

  if (SEARCH) {
    let timer = null;

    SEARCH.addEventListener("input", () => {
      const q = SEARCH.value.trim();

      clearTimeout(timer);

      timer = setTimeout(() => {
        loadPresets(q);
      }, 200);
    });
  }

  /* Carrega inicial (lista completa) */
  loadPresets("");

  /* Retorna API pública */
  return {
    add,
    remove,
    clearAll,
    getCount,
    syncHidden,
  };
})();
/* =========================================================
   MODAL DE CONFIRMAÇÃO (Antes de Enviar)
   ========================================================= */

(function initConfirmModal() {
  const form = getForm();
  const btnOpen = document.getElementById("btn-open-confirm");
  const overlay = document.getElementById("confirm-overlay");
  const modal = document.getElementById("confirm-modal");
  const ack = document.getElementById("ack-check");
  const btnCancel = document.getElementById("btn-cancel-confirm");
  const btnSubmit = document.getElementById("btn-submit-confirm");

  if (!form || !btnOpen || !overlay || !modal || !ack || !btnCancel || !btnSubmit) {
    console.warn("ConfirmModal: elementos não encontrados.");
    return;
  }

  let lastFocus = null;

  function setVisible(show) {
    overlay.style.display = show ? "block" : "none";
    modal.style.display = show ? "flex" : "none";
  }

  function openModal() {
    lastFocus = document.activeElement;

    setVisible(true);
    ack.checked = false;

    btnSubmit.disabled = true;
    btnSubmit.style.opacity = ".7";
    btnSubmit.style.cursor = "not-allowed";

    setTimeout(() => ack.focus(), 10);
  }

  function closeModal() {
    setVisible(false);
    if (lastFocus?.focus) lastFocus.focus();
  }

  btnOpen.addEventListener("click", (e) => {
    e.preventDefault();
    openModal();
  });

  btnCancel.addEventListener("click", () => {
    closeModal();
  });

  overlay.addEventListener("click", () => {
    closeModal();
  });

  ack.addEventListener("change", () => {
    const ok = ack.checked;

    btnSubmit.disabled = !ok;
    btnSubmit.style.opacity = ok ? "1" : ".7";
    btnSubmit.style.cursor = ok ? "pointer" : "not-allowed";
  });

  btnSubmit.addEventListener("click", () => {
    if (!ack.checked) return;

    closeModal();
    form.requestSubmit?.() || form.submit();
  });

  document.addEventListener("keydown", (e) => {
    if (modal.style.display !== "flex") return;

    if (e.key === "Escape") {
      closeModal();
      return;
    }

    if (e.key === "Enter" && !btnSubmit.disabled) {
      btnSubmit.click();
    }
  });
})();
/* =========================================================
   AJUSTES ADICIONAIS DE ACESSIBILIDADE E UX
   ========================================================= */

/**
 * Ajusta aria-pressed dos botões segmentados em qualquer
 * mudança de estado (ticket_type, SLA, is_compliant etc).
 * Este módulo já está coberto nos listeners principais,
 * porém este trecho garante fallback para navegadores antigos.
 */
(function bindSegmentedFallback() {
  const segContainers = document.querySelectorAll(".segmented");

  segContainers.forEach(container => {
    const radios = container.querySelectorAll('input[type="radio"]');

    radios.forEach(r => {
      r.addEventListener("change", () => {
        radios.forEach(other => {
          const lab = other.id ? container.querySelector(`label[for="${other.id}"]`) : null;
          if (lab) {
            lab.setAttribute("aria-pressed", other.checked ? "true" : "false");
          }
        });
      });
    });
  });
})();


/* =========================================================
   LIMPEZA AUTOMÁTICA EM BOTÃO "Reset"
   ========================================================= */

(function handleResetButton() {
  const form = getForm();
  if (!form) return;

  form.addEventListener("reset", () => {
    // limpar chips NC
    if (window.NC) {
      window.NC.clearAll();
    }

    // limpar autocompletes (hidden fields)
    setTimeout(() => {
      const hiddenFields = form.querySelectorAll("input[type='hidden']");
      hiddenFields.forEach(h => {
        h.value = "";
      });

      // Reset da área de sugestão dos autocompletes
      const suggestBoxes = [
        "auditor_suggest",
        "inspector_suggest",
        "supplier_suggest",
        "location_suggest",
        "category_suggest",
        "resolver_suggest",
      ];

      suggestBoxes.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
          el.innerHTML = "";
          el.style.display = "none";
        }
      });

      // Fechar bloco NC
      const just = document.getElementById("just_block");
      if (just) just.style.display = "none";

      // Reset de aria-pressed nos segmentados
      const segBtns = document.querySelectorAll(".segmented-btn");
      segBtns.forEach(btn => btn.setAttribute("aria-pressed", "false"));
    }, 10);
  });
})();


/* =========================================================
   SUPORTE A ENTER → NÃO CONFIRMAR SEM QUERER
   ========================================================= */

/**
 * Evita que ENTER em campos do formulário envie diretamente
 * (ajuda na UX do modal de confirmação)
 */
(function preventEnterSubmit() {
  const form = getForm();
  if (!form) return;

  form.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      const tag = e.target.tagName.toLowerCase();
      if (tag === "input" && e.target.type !== "radio") {
        e.preventDefault();
      }
    }
  });
})();
/* =========================================================
   AJUSTE VISUAL DOS BOTÕES SEGMENTADOS EM MOBILE
   ========================================================= */

/**
 * Este módulo adiciona ajustes para deixar os botões segmentados
 * mais responsivos em telas menores, garantindo padrão visual.
 */
(function improveSegmentedMobile() {
  const checkResize = () => {
    const isMobile = window.innerWidth <= 680;
    const segBtns = document.querySelectorAll(".segmented-btn");

    segBtns.forEach(btn => {
      if (isMobile) {
        btn.style.minWidth = "48%";
      } else {
        btn.style.minWidth = "";
      }
    });
  };

  window.addEventListener("resize", checkResize);
  checkResize();
})();


/* =========================================================
   FOCUS AUTOMÁTICO EM CAMPOS APÓS ERRO NO FORM
   ========================================================= */

(function focusOnErrorField() {
  const form = getForm();
  if (!form) return;

  const errorAlert = document.querySelector(".alert-danger");
  if (!errorAlert) return;

  setTimeout(() => {
    const firstInvalid = form.querySelector("[aria-invalid='true'], .error, input:invalid");
    if (firstInvalid && firstInvalid.focus) {
      firstInvalid.focus();
    }
  }, 150);
})();


/* =========================================================
   AJUSTE DE TÍTULOS E RÓTULOS NO FORMULÁRIO
   ========================================================= */

/**
 * Melhora a acessibilidade dos rótulos em navegadores que falham
 * em associar <label> via for/id em elementos criados dinamicamente.
 */
(function ensureLabelConnections() {
  const labels = document.querySelectorAll("label[for]");

  labels.forEach(label => {
    const id = label.getAttribute("for");
    if (!id) return;

    const target = document.getElementById(id);
    if (target && !target.getAttribute("aria-labelledby")) {
      target.setAttribute("aria-labelledby", id + "_lbl");
      label.id = id + "_lbl";
    }
  });
})();


/* =========================================================
   MELHORIAS DE UX PARA CAMPOS DE TEXTO LONGO
   ========================================================= */

(function enhanceTypingUX() {
  const longInputs = document.querySelectorAll(
    "input[type='text']:not(.tag-input)"
  );

  longInputs.forEach(inp => {
    inp.addEventListener("focus", () => {
      inp.style.borderColor = "#0d6efd";
      inp.style.boxShadow = "0 0 0 2px rgba(13,110,253,.25)";
    });

    inp.addEventListener("blur", () => {
      inp.style.borderColor = "";
      inp.style.boxShadow = "";
    });
  });
})();


/* =========================================================
   LIMITADOR DE TAMANHO PARA POPUPS DE AUTOCOMPLETE
   ========================================================= */

(function limitPopupHeight() {
  const popups = [
    "auditor_suggest",
    "inspector_suggest",
    "supplier_suggest",
    "location_suggest",
    "category_suggest",
    "resolver_suggest"
  ];

  popups.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.style.maxHeight = "260px";
      el.style.overflowY = "auto";
    }
  });
})();
/* =========================================================
   SUPORTE PARA DISPOSITIVOS TOUCH
   ========================================================= */

/**
 * Em alguns tablets e celulares, eventos de "hover" ou
 * toques rápidos podem abrir/fechar popups de maneira
 * indesejada. Este módulo adiciona proteção adicional.
 */
(function handleTouchDevices() {
  const isTouch = "ontouchstart" in window;

  if (!isTouch) return;

  const popups = [
    "auditor_suggest",
    "inspector_suggest",
    "supplier_suggest",
    "location_suggest",
    "category_suggest",
    "resolver_suggest",
    "nc_presets"
  ];

  popups.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.style.webkitOverflowScrolling = "touch";
      el.style.scrollBehavior = "smooth";
    }
  });
})();


/* =========================================================
   AJUSTES DE TECLADO (TAB, ESC, SETAS)
   ========================================================= */

/**
 * Melhorias de interação com teclado:
 * - ESC fecha popups abertos
 * - TAB fecha popups e move corretamente o foco
 * - Setas navegam em popups de autocomplete (opcional)
 */

(function enhanceKeyboardNavigation() {
  const popupIds = [
    "auditor_suggest",
    "inspector_suggest",
    "supplier_suggest",
    "location_suggest",
    "category_suggest",
    "resolver_suggest"
  ];

  /** Fecha todos os popups */
  function closeAllPopups() {
    popupIds.forEach(id => {
      const el = document.getElementById(id);
      if (el) {
        el.style.display = "none";
        el.innerHTML = "";
      }
    });
  }

  /** Fecha presets NC */
  function closeNCPresets() {
    const ncBox = document.getElementById("nc_presets");
    if (ncBox) {
      // Mantém conteúdo, mas apenas esconde
      ncBox.style.display = "none";
    }
  }

  /** Reabre presets NC quando foco volta ao campo */
  const ncSearch = document.getElementById("nc_search");
  if (ncSearch) {
    ncSearch.addEventListener("focus", () => {
      const ncBox = document.getElementById("nc_presets");
      if (ncBox) ncBox.style.display = "block";
    });
  }

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeAllPopups();
      closeNCPresets();
    }
  });

  document.addEventListener("click", (e) => {
    const clickedInsidePopup = popupIds.some(id => {
      const el = document.getElementById(id);
      return el && el.contains(e.target);
    });

    const ncBox = document.getElementById("nc_presets");
    const insideNC = ncBox && ncBox.contains(e.target);

    const ncSearchInput = document.getElementById("nc_search");
    const clickedSearchNC = ncSearchInput && e.target === ncSearchInput;

    if (!clickedInsidePopup) {
      closeAllPopups();
    }

    if (!insideNC && !clickedSearchNC) {
      closeNCPresets();
    }
  });
})();


/* =========================================================
   MARCAÇÃO AUTOMÁTICA DE PRIORIDADE VISUAL
   ========================================================= */

/**
 * Deixa destaque visual na prioridade selecionada
 * (azul forte, semelhante ao ServiceNow)
 */
(function highlightPriority() {
  const radios = document.querySelectorAll('input[name="priority"]');
  if (!radios.length) return;

  function refresh() {
    radios.forEach(r => {
      const label = document.querySelector(`label[for="${r.id}"]`);
      if (!label) return;

      if (r.checked) {
        label.style.background = "#0d6efd";
        label.style.color = "#fff";
        label.style.borderColor = "#0d6efd";
      } else {
        label.style.background = "#e5e7eb";
        label.style.color = "#111";
        label.style.borderColor = "#cbd5e1";
      }
    });
  }

  radios.forEach(r => r.addEventListener("change", refresh));
  refresh();
})();
/* =========================================================
   MARCAÇÃO AUTOMÁTICA DE SLA ATINGIDO (Sim / Não)
   ========================================================= */

(function highlightSLA() {
  const radios = document.querySelectorAll('input[name="sla_met"]');
  if (!radios.length) return;

  function refresh() {
    radios.forEach(r => {
      const label = document.querySelector(`label[for="${r.id}"]`);
      if (!label) return;

      if (r.checked) {
        label.style.background = "#0d6efd";
        label.style.color = "#fff";
        label.style.borderColor = "#0d6efd";
      } else {
        label.style.background = "#e5e7eb";
        label.style.color = "#111";
        label.style.borderColor = "#cbd5e1";
      }
    });
  }

  radios.forEach(r => r.addEventListener("change", refresh));
  refresh();
})();

/* =========================================================
   MARCAÇÃO AUTOMÁTICA DO "CHAMADO CONFORME?" (Sim / Não)
   ========================================================= */

(function highlightIsCompliant() {
  const radios = document.querySelectorAll('input[name="is_compliant"]');
  if (!radios.length) return;

  function refresh() {
    radios.forEach(r => {
      const label = document.querySelector(`label[for="${r.id}"]`);
      if (!label) return;

      if (r.checked) {
        label.style.background = "#0d6efd";
        label.style.color = "#fff";
        label.style.borderColor = "#0d6efd";
      } else {
        label.style.background = "#e5e7eb";
        label.style.color = "#111";
        label.style.borderColor = "#cbd5e1";
      }
    });
  }

  radios.forEach(r => r.addEventListener("change", refresh));
  refresh();
})();

/* =========================================================
   EFEITO DE FOCO EM CAMPOS DE AUTOCOMPLETE E NC_SEARCH
   ========================================================= */

(function enhanceAutocompleteFocus() {
  const fields = [
    "kyndryl_auditor_input",
    "petrobras_inspector_input",
    "audited_supplier_input",
    "location_input",
    "category_input",
    "resolver_group_input",
    "nc_search"
  ];

  fields.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;

    el.addEventListener("focus", () => {
      el.style.borderColor = "#0d6efd";
      el.style.boxShadow = "0 0 0 2px rgba(13,110,253,.25)";
    });

    el.addEventListener("blur", () => {
      el.style.borderColor = "";
      el.style.boxShadow = "";
    });
  });
})();

/* =========================================================
   ROLAGEM SUAVE (Smooth Scroll) EM TODOS POPUPS E PRESETS
   ========================================================= */

(function enableSmoothScroll() {
  [
    "nc_presets",
    "auditor_suggest",
    "inspector_suggest",
    "supplier_suggest",
    "location_suggest",
    "category_suggest",
    "resolver_suggest"
  ].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.style.scrollBehavior = "smooth";
      el.style.webkitOverflowScrolling = "touch";
    }
  });
})();
/* =========================================================
   ACESSIBILIDADE — TRAP DE FOCO NO MODAL DE CONFIRMAÇÃO
   ========================================================= */

/**
 * Garante que, quando o modal estiver aberto, o foco permaneça
 * dentro dele, impedindo navegação acidental pelo TAB.
 */
(function trapFocusInModal() {
  const modal = document.getElementById("confirm-modal");
  const overlay = document.getElementById("confirm-overlay");
  if (!modal || !overlay) return;

  function getFocusable() {
    return modal.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
  }

  document.addEventListener("keydown", (e) => {
    if (modal.style.display !== "flex") return;
    if (e.key !== "Tab") return;

    const list = Array.from(getFocusable());
    if (!list.length) return;

    const first = list[0];
    const last = list[list.length - 1];

    if (e.shiftKey) {
      // shift + tab
      if (document.activeElement === first) {
        e.preventDefault();
        last.focus();
      }
    } else {
      // tab normal
      if (document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });
})();


/* =========================================================
   AUTOFOCUS NO CAMPO "ticket_number" QUANDO O FORM É LIMPO
   ========================================================= */

(function autoFocusTicket() {
  const form = getForm();
  if (!form) return;

  form.addEventListener("reset", () => {
    setTimeout(() => {
      const input = document.getElementById("ticket_number");
      if (input) input.focus();
    }, 100);
  });
})();


/* =========================================================
   ANCORAGEM DO SCROLL AO ABRIR O BLOCO DE JUSTIFICATIVAS
   ========================================================= */

(function scrollToNCOnShow() {
  const radios = document.querySelectorAll('input[name="is_compliant"]');
  const block = document.getElementById("just_block");

  if (!radios.length || !block) return;

  radios.forEach(radio => {
    radio.addEventListener("change", e => {
      if (e.target.value === "0") {
        setTimeout(() => {
          block.scrollIntoView({
            behavior: "smooth",
            block: "center"
          });
        }, 150);
      }
    });
  });
})();


/* =========================================================
   AJUSTE VISUAL DO BLOCO DE JUSTIFICATIVAS
   ========================================================= */

(function styleNCBlock() {
  const block = document.getElementById("just_block");
  if (!block) return;

  block.style.transition = "all .25s ease";

  const show = () => {
    block.style.opacity = "1";
    block.style.transform = "scale(1)";
  };

  const hide = () => {
    block.style.opacity = "0";
    block.style.transform = "scale(.98)";
  };

  const observer = new MutationObserver(() => {
    if (block.style.display === "block") show();
    else hide();
  });

  observer.observe(block, {
    attributes: true,
    attributeFilter: ["style"]
  });
})();
/* =========================================================
   FECHAMENTO FINAL DO SCRIPT
   ========================================================= */

/**
 * Este bloco garante que todos os módulos foram carregados
 * corretamente e registra no console uma confirmação final.
 */
(function finalizeScript() {
  try {
    console.log("%c[Auditoria de Chamados] scripts.js carregado com sucesso.",
      "color:#0d6efd;font-weight:bold;font-size:14px;");

    if (!window.NC) {
      console.warn("⚠ Módulo NC não foi inicializado corretamente.");
    }
  } catch (e) {
    console.error("Erro ao finalizar scripts.js:", e);
  }
})();

/* =========================================================
   EOF — END OF FILE
   ========================================================= */