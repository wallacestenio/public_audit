/**
 * scripts.js — Auditoria de Chamados
 * - Autocomplete (6 campos) puxando do /api/catalog (tabelas: kyndryl_auditors, petrobras_inspectors, audited_suppliers, locations, categories, resolver_groups)
 * - Sanfona de "Opções de exportação"
 * - Export por mês (global window.exportByMonth)
 * - Modal de confirmação
 * - Banner sucess + toggle de "Justificativas" + normalizadores
 */

/* ===== Utils base ===== */
function getForm() {
  return document.querySelector('form[action$="/audit-entries"]'); // funciona em subpasta
}
function baseJoin(path) {
  const base = (window.APP_BASE || '').replace(/\/+$/,'');
  return base + path;
}

/* ===== Export por mês ===== */
window.exportByMonth = function exportByMonth(){
  const m = (document.querySelector('input[name="audit_month"]')?.value || '').trim();
  if (!m) { alert('Preencha o campo "Mês da Auditoria" (ex.: 2026-02) para exportar por mês.'); return; }
  window.location.href = baseJoin('/export/csv') + '?audit_month=' + encodeURIComponent(m);
};

/* ===== SANFONA: Opções de exportação ===== */
(function(){
  const toggle = document.getElementById('export-toggle');
  const panel  = document.getElementById('export-panel');
  if (!toggle || !panel) return;

  const KEY = 'exportPanelOpen';

  function setOpen(open){
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) { panel.removeAttribute('hidden'); }
    else { panel.setAttribute('hidden',''); }
    try { localStorage.setItem(KEY, open ? '1' : '0'); } catch {}
  }

  toggle.addEventListener('click', () => {
    const curr = toggle.getAttribute('aria-expanded') === 'true';
    setOpen(!curr);
  });

  // Estado inicial (persistido)
  let startOpen = false;
  try { startOpen = localStorage.getItem(KEY) === '1'; } catch {}
  setOpen(startOpen);
})();

/* ===== Banner success (countdown) ===== */
(function(){
  const el = document.getElementById('countdown');
  if (!el) return;
  let timeLeft = parseInt(el.textContent || '5', 10);
  if (!Number.isFinite(timeLeft)) timeLeft = 5;

  const t = setInterval(()=>{
    timeLeft--;
    el.textContent = String(timeLeft);
    if (timeLeft <= 0) {
      clearInterval(t);
      try {
        const url = new URL(window.location.href);
        url.searchParams.delete('created');
        window.history.replaceState({}, '', url.pathname + (url.searchParams.toString() ? ('?' + url.searchParams.toString()) : ''));
      } catch {}
      const first = document.querySelector('input[name="ticket_number"]');
      if (first) first.focus();
    }
  }, 1000);
})();

/* ===== Toggle do bloco de justificativas ===== */
(function(){
  function getIsCompliant(){ const el=document.querySelector('input[name="is_compliant"]:checked'); return el ? el.value : '1'; }
  function toggleJust(){
    const show = getIsCompliant()==='0';
    const block=document.getElementById('just_block');
    if (block) block.style.display = show ? 'block' : 'none';
  }
  document.querySelectorAll('input[name="is_compliant"]').forEach(r => r.addEventListener('change', toggleJust));
  toggleJust();
})();

/* ===== TAG GROUP: busca / chips (IDs) para reasons ===== */
(function(){
  const input   = document.getElementById('nc_search');
  const presets = document.getElementById('nc_presets');
  const chips   = document.getElementById('nc_chips');
  const hidden  = document.getElementById('nc_ids');

  if (!input || !presets || !chips || !hidden) return;

  const selectedIds = new Set(
    (hidden.value || '').split(/[;,]/)
      .map(s => s.trim()).filter(Boolean)
      .map(x => parseInt(x, 10))
      .filter(Number.isInteger)
  );
  const idToLabel = new Map();
  let allReasons = [];
  let filtered   = [];

  function updateHidden() { hidden.value = Array.from(selectedIds).join(';'); }

  function renderChips() {
    chips.innerHTML = '';
    selectedIds.forEach(id => {
      const label = idToLabel.get(id) || `#${id}`;
      const chip = document.createElement('span');
      chip.className = 'tag-chip';
      chip.innerHTML = `<span>${label}</span><button class="x" title="Remover" aria-label="Remover ${label}">×</button>`;
      chip.querySelector('.x').onclick = () => { selectedIds.delete(id); updateHidden(); renderChips(); renderPresets(); };
      chips.appendChild(chip);
    });
  }

  function groupBy(arr, key) {
    const map = new Map();
    arr.forEach(it => {
      const k = (it[key] || 'Outros') || 'Outros';
      if (!map.has(k)) map.set(k, []);
      map.get(k).push(it);
    });
    return map;
  }

  function renderPresets() {
    presets.innerHTML = '';
    const toShow = filtered.length ? filtered : allReasons;
    if (!toShow.length) {
      presets.innerHTML = '<div class="muted" style="padding:4px 2px">Nenhuma justificativa encontrada.</div>';
      return;
    }
    const byGroup = groupBy(toShow, 'group');
    byGroup.forEach((items, groupName) => {
      const remain = items.filter(it => !selectedIds.has(it.id));
      if (!remain.length) return;

      const g = document.createElement('div');
      g.className = 'preset-group';

      const title = document.createElement('div');
      title.className = 'preset-title';
      title.textContent = groupName;
      g.appendChild(title);

      const list = document.createElement('div');
      list.className = 'preset-list';
      remain.forEach(it => {
        const badge = document.createElement('button');
        badge.type = 'button';
        badge.className = 'preset-badge';
        badge.textContent = it.label;
        badge.onclick = () => {
          selectedIds.add(it.id);
          updateHidden();
          renderChips();
          renderPresets();
        };
        list.appendChild(badge);
      });
      g.appendChild(list);

      presets.appendChild(g);
    });
  }

  function applyFilter(q) {
    const needle = (q || '').trim().toLowerCase();
    if (!needle) { filtered = []; renderPresets(); return; }
    filtered = allReasons.filter(it =>
      it.label.toLowerCase().includes(needle) || (it.group || 'Outros').toLowerCase().includes(needle)
    );
    renderPresets();
  }

  async function loadAll() {
    try {
      let res = await fetch(baseJoin('/api/catalog?resource=noncompliance-reasons'));
      if (!res.ok) res = await fetch(baseJoin('/api/catalog?resource=noncompliance-reasons&q='));
      if (!res.ok) throw new Error('Falha ao carregar presets');

      const data = await res.json();
      allReasons = (Array.isArray(data) ? data : [])
        .map(r => ({
          id: parseInt(r.id, 10),
          label: String(r.label ?? r.noncompliance_reason ?? '').trim(),
          group: String(r.group ?? 'Outros').trim() || 'Outros'
        }))
        .filter(r => Number.isInteger(r.id) && r.label.length > 0);

      idToLabel.clear();
      allReasons.forEach(r => idToLabel.set(r.id, r.label));

      renderChips();
      renderPresets();

    } catch (err) {
      presets.innerHTML = '<div class="muted" style="padding:4px 2px;color:#b91c1c">Não foi possível carregar as justificativas.</div>';
      console.error(err);
    }
  }

  input.addEventListener('input', () => applyFilter(input.value));
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      const first = presets.querySelector('.preset-badge');
      if (first) first.click();
    }
  });

  const form = getForm();
  if (form) {
    form.addEventListener('submit', (e) => {
      const isNC = (document.querySelector('input[name="is_compliant"]:checked')?.value === '0');
      if (isNC && selectedIds.size === 0) {
        e.preventDefault();
        alert('Selecione ao menos uma justificativa.');
        input.focus();
      }
    });
  }

  loadAll();
})();

/* ===== Normalizador do número do ticket e sincronização do tipo ===== */
(function(){
  const tn = document.getElementById('ticket_number');
  if (!tn) return;

  const radios = Array.from(document.querySelectorAll('input[name="ticket_type"]'));
  const prefixMap = { INC:'INCIDENTE', RITM:'REQUISIÇÃO', SCTASK:'TASK' };

  function updateAriaPressed() {
    radios.forEach(r => {
      const lab = r.id ? document.querySelector(`label[for="${r.id}"]`) : null;
      if (lab) lab.setAttribute('aria-pressed', r.checked ? 'true' : 'false');
    });
  }

  function selectTicketType(targetValue) {
    const wanted = String(targetValue || '').toUpperCase();
    radios.forEach(r => { r.checked = (String(r.value).toUpperCase() === wanted); });
    updateAriaPressed();
  }

  function normalizeTicket() {
    let v = (tn.value || '').toUpperCase();
    v = v.replace(/\s+/g, '');
    tn.value = v;

    const ok = /^(INC|RITM|SCTASK)\d{6,}$/.test(v);
    tn.setCustomValidity(ok ? '' :
      'O ticket deve iniciar com INC, RITM ou SCTASK seguido de dígitos. Ex.: INC1234567');

    const m = /^(INC|RITM|SCTASK)/.exec(v);
    if (m) {
      const prefix = m[1];
      const targetValue = prefixMap[prefix];
      if (targetValue) selectTicketType(targetValue);
    }
  }

  tn.addEventListener('input', normalizeTicket);
  tn.addEventListener('blur', normalizeTicket);

  const form = getForm();
  if (form) {
    form.addEventListener('submit', (e) => {
      normalizeTicket();
      if (!tn.checkValidity()) {
        e.preventDefault();
        tn.reportValidity();
        tn.focus();
      }
    });
  }

  normalizeTicket();
})();

/* ===== Normalizador de audit_month ===== */
(function(){
  const m = document.querySelector('input[name="audit_month"]');
  if (!m) return;

  function norm(v){
    if (!v) return '';
    v = v.trim().toLowerCase();

    const map = {
      'jan':'01','janeiro':'01',
      'fev':'02','fevereiro':'02',
      'mar':'03','março':'03','marco':'03',
      'abr':'04','abril':'04',
      'mai':'05','maio':'05',
      'jun':'06','junho':'06',
      'jul':'07','julho':'07',
      'ago':'08','agosto':'08',
      'set':'09','setembro':'09',
      'out':'10','outubro':'10',
      'nov':'11','novembro':'11',
      'dez':'12','dezembro':'12'
    };

    if (/^\d{4}-(0[1-9]|1[0-2])$/.test(v)) return v;

    let m1 = v.match(/^(0?[1-9]|1[0-2])\s*\/\s*(\d{4})$/);
    if (m1) return `${m1[2]}-${String(m1[1]).padStart(2,'0')}`;

    let m2 = v.match(/^([a-zçõ]+)\s+(\d{4})$/);
    if (m2 && map[m2[1]]) return `${m2[2]}-${map[m2[1]]}`;

    if (map[v]) {
      const year = new Date().getFullYear();
      return `${year}-${map[v]}`;
    }
    return v;
  }

  m.addEventListener('blur', ()=>{ m.value = norm(m.value); });
})();

/* ===== Autocomplete helper (genérico) ===== */
function makeAutocomplete(opts){
  const { inputId, hiddenNameId, hiddenIdId, popupId, resource, nameFallbacks=['name','label'] } = opts;

  const input  = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenNameId);
  const hidId  = document.getElementById(hiddenIdId);
  const popup  = document.getElementById(popupId);

  if (!input || !hidden || !hidId || !popup) return;

  let timer = null;
  let cache = [];

  function closePopup(){ popup.style.display='none'; popup.innerHTML=''; }
  function openPopup(){ popup.style.display='block'; }
  function clearSelection(){ hidden.value=''; hidId.value=''; }

  function getNameOf(it){
    for (const k of nameFallbacks) {
      if (it && typeof it[k] === 'string' && it[k].trim() !== '') return it[k];
    }
    return '';
  }

  function selectItem(it){
    const name = getNameOf(it);
    input.value  = name;
    hidden.value = name;
    hidId.value  = String(it?.id ?? '');
    closePopup();
  }

  function renderList(items){
    popup.innerHTML = '';
    if (!items || !items.length) { closePopup(); return; }

    const list = document.createElement('div');
    list.style.display = 'flex';
    list.style.flexDirection = 'column';

    items.forEach(it => {
      const name = getNameOf(it);
      const btn  = document.createElement('button');
      btn.type   = 'button';
      btn.className = 'preset-badge';
      btn.style.textAlign = 'left';
      btn.style.margin    = '4px';
      btn.textContent = name;
      btn.title      = name;
      btn.onclick    = () => selectItem(it);
      list.appendChild(btn);
    });

    popup.appendChild(list);
    openPopup();
  }

  async function fetchList(q){
    try {
      const url = baseJoin(`/api/catalog?resource=${encodeURIComponent(resource)}&q=` + encodeURIComponent(q || ''));
      const res = await fetch(url, { headers: { 'Accept':'application/json' } });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      cache = Array.isArray(data) ? data : [];
      renderList(cache);
    } catch(e){ cache=[]; closePopup(); console.error(e); }
  }

  input.addEventListener('input', () => {
    clearSelection();
    const q = input.value.trim();
    clearTimeout(timer);
    if (q.length < 2) { closePopup(); return; }
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
      if (vis === '' || sel === '') {
        e.preventDefault();
        alert('Selecione um valor válido da lista.');
        input.focus();
        return;
      }
      if (vis !== sel) {
        e.preventDefault();
        alert('O valor informado não corresponde a uma opção válida. Selecione na lista.');
        input.focus();
        return;
      }
    });
  }
}

/* ===== Autocompletes finais (campos do form) ===== */

makeAutocomplete({
  inputId:'kyndryl_auditor_input',
  hiddenNameId:'kyndryl_auditor_value',
  hiddenIdId:'kyndryl_auditor_id',
  popupId:'auditor_suggest',
  resource:'kyndryl-auditors',
  nameFallbacks:['name','label','kyndryl_auditor','auditor_name','full_name']
});

makeAutocomplete({
  inputId:'petrobras_inspector_input',
  hiddenNameId:'petrobras_inspector_value',
  hiddenIdId:'petrobras_inspector_id',
  popupId:'inspector_suggest',
  resource:'petrobras-inspectors',
  nameFallbacks:['name','label','petrobras_inspector','inspector_name','full_name']
});

makeAutocomplete({
  inputId:'audited_supplier_input',
  hiddenNameId:'audited_supplier_value',
  hiddenIdId:'audited_supplier_id',
  popupId:'supplier_suggest',
  resource:'audited-suppliers',
  nameFallbacks:['name','label','audited_supplier','supplier_name']
});

makeAutocomplete({
  inputId:'location_input',
  hiddenNameId:'location_value',
  hiddenIdId:'location_id',
  popupId:'location_suggest',
  resource:'locations',
  nameFallbacks:['name','label','location']
});

makeAutocomplete({
  inputId:'category_input',
  hiddenNameId:'category_value',
  hiddenIdId:'category_id',
  popupId:'category_suggest',
  resource:'categories',
  nameFallbacks:['name','label','category']
});

makeAutocomplete({
  inputId:'resolver_group_input',
  hiddenNameId:'resolver_group_value',
  hiddenIdId:'resolver_group_id',
  popupId:'resolver_suggest',
  resource:'resolver-groups',
  nameFallbacks:['name','label','resolver_group']
});

/* ===== Modal: controle ===== */
(function(){
  const form        = getForm();
  const btnOpen     = document.getElementById('btn-open-confirm');
  const overlay     = document.getElementById('confirm-overlay');
  const modal       = document.getElementById('confirm-modal');
  const ack         = document.getElementById('ack-check');
  const btnCancel   = document.getElementById('btn-cancel-confirm');
  const btnSubmit   = document.getElementById('btn-submit-confirm');

  if (!form || !btnOpen || !overlay || !modal || !ack || !btnCancel || !btnSubmit) return;

  let lastFocus = null;

  function setVisible(show){
    overlay.style.display = show ? 'block' : 'none';
    modal.style.display   = show ? 'flex' : 'none';
    modal.setAttribute('aria-hidden', show ? 'false' : 'true');
  }

  function openModal(){
    lastFocus = document.activeElement;
    setVisible(true);
    ack.checked = false;
    btnSubmit.disabled = true;
    btnSubmit.style.opacity = '.8';
    btnSubmit.style.cursor  = 'not-allowed';
    setTimeout(() => ack.focus(), 0);
  }

  function closeModal(){
    setVisible(false);
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  btnOpen.addEventListener('click', openModal);
  btnCancel.addEventListener('click', closeModal);
  overlay.addEventListener('click', closeModal);

  ack.addEventListener('change', () => {
    const ok = ack.checked === true;
    btnSubmit.disabled = !ok;
    btnSubmit.style.opacity = ok ? '1' : '.8';
    btnSubmit.style.cursor  = ok ? 'pointer' : 'not-allowed';
    if (ok) btnSubmit.focus();
  });

  btnSubmit.addEventListener('click', () => {
    if (!ack.checked) return;
    closeModal();
    form.requestSubmit ? form.requestSubmit() : form.submit();
  });

  document.addEventListener('keydown', (e) => {
    if (modal.style.display !== 'flex') return;
    if (e.key === 'Escape') { e.preventDefault(); closeModal(); }
    if (e.key === 'Enter' && !btnSubmit.disabled) {
      e.preventDefault(); btnSubmit.click();
    }
  });
})();