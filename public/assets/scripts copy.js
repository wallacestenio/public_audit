/**
 * scripts.js — Auditoria de Chamados (FINAL)
 * Mantém TODAS as funcionalidades e adiciona:
 * - Validação visual ServiceNow (✔/✖)
 * - Botão “Abrir no ServiceNow”
 * - Normalização INC/RITM/SCTASK
 * - Manutenção total de autocomplete, modal, NC, duplicidade, etc.
 */

/* ===================== Utils base ===================== */
function getForm() {
  return document.querySelector(
    'form[action$="/audit-entries"], form[action$="/audit-estoque"]'
  );
}
function baseJoin(path) {
  const base = (window.APP_BASE || '').replace(/\/+$/, '');
  return base + path;
}
function getCatalogHeaders() {
  const headers = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  };
  if (window.CATALOG_TOKEN) headers['X-Form-Token'] = window.CATALOG_TOKEN;
  return headers;
}

/* ===================== Export ===================== */
window.exportByMonth = function () {
  const m = (document.querySelector('input[name="audit_month"]')?.value || '').trim();
  if (!m) return;
  window.location.href =
    baseJoin('/export/csv') + '?audit_month=' + encodeURIComponent(m);
};

/* ===================== Sanfona Export ===================== */
(function () {
  const toggle = document.getElementById('export-toggle');
  const panel = document.getElementById('export-panel');
  if (!toggle || !panel) return;

  const KEY = 'exportPanelOpen';
  function setOpen(open) {
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    open ? panel.removeAttribute('hidden') : panel.setAttribute('hidden', '');
    try {
      localStorage.setItem(KEY, open ? '1' : '0');
    } catch {}
  }

  toggle.addEventListener('click', () => {
    const curr = toggle.getAttribute('aria-expanded') === 'true';
    setOpen(!curr);
  });

  let startOpen = false;
  try {
    startOpen = localStorage.getItem(KEY) === '1';
  } catch {}
  setOpen(startOpen);
})();

/* ===================== Banner success ===================== */
(function () {
  const el = document.getElementById('countdown');
  if (!el) return;
  let timeLeft = parseInt(el.textContent || '5', 10);
  const t = setInterval(() => {
    timeLeft--;
    el.textContent = String(timeLeft);
    if (timeLeft <= 0) {
      clearInterval(t);
      try {
        const url = new URL(window.location.href);
        url.searchParams.delete('created');
        window.history.replaceState(
          {},
          '',
          url.pathname +
            (url.searchParams.toString()
              ? '?' + url.searchParams.toString()
              : '')
        );
      } catch {}
      document.querySelector('input[name="ticket_number"]')?.focus();
    }
  }, 1000);
})();

/* ===================== Ticket + Tipo + Validação ServiceNow ===================== */
(function () {
  const tn = document.getElementById('ticket_number');
  if (!tn) return;

  const radios = Array.from(
    document.querySelectorAll('input[name="ticket_type"]')
  );
  const prefixMap = { INC: 'INCIDENTE', RITM: 'REQUISIÇÃO', SCTASK: 'TASK' };

  /* ------ Construir UI (ícone ✔/✖ + botão Abrir SNOW) ------ */
  const wrapper = document.createElement('div');
  wrapper.style.display = 'flex';
  wrapper.style.alignItems = 'center';
  wrapper.style.gap = '8px';

  tn.parentElement.insertBefore(wrapper, tn);
  wrapper.appendChild(tn);

  const icon = document.createElement('span');
  icon.style.fontSize = '18px';
  icon.style.minWidth = '20px';
  icon.style.textAlign = 'center';
  wrapper.appendChild(icon);

  const link = document.createElement('a');
  link.textContent = 'Abrir no ServiceNow';
  link.target = '_blank';
  link.style.display = 'none';
  link.style.fontSize = '12px';
  link.style.color = '#2563eb';
  wrapper.appendChild(link);

  /* ------ Atualiza estado visual ------ */
  function setState(ok, url) {
    tn.style.borderColor = ok ? '#16a34a' : '#dc2626';
    icon.textContent = ok ? '✔' : '✖';
    icon.style.color = ok ? '#16a34a' : '#dc2626';

    if (ok && url) {
      link.href = url;
      link.style.display = 'inline';
    } else {
      link.style.display = 'none';
    }
  }

  /* ------ Atualiza radiobutton visual ------ */
  function updateAria() {
    radios.forEach((r) => {
      const lab = r.id ? document.querySelector(`label[for="${r.id}"]`) : null;
      if (lab) lab.setAttribute('aria-pressed', r.checked ? 'true' : 'false');
    });
  }

  /* ------ Selecionar tipo pelo prefixo INC/RITM/SCTASK ------ */
  function selectType(v) {
    const m = /^(INC|RITM|SCTASK)/.exec(v);
    if (!m) return;
    const t = prefixMap[m[1]];
    radios.forEach((r) => (r.checked = String(r.value).toUpperCase() === t));
    updateAria();
  }

  /* ------ Normaliza campo ------ */
  function normalize() {
    let v = (tn.value || '').toUpperCase().replace(/\s+/g, '');
    tn.value = v;

    const ok = /^(INC|RITM|REQ|SCTASK|TASK)\d+$/.test(v);
    tn.setCustomValidity(
      ok ? '' : 'O ticket deve iniciar com INC, RITM ou SCTASK + dígitos.'
    );

    if (ok) selectType(v);
    return ok ? v : '';
  }

  /* ------ Validação ServiceNow via API local ------ */
  async function checkSN(v) {
    try {
      const res = await fetch(
        baseJoin('/api/check-ticket?number=' + encodeURIComponent(v))
      );
      const data = await res.json();

      if (data.exists) {
        setState(true, data.url || data.redirect);
        return true;
      }

      setState(false);
      return false;
    } catch {
      // Em erro de rede, não travar o usuário
      return true;
    }
  }

  
(async () => {
  const input    = document.getElementById('ticket_number');
  const feedback = document.getElementById('ticket-feedback');
  const saveBtn  = document.getElementById('btn-open-confirm');

  if (!input || !feedback || !saveBtn) return;

  let timer = null;
  let lastValue = '';

  input.addEventListener('input', () => {
    clearTimeout(timer);

    const value = input.value.trim();
    feedback.style.display = 'none';
    feedback.textContent = '';

    saveBtn.disabled = false;
    saveBtn.style.opacity = '';
    saveBtn.style.pointerEvents = '';

    if (value.length < 8 || value === lastValue) return;

    timer = setTimeout(() => validateTicket(value), 400);
  });

  async function validateTicket(number) {
    lastValue = number;

    try {
      const base  = '<?= htmlspecialchars($base, ENT_QUOTES, "UTF-8") ?>';
      const token = '<?= htmlspecialchars($form_token_catalog ?? "", ENT_QUOTES, "UTF-8") ?>';

      const res = await fetch(
        `${base}/api/validate/ticket?number=${encodeURIComponent(number)}`,
        {
          method: 'GET',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-Form-Token': token,
            'Accept': 'application/json'
          },
          credentials: 'same-origin'
        }
      );

      if (!res.ok) return;

      const data = await res.json();

      if (data.invalid) return;

      if (data.duplicate) {
        feedback.textContent = '⚠️ Este chamado já está cadastrado.';
        feedback.style.display = 'block';
        feedback.style.color = '#dc2626';

        saveBtn.disabled = true;
        saveBtn.style.opacity = '0.6';
        saveBtn.style.pointerEvents = 'none';
      }

    } catch (err) {
      console.error('Falha ao validar ticket:', err);
    }
  }
})();



  /* ------ Eventos ------ */
  tn.addEventListener('input', () => {
    tn.style.borderColor = '';
    icon.textContent = '';
    link.style.display = 'none';
    normalize();
  });

  tn.addEventListener('blur', async () => {
    const v = normalize();
    if (v) await checkSN(v);
  });

  /* ------ No submit, validar tudo ------ */
  const form = getForm();
  if (form) {
    form.addEventListener('submit', async (e) => {
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

/* ===================== audit_month normalizer ===================== */
(function () {
  const m = document.querySelector('input[name="audit_month"]');
  if (!m) return;

  const map = {
    jan: '01', janeiro: '01',
    fev: '02', fevereiro: '02',
    mar: '03', março: '03', marco: '03',
    abr: '04', abril: '04',
    mai: '05', maio: '05',
    jun: '06', junho: '06',
    jul: '07', julho: '07',
    ago: '08', agosto: '08',
    set: '09', setembro: '09',
    out: '10', outubro: '10',
    nov: '11', novembro: '11',
    dez: '12', dezembro: '12'
  };

  function norm(v) {
    v = (v || '').trim().toLowerCase();
    if (/^\d{4}-(0[1-9]|1[0-2])$/.test(v)) return v;

    const m1 = v.match(/^(0?[1-9]|1[0-2])\s*\/\s*(\d{4})$/);
    if (m1) return `${m1[2]}-${String(m1[1]).padStart(2, '0')}`;

    const m2 = v.match(/^([a-zçõ]+)\s+(\d{4})$/);
    if (m2 && map[m2[1]]) return `${m2[2]}-${map[m2[1]]}`;

    if (map[v]) return `${new Date().getFullYear()}-${map[v]}`;
    return v;
  }

  m.addEventListener('blur', () => (m.value = norm(m.value)));
})();
/* ===================== Autocomplete helper (genérico) ===================== */
function makeAutocomplete(opts) {
  const {
    inputId,
    hiddenNameId,
    hiddenIdId,
    popupId,
    resource,
    nameFallbacks = ['name', 'label']
  } = opts;

  const input = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenNameId);
  const hidId = document.getElementById(hiddenIdId);
  const popup = document.getElementById(popupId);

  if (!input || !hidden || !hidId || !popup) return;

  const isLocked = input.hasAttribute('data-locked');
  if (isLocked) {
    hidden.value = input.value || '';
    return;
  }

  let timer = null;
  let cache = [];

  function closePopup() {
    popup.style.display = 'none';
    popup.innerHTML = '';
  }
  function openPopup() {
    popup.style.display = 'block';
  }
  function clearSelection() {
    hidden.value = '';
    hidId.value = '';
  }

  function getNameOf(it) {
    for (const k of nameFallbacks) {
      if (it && typeof it[k] === 'string' && it[k].trim() !== '') return it[k];
    }
    return '';
  }

  function selectItem(it) {
    const name = getNameOf(it);
    input.value = name;
    hidden.value = name;
    hidId.value = String(it?.id ?? '');
    closePopup();
  }

  function renderList(items) {
    popup.innerHTML = '';
    if (!items?.length) return closePopup();

    const list = document.createElement('div');
    list.style.display = 'flex';
    list.style.flexDirection = 'column';

    items.forEach((it) => {
      const name = getNameOf(it);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'preset-badge';
      btn.style.textAlign = 'left';
      btn.style.margin = '4px';
      btn.textContent = name;
      btn.onclick = () => selectItem(it);
      list.appendChild(btn);
    });

    popup.appendChild(list);
    openPopup();
  }

  async function fetchList(q) {
    try {
      const res = await fetch(
        baseJoin(`/api/catalog?resource=${encodeURIComponent(resource)}&q=` + encodeURIComponent(q || '')),
        { headers: getCatalogHeaders() }
      );

      if (!res.ok) throw new Error('Fetch ' + res.status);

      const data = await res.json();
      cache = Array.isArray(data) ? data : [];
      renderList(cache);
    } catch {
      cache = [];
      closePopup();
    }
  }

  input.addEventListener('input', () => {
    clearSelection();
    const q = input.value.trim();
    clearTimeout(timer);
    if (q.length < 2) return closePopup();
    timer = setTimeout(() => fetchList(q), 180);
  });

  input.addEventListener('focus', () => {
    const q = input.value.trim();
    if (q.length >= 2) fetchList(q);
  });

  document.addEventListener('click', (e) => {
    if (!popup.contains(e.target) && e.target !== input) closePopup();
  });

  const form = getForm();
  if (form) {
    form.addEventListener('submit', (e) => {
      const vis = (input.value || '').trim();
      const sel = (hidden.value || '').trim();
      if (!vis || !sel || vis !== sel) {
        e.preventDefault();
        input.focus();
      }
    });
  }
}

/* ===================== Autocompletes finais ===================== */
makeAutocomplete({
  inputId: 'kyndryl_auditor_input',
  hiddenNameId: 'kyndryl_auditor_value',
  hiddenIdId: 'kyndryl_auditor_id',
  popupId: 'auditor_suggest',
  resource: 'kyndryl-auditors',
  nameFallbacks: ['name', 'label', 'kyndryl_auditor']
});

makeAutocomplete({
  inputId: 'petrobras_inspector_input',
  hiddenNameId: 'petrobras_inspector_value',
  hiddenIdId: 'petrobras_inspector_id',
  popupId: 'inspector_suggest',
  resource: 'petrobras-inspectors',
  nameFallbacks: ['name', 'label', 'petrobras_inspector']
});

makeAutocomplete({
  inputId: 'audited_supplier_input',
  hiddenNameId: 'audited_supplier_value',
  hiddenIdId: 'audited_supplier_id',
  popupId: 'supplier_suggest',
  resource: 'audited-suppliers',
  nameFallbacks: ['name', 'label', 'audited_supplier']
});

makeAutocomplete({
  inputId: 'location_input',
  hiddenNameId: 'location_value',
  hiddenIdId: 'location_id',
  popupId: 'location_suggest',
  resource: 'locations',
  nameFallbacks: ['name', 'label', 'location']
});

makeAutocomplete({
  inputId: 'category_input',
  hiddenNameId: 'category_value',
  hiddenIdId: 'category_id',
  popupId: 'category_suggest',
  resource: 'categories',
  nameFallbacks: ['name', 'label', 'category']
});

makeAutocomplete({
  inputId: 'resolver_group_input',
  hiddenNameId: 'resolver_group_value',
  hiddenIdId: 'resolver_group_id',
  popupId: 'resolver_suggest',
  resource: 'resolver-groups',
  nameFallbacks: ['name', 'label', 'resolver_group']
});

/* ===================== Chamado Conforme - limpeza e controle ===================== */
(function () {
  const radios = Array.from(document.querySelectorAll('input[name="is_compliant"]'));
  const block = document.getElementById('just_block');
  if (!radios.length || !block) return;

  const yes = document.querySelector('input[name="is_compliant"][value="1"]');
  const no = document.querySelector('input[name="is_compliant"][value="0"]');

  function showBlock(show) {
    block.style.display = show ? 'block' : 'none';
  }
  function updateAria() {
    radios.forEach((r) => {
      const lab = r.id ? document.querySelector(`label[for="${r.id}"]`) : null;
      if (lab) lab.setAttribute('aria-pressed', r.checked ? 'true' : 'false');
    });
  }

  function setInitial() {
    showBlock(document.querySelector('input[name="is_compliant"]:checked')?.value === '0');
    updateAria();
  }

  radios.forEach((r) =>
    r.addEventListener('change', (e) => {
      const val = e.target.value;
      if (val === '1') {
        const hasNC = window.NC && window.NC.getCount() > 0;
        if (hasNC) {
          const ok = confirm(
            'Você marcou "Sim" para Conforme. As justificativas serão removidas. Continuar?'
          );
          if (ok) {
            window.NC.clearAll();
            document.getElementById('nc_ids').value = '';
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

/* ===================== Modal de Confirmação ===================== */
(function () {
  const form = getForm();
  const btnOpen = document.getElementById('btn-open-confirm');
  const overlay = document.getElementById('confirm-overlay');
  const modal = document.getElementById('confirm-modal');
  const ack = document.getElementById('ack-check');
  const btnCancel = document.getElementById('btn-cancel-confirm');
  const btnSubmit = document.getElementById('btn-submit-confirm');

  if (!form || !btnOpen || !overlay || !modal || !ack || !btnCancel || !btnSubmit) return;

  let lastFocus = null;

  function setVisible(show) {
    overlay.style.display = show ? 'block' : 'none';
    modal.style.display = show ? 'flex' : 'none';
  }

  function openModal() {
    lastFocus = document.activeElement;
    setVisible(true);
    ack.checked = false;
    btnSubmit.disabled = true;
    btnSubmit.style.opacity = '.7';
    setTimeout(() => ack.focus(), 10);
  }

  function closeModal() {
    setVisible(false);
    lastFocus?.focus?.();
  }

  btnOpen.addEventListener('click', (e) => {
    e.preventDefault();
    openModal();
  });
  btnCancel.addEventListener('click', closeModal);
  overlay.addEventListener('click', closeModal);

  ack.addEventListener('change', () => {
    const ok = ack.checked;
    btnSubmit.disabled = !ok;
    btnSubmit.style.opacity = ok ? '1' : '.7';
  });

  btnSubmit.addEventListener('click', () => {
    if (!ack.checked) return;
    closeModal();
    form.requestSubmit?.() || form.submit();
  });

  document.addEventListener('keydown', (e) => {
    if (modal.style.display !== 'flex') return;
    if (e.key === 'Escape') closeModal();
    if (e.key === 'Enter' && !btnSubmit.disabled) btnSubmit.click();
  });
})();

