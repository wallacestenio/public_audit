/* =========================================
   Helpers
   ========================================= */
function $(sel, ctx){ return (ctx||document).querySelector(sel); }
function $all(sel, ctx){ return Array.from((ctx||document).querySelectorAll(sel)); }

function showFieldError(el, msg){
  if (!el) return;
  el.textContent = msg || '';
  el.style.display = msg ? '' : 'none';
}
function scrollIntoViewIfNeeded(node){
  if (!node) return;
  try { node.scrollIntoView({behavior:'smooth', block:'center'}); } catch(_e){}
}

/* =========================================
   Export por mês
   ========================================= */
function initExportByMonth(){
  const btn = document.getElementById('btn-export-month');
  if (!btn) return;
  btn.addEventListener('click', function(){
    const m = (document.querySelector('input[name="audit_month"]')?.value || '').trim();
    if (!m) { alert('Preencha o campo "Mês da Auditoria" (ex.: 2026-02) para exportar por mês.'); return; }
    window.location.href = '/export/csv?audit_month=' + encodeURIComponent(m);
  });
}

/* =========================================
   Sanfona de Exportação (Accordion)
   ========================================= */
function initExportAccordion(){
  const toggle = document.getElementById('export_toggle');
  const panel  = document.getElementById('export_panel');
  if (!toggle || !panel) return;

  const KEY = 'export_panel_open';
  const persisted = sessionStorage.getItem(KEY);
  if (persisted === '1') {
    panel.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
  }

  function open(){
    panel.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    sessionStorage.setItem(KEY, '1');
  }
  function close(){
    toggle.setAttribute('aria-expanded', 'false');
    sessionStorage.setItem(KEY, '0');
    setTimeout(() => { panel.hidden = true; }, 160);
  }
  function togglePanel(){
    const expanded = toggle.getAttribute('aria-expanded') === 'true';
    expanded ? close() : open();
  }

  toggle.addEventListener('click', togglePanel);
}

/* =========================================
   Grupo de justificativas (presets/chips)
   ========================================= */
function initNcGroup(){
  const input   = $('#nc_search');
  const presets = $('#nc_presets');
  const chips   = $('#nc_chips');
  const hidden  = $('#nc_ids');
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

  function updateHidden(){ hidden.value = Array.from(selectedIds).join(';'); }

  function renderChips(){
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

  function renderPresets(){
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

  function applyFilter(q){
    const needle = (q || '').trim().toLowerCase();
    if (!needle) { filtered = []; renderPresets(); return; }
    filtered = allReasons.filter(it =>
      it.label.toLowerCase().includes(needle) || (it.group || 'Outros').toLowerCase().includes(needle)
    );
    renderPresets();
  }

  async function loadAll(){
    try {
      let res = await fetch('/api/catalog?resource=noncompliance-reasons');
      if (!res.ok) res = await fetch('/api/catalog?resource=noncompliance-reasons&q=');
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
    } catch(e){
      presets.innerHTML = '<div class="muted" style="padding:4px 2px;color:#b91c1c">Não foi possível carregar as justificativas.</div>';
      console.error(e);
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

  // Expor API mínima para o controlador de "Conforme?"
  window.__ncAPI = {
    getCount: () => selectedIds.size,
    clearAll: () => {
      selectedIds.clear();
      updateHidden();
      chips.innerHTML = '';
      presets.innerHTML = '';
      input.value = '';
      filtered = [];
      renderPresets();
    }
  };

  loadAll();
}

/* =========================================
   Normalizador do número do ticket
   + sincronização do tipo
   ========================================= */
function initTicketNormalizer(){
  const tn = $('#ticket_number');
  if (!tn) return;

  const radios = $all('input[name="ticket_type"]');
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
  normalizeTicket();
}

/* =========================================
   Normalizador de audit_month
   ========================================= */
function initMonthNormalizer(){
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

    if (/^\d{4}-(0[1-9]|1[0-2])$/.test(v)) return v; // YYYY-MM
    let m1 = v.match(/^(0?[1-9]|1[0-2])\s*\/\s*(\d{4})$/);
    if (m1) return `${m1[2]}-${String(m1[1]).padStart(2,'0')}`; // MM/YYYY

    let m2 = v.match(/^([a-zçõ]+)\s+(\d{4})$/);
    if (m2 && map[m2[1]]) return `${m2[2]}-${map[m2[1]]}`; // "fev 2026"

    if (map[v]) { // só mês -> ano atual
      const year = new Date().getFullYear();
      return `${year}-${map[v]}`;
    }
    return v;
  }

  m.addEventListener('blur', ()=>{ m.value = norm(m.value); });
}

/* =========================================
   Autocomplete genérico (corrigido)
   ========================================= */
function initTypeahead(cfg){
  const { inputId, hiddenNameId, hiddenIdId, suggestId, resource, fieldFallbacks, invalidMsg } = cfg;
  const input  = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenNameId);
  const hidId  = document.getElementById(hiddenIdId);
  const popup  = document.getElementById(suggestId);
  if (!input || !hidden || !hidId || !popup) return;

  let timer = null;

  function closePopup(){ popup.style.display='none'; popup.innerHTML=''; }
  function openPopup(){ popup.style.display='block'; }
  function clearSelection(){ hidden.value=''; hidId.value=''; }

  function pickName(it){
    for (const k of ['name', ...fieldFallbacks]) {
      if (it && it[k]) return String(it[k]);
    }
    return '';
  }

  function selectItem(it){
    const name = pickName(it);
    input.value  = name;
    hidden.value = name;
    hidId.value  = String(it.id ?? '');
    closePopup();
  }

  function renderError(message){
    popup.innerHTML = '';
    const box = document.createElement('div');
    box.className = 'muted';
    box.style.cssText = 'padding:6px;color:#b91c1c';
    box.textContent = message || 'Falha ao carregar sugestões.';
    popup.appendChild(box);
    openPopup();
  }

  function renderList(items){
    popup.innerHTML = '';
    if (!items || !items.length) {
      const box = document.createElement('div');
      box.className = 'muted';
      box.style.cssText = 'padding:6px';
      box.textContent = 'Nenhum resultado.';
      popup.appendChild(box);
      openPopup();
      return;
    }
    const list = document.createElement('div');
    list.style.display = 'flex';
    list.style.flexDirection = 'column';
    items.forEach(it => {
      const name = pickName(it);
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

  async function fetchList(query){
    const url = `/api/catalog?resource=${encodeURIComponent(resource)}&q=${encodeURIComponent(query || '')}`;
    try{
      let res = await fetch(url, { headers:{'Accept':'application/json'} });
      if (!res.ok) {
        // fallback: tenta sem q para ver se o endpoint suporta lista padrão
        res = await fetch(`/api/catalog?resource=${encodeURIComponent(resource)}&q=`, { headers:{'Accept':'application/json'} });
      }
      if (!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      const arr = Array.isArray(data) ? data : (Array.isArray(data?.items) ? data.items : []);
      renderList(arr);
    }catch(e){
      console.error('[typeahead]', resource, e);
      renderError('Não foi possível carregar as sugestões.');
    }
  }

  // Busca ao digitar: limiar 1 (antes eram 2); permite 0 no focus
  input.addEventListener('input', () => {
    clearSelection();
    const q = input.value.trim();
    clearTimeout(timer);
    if (q.length < 1) { closePopup(); return; }
    timer = setTimeout(() => fetchList(q), 180);
  });

  // Ao focar, tenta q atual ou lista base
  input.addEventListener('focus', () => {
    const q = input.value.trim();
    if (q.length >= 1) fetchList(q);
    else fetchList('');
  });

  // Fecha se clicar fora
  document.addEventListener('click', (e) => { if (!popup.contains(e.target) && e.target !== input) closePopup(); });

  // Validação no submit (fallback)
  const form = document.querySelector('form[action="/audit-entries"]');
  if (form) {
    form.addEventListener('submit', (e) => {
      const vis = (input.value || '').trim();
      const sel = (hidden.value || '').trim();
      if (vis === '' || sel === '' || vis !== sel) {
        e.preventDefault();
        alert(invalidMsg);
        input.focus();
      }
    });
  }
}

/* =========================================
   Modal de CONFIRMAÇÃO (com PRE-CHECKS leves)
   ========================================= */
function initSubmitModal(){
  const form        = document.querySelector('form[action="/audit-entries"]');
  const btnOpen     = document.getElementById('btn-open-confirm');
  const overlay     = document.getElementById('confirm-overlay');
  const modal       = document.getElementById('confirm-modal');
  const ack         = document.getElementById('ack-check');
  const btnCancel   = document.getElementById('btn-cancel-confirm');
  const btnSubmit   = document.getElementById('btn-submit-confirm');

  if (!form || !btnOpen || !overlay || !modal || !ack || !btnCancel || !btnSubmit) return;

  function setVisible(show){
    overlay.style.display = show ? 'block' : 'none';
    modal.style.display   = show ? 'flex' : 'none';
    modal.setAttribute('aria-hidden', show ? 'false' : 'true');
  }

  // Pré-validações mínimas antes de abrir o modal (não navega)
  function precheck(){
    showFieldError($('#ticket_error'), '');

    const ticket = ($('#ticket_number')?.value || '').trim().toUpperCase();
    const validTicket = /^(INC|RITM|SCTASK)\d{6,}$/.test(ticket);
    if (!validTicket){
      showFieldError($('#ticket_error'), 'Informe um Número de Ticket válido (INC/RITM/SCTASK + dígitos). Ex.: INC1234567');
      $('#ticket_number')?.focus();
      scrollIntoViewIfNeeded($('#ticket_number'));
      return false;
    }

    const slaChecked  = !!document.querySelector('input[name="sla_met"]:checked');
    if (!slaChecked){
      alert('Selecione "SLA Atingido?" (Sim ou Não).');
      document.getElementById('sla_met_1')?.focus();
      return false;
    }

    const compChecked = !!document.querySelector('input[name="is_compliant"]:checked');
    if (!compChecked){
      alert('Selecione "Chamado Conforme?" (Sim ou Não).');
      document.getElementById('is_comp_1')?.focus();
      return false;
    }

    const isCompVal = document.querySelector('input[name="is_compliant"]:checked')?.value || '1';
    if (isCompVal === '0'){
      const ids = ($('#nc_ids')?.value || '').trim();
      if (!ids){
        alert('Selecione ao menos uma justificativa (para Não conforme).');
        scrollIntoViewIfNeeded($('#just_block'));
        $('#nc_search')?.focus();
        return false;
      }
    }

    // Typeaheads coerentes
    const pairs = [
      ['kyndryl_auditor_input','kyndryl_auditor_value','auditor'],
      ['petrobras_inspector_input','petrobras_inspector_value','inspetor Petrobras'],
      ['audited_supplier_input','audited_supplier_value','fornecedor auditado'],
      ['location_input','location_value','localidade'],
      ['category_input','category_value','categoria'],
      ['resolver_group_input','resolver_group_value','mesa solucionadora'],
    ];
    for (const [visId, hidId, label] of pairs){
      const vis = (document.getElementById(visId)?.value || '').trim();
      const hid = (document.getElementById(hidId)?.value || '').trim();
      if (vis === '' || hid === '' || vis !== hid){
        alert(`Selecione um(a) ${label} válido(a) da lista.`);
        document.getElementById(visId)?.focus();
        return false;
      }
    }

    return true;
  }

  function openModal(){
    setVisible(true);
    ack.checked = false;
    btnSubmit.disabled = true;
    btnSubmit.style.opacity = '.8';
    btnSubmit.style.cursor  = 'not-allowed';
    setTimeout(() => ack.focus(), 0);
  }

  function closeModal(){ setVisible(false); }

  btnOpen.addEventListener('click', () => {
    if (!precheck()) return;
    openModal();
  });
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
    if (e.key === 'Enter' && !btnSubmit.disabled) { e.preventDefault(); btnSubmit.click(); }
  });
}

/* =========================================
   Controle de “Conforme?” + modal para limpar justificativas
   ========================================= */
function initCompliantToggle(){
  const radios = $all('input[name="is_compliant"]');
  if (!radios.length) return;

  const blockJust = document.getElementById('just_block');

  const overlay = document.getElementById('nc-overlay');
  const modal   = document.getElementById('nc-modal');
  const btnOk   = document.getElementById('nc-confirm');
  const btnNo   = document.getElementById('nc-cancel');

  const radioSim = document.querySelector('input[name="is_compliant"][value="1"]');
  const radioNao = document.querySelector('input[name="is_compliant"][value="0"]');

  function uiJust(show){ if (blockJust) blockJust.style.display = show ? 'block' : 'none'; }
  function uiPressed(){
    radios.forEach(r => {
      const lab = r.id ? document.querySelector(`label[for="${r.id}"]`) : null;
      if (lab) lab.setAttribute('aria-pressed', r.checked ? 'true' : 'false');
    });
  }
  function selectSim(){
    if (radioSim) radioSim.checked = true;
    if (radioNao) radioNao.checked = false;
    uiJust(false);
    uiPressed();
  }
  function selectNao(){
    if (radioNao) radioNao.checked = true;
    if (radioSim) radioSim.checked = false;
    uiJust(true);
    uiPressed();
  }

  function openNcModal(onConfirm, onCancel){
    overlay.style.display = 'block';
    modal.style.display   = 'flex';
    modal.setAttribute('aria-hidden', 'false');

    function cleanup(){
      overlay.style.display = 'none';
      modal.style.display   = 'none';
      modal.setAttribute('aria-hidden', 'true');
      overlay.removeEventListener('click', onCancelHandler);
      btnNo.removeEventListener('click', onCancelHandler);
      btnOk.removeEventListener('click', onConfirmHandler);
      document.removeEventListener('keydown', onKey);
    }
    function onConfirmHandler(){
      try {
        if (window.__ncAPI && typeof window.__ncAPI.clearAll === 'function') window.__ncAPI.clearAll();
      } finally {
        cleanup();
        if (typeof onConfirm === 'function') onConfirm();
      }
    }
    function onCancelHandler(){
      cleanup();
      if (typeof onCancel === 'function') onCancel();
    }
    function onKey(e){
      if (e.key === 'Escape'){ e.preventDefault(); onCancelHandler(); }
      if (e.key === 'Enter'){ e.preventDefault(); onConfirmHandler(); }
    }

    overlay.addEventListener('click', onCancelHandler);
    btnNo.addEventListener('click', onCancelHandler);
    btnOk.addEventListener('click', onConfirmHandler);
    document.addEventListener('keydown', onKey);
    setTimeout(() => { try{ btnOk.focus(); }catch(_e){} }, 0);
  }

  function handleChange(e){
    const val = e && e.target ? String(e.target.value) : (radioSim?.checked ? '1' : '0');
    if (val === '0'){ // Não: mostra bloco de justificativas
      selectNao();
      return;
    }
    // Vai para SIM: se havia justificativas, pede confirmação pra limpar
    const count = (window.__ncAPI && typeof window.__ncAPI.getCount === 'function') ? window.__ncAPI.getCount() : 0;
    if (count > 0){
      openNcModal(
        () => { selectSim(); }, // confirmar
        () => { selectNao(); }  // cancelar
      );
    } else {
      selectSim();
    }
  }

  radios.forEach(r => r.addEventListener('change', handleChange));
  handleChange(); // estado inicial coerente
}

/* =========================================
   Required radios (SLA e Compliant) – fallback de segurança
   ========================================= */
function initRequiredRadios(){
  const form = document.querySelector('form[action="/audit-entries"]');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    const slaChecked = !!document.querySelector('input[name="sla_met"]:checked');
    if (!slaChecked) {
      e.preventDefault();
      alert('Selecione "SLA Atingido?" (Sim ou Não).');
      const first = document.getElementById('sla_met_1') || document.querySelector('input[name="sla_met"]');
      if (first) first.focus();
      return;
    }

    const compChecked = !!document.querySelector('input[name="is_compliant"]:checked');
    if (!compChecked) {
      e.preventDefault();
      alert('Selecione "Chamado Conforme?" (Sim ou Não).');
      const first = document.getElementById('is_comp_1') || document.querySelector('input[name="is_compliant"]');
      if (first) first.focus();
      return;
    }

    const isCompVal = document.querySelector('input[name="is_compliant"]:checked')?.value || '1';
    if (isCompVal === '0'){
      const ids = ($('#nc_ids')?.value || '').trim();
      if (!ids){
        e.preventDefault();
        alert('Selecione ao menos uma justificativa (para Não conforme).');
        scrollIntoViewIfNeeded($('#just_block'));
        $('#nc_search')?.focus();
        return;
      }
    }
  });
}

/* =========================================
   Sucesso: countdown + mensagens + redirect
   ========================================= */
function initSuccessBanner(){
  const banner = document.getElementById('success-banner');
  if (!banner) return;

  const elCountdown = document.getElementById('countdown');
  const msgsWrap    = document.getElementById('motivation_msgs');
  const msgs        = msgsWrap ? Array.from(msgsWrap.querySelectorAll('.msg')) : [];
  let idx = 0;

  // Rotaciona mensagens a cada 1.6s
  if (msgs.length > 1) {
    setInterval(() => {
      msgs.forEach((m,i) => { m.style.display = (i === idx ? '' : 'none'); });
      idx = (idx + 1) % msgs.length;
    }, 1600);
  }

  // Countdown + redirect
  let timeLeft = parseInt((elCountdown?.textContent || '5'), 10);
  const timer = setInterval(() => {
    timeLeft--;
    if (elCountdown) elCountdown.textContent = String(timeLeft);

    if (timeLeft <= 0) {
      clearInterval(timer);
      const url = new URL(window.location.href);
      url.searchParams.delete('created');
      const cleanUrl = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '');
      window.location.href = cleanUrl;
      setTimeout(() => {
        const first = document.querySelector('input[name="ticket_number"]');
        if (first) first.focus();
      }, 500);
    }
  }, 1000);
}

/* =========================================
   Inicialização ÚNICA
   ========================================= */
function initAllTypeaheads(){
  // Auditor Kyndryl
  initTypeahead({
    inputId:'kyndryl_auditor_input',
    hiddenNameId:'kyndryl_auditor_value',
    hiddenIdId:'kyndryl_auditor_id',
    suggestId:'auditor_suggest',
    resource:'kyndryl-auditors',
    fieldFallbacks:['kyndryl_auditor','label'],
    invalidMsg:'Selecione um auditor válido da lista.'
  });
  // Inspetor Petrobras
  initTypeahead({
    inputId:'petrobras_inspector_input',
    hiddenNameId:'petrobras_inspector_value',
    hiddenIdId:'petrobras_inspector_id',
    suggestId:'inspector_suggest',
    resource:'petrobras-inspectors',
    fieldFallbacks:['petrobras_inspector','label'],
    invalidMsg:'Selecione um inspetor Petrobras válido da lista.'
  });
  // Fornecedor Auditado
  initTypeahead({
    inputId:'audited_supplier_input',
    hiddenNameId:'audited_supplier_value',
    hiddenIdId:'audited_supplier_id',
    suggestId:'supplier_suggest',
    resource:'audited-suppliers',
    fieldFallbacks:['audited_supplier','label'],
    invalidMsg:'Selecione um fornecedor auditado válido da lista.'
  });
  // Localidade
  initTypeahead({
    inputId:'location_input',
    hiddenNameId:'location_value',
    hiddenIdId:'location_id',
    suggestId:'location_suggest',
    resource:'locations',
    fieldFallbacks:['location','label'],
    invalidMsg:'Selecione uma localidade válida da lista.'
  });
  // Categoria
  initTypeahead({
    inputId:'category_input',
    hiddenNameId:'category_value',
    hiddenIdId:'category_id',
    suggestId:'category_suggest',
    resource:'categories',
    fieldFallbacks:['category','label'],
    invalidMsg:'Selecione uma categoria válida da lista.'
  });
  // Mesa Solucionadora
  initTypeahead({
    inputId:'resolver_group_input',
    hiddenNameId:'resolver_group_value',
    hiddenIdId:'resolver_group_id',
    suggestId:'resolver_suggest',
    resource:'resolver-groups',
    fieldFallbacks:['resolver_group','label'],
    invalidMsg:'Selecione uma mesa solucionadora válida da lista.'
  });
}

document.addEventListener('DOMContentLoaded', function(){
  initExportByMonth();
  initNcGroup();
  initTicketNormalizer();
  initMonthNormalizer();
  initAllTypeaheads();
  initSubmitModal();
  initCompliantToggle();
  initRequiredRadios();
  initSuccessBanner();
  initExportAccordion();
});