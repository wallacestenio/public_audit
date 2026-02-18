<?php $title = 'Formulário de Chamados'; ?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    :root{
      --bg:#f6f7f9; --card:#fff; --bd:#e3e6ea; --bd2:#ccd1d6;
      --tx:#111827; --muted:#6b7280; --btn:#0d6efd; --btnh:#0b5ed7;
      --btn2:#64748b; --btn3:#94a3b8; --info:#0ea5e9;
      --success-bg:#d1fae5; --success-bd:#10b981; --success-tx:#065f46;
      --danger-bg:#fee2e2; --danger-bd:#ef4444; --danger-tx:#b91c1c;
    }
    *{box-sizing:border-box}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:24px;background:var(--bg);color:var(--tx)}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:8px;padding:20px;max-width:1000px;margin:0 auto}
    .row{display:flex;flex-wrap:wrap;gap:12px}
    .col{flex:1 1 300px;min-width:300px}
    .col-full{flex-basis:100%;min-width:100%}
    label{font-weight:600;display:block;margin:6px 0}
    input,select,textarea{width:100%;padding:10px;border:1px solid var(--bd2);border-radius:6px;background:#fff}
    button,.btn{padding:10px 16px;border:0;background:var(--btn);color:#fff;border-radius:6px;cursor:pointer;text-decoration:none;display:inline-block}
    button:hover,.btn:hover{background:var(--btnh)}
    .muted{color:var(--muted);font-size:13px;margin-top:4px}
    .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
    .actions .btn-gray{background:var(--btn2)}
    .actions .btn-light{background:var(--btn3)}
    .btn-info{background:var(--info)}
    .center{text-align:center}
    .justify-block{margin-top:4px}
    @media (max-width:680px){ .col{min-width:100%} }

    /* Alerts coerentes com o sucesso.php */
    .alert{padding:15px;border-radius:6px;font-size:16px;margin-bottom:12px;border:1px solid transparent}
    .alert-success{background:var(--success-bg);border-color:var(--success-bd);color:var(--success-tx)}
    .alert-danger{background:var(--danger-bg);border-color:var(--danger-bd);color:var(--danger-tx)}

    /* ====== Botões segmentados (radios estilizados) ====== */
    .segmented{ display:flex; gap:8px; flex-wrap:wrap; }
    .segmented > input[type="radio"]{ position:absolute; opacity:0; pointer-events:none; }
    .segmented-btn{
      flex:1; min-width:60px; text-align:center;
      background:#e5e7eb; border:1px solid #cbd5e1;
      border-radius:6px; font-weight:600; cursor:pointer; user-select:none;
      transition:background .15s ease, border-color .15s ease, color .15s ease, transform .05s ease;
      height:42px; line-height:22px; display:flex; justify-content:center; align-items:center; padding:0;
    }
    .segmented-btn:hover{ background:#d1d5db; }
    .segmented > input[type="radio"]:checked + .segmented-btn{
      background:#0d6efd; color:#fff; border-color:#0d6efd;
    }
    .segmented > input[type="radio"]:focus + .segmented-btn{
      outline:3px solid rgba(13,110,253,.35); outline-offset:2px;
    }
    .segmented-btn:active{ transform:scale(.98); }
    @media (max-width:680px){ .segmented-btn{ min-height:42px; min-width:calc(25% - 6px);} }

    /* ===== TAG GROUP (justificativas) ===== */
    .tag-input{ width:100%; padding:10px; border:1px solid var(--bd2); border-radius:6px; background:#fff; margin-bottom:6px; }
    .tag-suggest{ position:relative; background:#fff; border:1px solid var(--bd2); border-radius:6px;
      max-height:240px; overflow:auto; display:none; margin-bottom:6px; }
    .tag-group{ padding:6px 8px; border-top:1px solid #eef2f7; }
    .tag-group:first-child{ border-top:none; }
    .tag-group-title{ font-weight:700; font-size:13px; color:#64748b; margin:6px 2px; text-transform:uppercase; letter-spacing:.02em; }
    .tag-item{ display:block; width:100%; text-align:left; background:#fff; border:1px solid #e5e7eb; border-radius:6px;
      padding:8px; margin:4px 0; cursor:pointer; }
    .tag-item:hover{ background:#f1f5f9; }
    .tag-chips{ display:flex; flex-wrap:wrap; gap:8px; }
    .tag-chip{ background:#e2e8f0; color:#0f172a; border-radius:20px; padding:6px 10px; display:flex; align-items:center; gap:6px; }
    .tag-chip .x{ background:#cbd5e1; border:none; border-radius:50%; width:18px; height:18px; line-height:18px; text-align:center; cursor:pointer; }
    /* ===== Normalização de altura para inputs ===== */
input, select, textarea {
  height: 42px;                /* altura padrão */
  line-height: 22px;
}

/* ===== Container do campo (label + controle) — opcional, dá ritmo vertical */
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;                    /* distância label-controle */
}

/* ===== Grupos de botões radio estilizados (segmentados) ===== */
.segmented {
  display: flex;
  flex-wrap: nowrap;           /* mantém numa linha quando couber */
  gap: 8px;
  align-items: stretch;        /* estica os botões para altura uniforme */
  min-height: 42px;            /* garante mesma altura de input */
}

/* Radios escondidos, mas acessíveis */
.segmented > input[type="radio"] {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

/* Botão segmentado (label) */
.segmented-btn {
  flex: 1 1 0;                 /* todos dividem espaço igualmente */
  min-width: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 42px;                /* MESMA altura dos inputs */
  padding: 0;                  /* remove padding para altura exata */
  background: #e5e7eb;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  user-select: none;
  transition: background .15s ease, border-color .15s ease, color .15s ease, transform .05s ease;
}

/* Hover/active/focus/checked */
.segmented-btn:hover { background: #d1d5db; }
.segmented > input[type="radio"]:checked + .segmented-btn {
  background: #0d6efd;
  color: #fff;
  border-color: #0d6efd;
}
.segmented > input[type="radio"]:focus + .segmented-btn {
  outline: 3px solid rgba(13,110,253,.35);
  outline-offset: 2px;
}
.segmented-btn:active { transform: scale(.98); }

/* ===== Responsividade ===== */
@media (max-width: 980px) {
  .segmented { flex-wrap: wrap; }        /* pode quebrar em 2 linhas */
  .segmented-btn { min-width: calc(25% - 6px); } /* tenta manter 4 por linha quando couber */
}
@media (max-width: 680px) {
  .segmented-btn { min-width: 48%; }     /* 2 por linha quando bem estreito */
}

/* Campos com ritmo vertical consistente */
.field{ display:flex; flex-direction:column; gap:6px; }

/* Altura padrão dos controles */
input, select, textarea{ height:42px; line-height:22px; box-sizing:border-box; }

/* Grupos segmentados exatamente com a altura dos inputs */
.segmented{
  display:flex; align-items:stretch; gap:8px; width:100%;
  min-height:42px; flex-wrap:nowrap;
}
.segmented > input[type="radio"]{ position:absolute; opacity:0; pointer-events:none; }
.segmented-btn{
  flex:1 1 0; min-width:60px; display:flex; align-items:center; justify-content:center;
  height:42px; padding:0; background:#e5e7eb; border:1px solid #cbd5e1; border-radius:6px;
  font-weight:600; cursor:pointer; user-select:none;
  transition:background .15s ease, border-color .15s ease, color .15s ease, transform .05s ease;
}
.segmented-btn:hover{ background:#d1d5db; }
.segmented > input[type="radio"]:checked + .segmented-btn{ background:#0d6efd; color:#fff; border-color:#0d6efd; }
.segmented > input[type="radio"]:focus + .segmented-btn{ outline:3px solid rgba(13,110,253,.35); outline-offset:2px; }
.segmented-btn:active{ transform:scale(.98); }

@media (max-width:980px){ .segmented{ flex-wrap:wrap; } .segmented-btn{ min-width:calc(25% - 6px);} }
@media (max-width:680px){ .segmented-btn{ min-width:48%; } }
  </style>
</head>
<body>
  <div class="card">
    <!-- <h1>Formulário de Chamados</h1> -->

    <?php
      // 1) Banner de sucesso se veio com ?created=ID
      $created = $_GET['created'] ?? null;
      if ($created !== null && $created !== ''):
    ?>
      <div class="alert alert-success">
        Chamado <strong>#<?= htmlspecialchars((string)$created) ?></strong> criado com sucesso.
        <br>
        Você será redirecionado para o formulário em <span id="countdown">5</span> segundos…
      </div>
      <script>
        (function(){
          let timeLeft = 5;
          const el = document.getElementById('countdown');
          const t = setInterval(()=>{
            timeLeft--;
            if (el) el.textContent = timeLeft;
            if (timeLeft <= 0) {
              clearInterval(t);
              // limpa o parâmetro ?created da URL sem recarregar
              const url = new URL(window.location.href);
              url.searchParams.delete('created');
              window.history.replaceState({}, '', url.pathname + (url.search ? '?'+url.searchParams.toString() : ''));
              // foco no primeiro campo (experiência do usuário)
              const first = document.querySelector('input[name="ticket_number"]');
              if (first) first.focus();
            }
          }, 1000);
        })();
      </script>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
</head>
<body>
  <div class="card">
    <h1>Formulário de Chamados</h1>

    <?php if (!empty($error)): ?>
      <div style="background:#fee2e2;border:1px solid #ef4444;color:#b91c1c;padding:10px;border-radius:6px;margin-bottom:12px">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- FORM -->
    <form method="post" action="/audit-entries">
      <div class="row">

        <!-- Número do Ticket (com validação por prefixo) -->
<div class="col">
  <div class="field">
    <label>Número Ticket *</label>
    <input
      id="ticket_number"
      name="ticket_number"
      required
      placeholder="INC1234567, RITM1234567, SCTASK1234567"
      value="<?= htmlspecialchars($old['ticket_number'] ?? '') ?>"
      pattern="^(INC|RITM|SCTASK)\d{6,}$"
      title="O ticket deve iniciar com INC, RITM ou SCTASK seguido de dígitos. Ex.: INC1234567"
      autocomplete="off"
      inputmode="text"
    >
    <div class="muted">
      Deve iniciar com <b>INC</b>, <b>RITM</b> ou <b>SCTASK</b> + dígitos. Ex.: <code>INC9889075</code>
    </div>
  </div>
</div>

<!-- Tipo do Ticket: 3 botões (sincroniza com o prefixo digitado) -->
<div class="col">
  <div class="field">
    <label>Tipo do Ticket *</label>
    <div class="segmented" role="radiogroup" aria-label="Tipo do Ticket">
      <?php
        $ttOld = strtoupper((string)($old['ticket_type'] ?? ''));
        $types = ['INCIDENTE'=>'Incidente','REQUISIÇÃO'=>'Requisição','TASK'=>'Task'];
        $i=0;
        foreach ($types as $val=>$label) {
          $id = 'ticket_type_'.(++$i);
          $checked = ($ttOld===strtoupper($val)) ? 'checked' : '';
          $req = ($i===1) ? 'required' : '';
          echo "<input type='radio' id='{$id}' name='ticket_type' value='{$val}' {$checked} {$req}>
                <label for='{$id}' class='segmented-btn' aria-pressed='".($checked?'true':'false')."'>{$label}</label>";
        }
      ?>
    </div>
  </div>
</div>

        <div class="col">
          <label>Auditor Kyndryl *</label>
          <input name="kyndryl_auditor" required value="<?= htmlspecialchars($old['kyndryl_auditor'] ?? '') ?>">
        </div>

        <div class="col">
          <label>Fiscal Petrobras *</label>
          <input name="petrobras_inspector" required value="<?= htmlspecialchars($old['petrobras_inspector'] ?? '') ?>">
        </div>

        <div class="col">
          <label>Fornecedor Auditado *</label>
          <input name="audited_supplier" required value="<?= htmlspecialchars($old['audited_supplier'] ?? '') ?>">
        </div>

        <div class="col">
          <label>Localidade *</label>
          <input name="location" required value="<?= htmlspecialchars($old['location'] ?? '') ?>">
        </div>

        <div class="col">
          <label>Mês da Auditoria *</label>
          <input
            name="audit_month" required
            placeholder="fevereiro 2026, fev 2026, 02/2026 ou 2026-02"
            list="month_names" autocomplete="off"
            value="<?= htmlspecialchars($old['audit_month'] ?? '') ?>"
          >
          <datalist id="month_names">
            <option value="janeiro"></option><option value="fevereiro"></option><option value="março"></option>
            <option value="abril"></option><option value="maio"></option><option value="junho"></option>
            <option value="julho"></option><option value="agosto"></option><option value="setembro"></option>
            <option value="outubro"></option><option value="novembro"></option><option value="dezembro"></option>
          </datalist>
          <div class="muted">Dica: “fevereiro 2026”, “fev 2026”, “02/2026” ou “2026-02”.</div>
        </div>

        <!-- Prioridade: 4 botões -->
        <div class="col">
  <div class="field">
    <label>Prioridade *</label>
    <div class="segmented" role="radiogroup" aria-label="Nível de Prioridade">
      <?php
        $pOld = (string)($old['priority'] ?? '');
        for ($n=1;$n<=4;$n++) {
          $id="priority_{$n}"; $val=(string)$n;
          $checked = ($pOld===$val) ? 'checked' : ''; $req = ($n===1)?'required':'';
          echo "<input type='radio' id='{$id}' name='priority' value='{$val}' {$checked} {$req}>
                <label for='{$id}' class='segmented-btn' aria-pressed='".($checked?'true':'false')."'>{$val}</label>";
        }
      ?>
    </div>
  </div>
</div>

        <div class="col">
          <label>Solicitante *</label>
          <input name="requester_name" required value="<?= htmlspecialchars($old['requester_name'] ?? '') ?>">
        </div>

        <div class="col">
          <label>Categoria *</label>
          <input name="category" required value="<?= htmlspecialchars($old['category'] ?? '') ?>">
        </div>

        <div class="col">
          <label>Mesa Solucionadora *</label>
          <input name="resolver_group" required value="<?= htmlspecialchars($old['resolver_group'] ?? '') ?>">
        </div>

        <!-- SLA: SIM/NÃO -->
        <div class="col">
  <div class="field">
    <label>Nível de Serviço Atingido? *</label>
    <div class="segmented" role="radiogroup" aria-label="SLA Atingido">
      <?php
        $slaOld = (string)($old['sla_met'] ?? '1'); $opts=['1'=>'Sim','0'=>'Não']; $i=0;
        foreach($opts as $val=>$label){
          $id='sla_met_'.(++$i); $checked=($slaOld===$val)?'checked':''; $req=($i===1)?'required':'';
          echo "<input type='radio' id='{$id}' name='sla_met' value='{$val}' {$checked} {$req}>
                <label for='{$id}' class='segmented-btn' aria-pressed='".($checked?'true':'false')."'>{$label}</label>";
        }
      ?>
    </div>
  </div>
</div>

        <!-- Conforme: SIM/NÃO centralizado -->
        <div class="col-full center">
  <div class="field" style="display:inline-block;min-width:240px;text-align:left">
    <label style="display:block;margin:6px 0">Chamado Conforme? *</label>
    <div class="segmented" role="radiogroup" aria-label="Chamado Conforme">
      <?php
        $icOld=(string)($old['is_compliant'] ?? '1'); $opts=['1'=>'Sim','0'=>'Não']; $i=0;
        foreach($opts as $val=>$label){
          $id='is_comp_'.(++$i); $checked=($icOld===$val)?'checked':''; $req=($i===1)?'required':'';
          echo "<input type='radio' id='{$id}' name='is_compliant' value='{$val}' {$checked} {$req}>
                <label for='{$id}' class='segmented-btn' aria-pressed='".($checked?'true':'false')."'>{$label}</label>";
        }
      ?>
    </div>
  </div>
</div>

        <!-- Justificativas (TAGs) -->
        <div class="col-full justify-block" id="just_block" style="display: <?= ((string)($old['is_compliant'] ?? '1')==='0'?'block':'none') ?>;">
          <label>Justificativas (obrigatórias quando Não conforme)</label>

          <input type="text" id="nc_search" class="tag-input" placeholder="Digite para buscar justificativas..." autocomplete="off">

          <div id="nc_suggest" class="tag-suggest" role="listbox" aria-label="Sugestões de justificativas"></div>

          <div id="nc_chips" class="tag-chips" aria-live="polite"></div>

          <!-- hidden enviado no POST (separado por ;) -->
          <input type="hidden" name="noncompliance_reasons" id="nc_values" value="<?= htmlspecialchars($old['noncompliance_reasons'] ?? '') ?>">

          <div class="muted">Dica: adicione várias justificativas. Clique na tag para remover.</div>
        </div>

        <!-- Salvar -->
        <div class="col-full" style="display:flex;justify-content:flex-end;margin-top:8px">
          <button type="submit">Salvar</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Ações (fora do form) -->
  <div class="actions" style="justify-content:flex-end;max-width:1000px;margin:12px auto 0">
    <a href="/export/csv" class="btn btn-gray">Exportar CSV (toda base)</a>
    <a href="/export/bridge" class="btn btn-light">Exportar CSV (Justificativas)</a>
    <a href="/export/csv/full" class="btn btn-light">Exportar CSV (base + justificativas)</a>
    <button type="button" onclick="exportByMonth()" class="btn btn-info">Exportar CSV (mês do formulário)</button>
  </div>

  <script>
    function exportByMonth(){
      const m = (document.querySelector('input[name="audit_month"]')?.value || '').trim();
      if (!m) { alert('Preencha o campo "Mês da Auditoria" (ex.: 2026-02) para exportar por mês.'); return; }
      window.location.href = '/export/csv?audit_month=' + encodeURIComponent(m);
    }

    // Toggle do bloco de justificativas (com radios)
    function getIsCompliant(){ const el=document.querySelector('input[name="is_compliant"]:checked'); return el ? el.value : '1'; }
    function toggleJust(){
      const show = getIsCompliant()==='0';
      const block=document.getElementById('just_block');
      if (block) block.style.display = show ? 'block' : 'none';
    }
    document.querySelectorAll('input[name="is_compliant"]').forEach(r => r.addEventListener('change', toggleJust));
    toggleJust(); // estado inicial
  </script>

  <!-- TAG GROUP: busca / chips -->
  <script>
  (function(){
    const input   = document.getElementById('nc_search');
    const box     = document.getElementById('nc_suggest');
    const chips   = document.getElementById('nc_chips');
    const hidden  = document.getElementById('nc_values');
    if (!input || !box || !chips || !hidden) return;

    const selected = new Set((hidden.value||'').split(/[;,]/).map(s=>s.trim()).filter(Boolean));
    function updateHidden(){ hidden.value = Array.from(selected).join(';'); }
    function renderChips(){
      chips.innerHTML = '';
      selected.forEach(label => {
        const chip=document.createElement('span');
        chip.className='tag-chip';
        chip.innerHTML = `<span>${label}</span><button class="x" title="Remover" aria-label="Remover ${label}">×</button>`;
        chip.querySelector('.x').onclick = () => { selected.delete(label); updateHidden(); renderChips(); };
        chips.appendChild(chip);
      });
    }

    async function fetchReasons(q){
      const res = await fetch(`/api/catalog?resource=noncompliance-reasons&q=${encodeURIComponent(q)}`);
      if (!res.ok) return [];
      return res.json(); // [{id,label,group}, ...]
    }
    function groupBy(arr, key){
      const map=new Map();
      arr.forEach(it => {
        const k = it[key] || 'Outros';
        if (!map.has(k)) map.set(k, []);
        map.get(k).push(it);
      });
      return map;
    }
    function renderSuggest(rows){
      box.innerHTML=''; if (!rows.length){ box.style.display='none'; return; }
      const byGroup=groupBy(rows,'group');
      byGroup.forEach((items, groupName)=>{
        // filtra itens já selecionados
        const remain = items.filter(it => !selected.has(it.label));
        if (!remain.length) return;

        const g=document.createElement('div'); g.className='tag-group';
        const title=document.createElement('div'); title.className='tag-group-title'; title.textContent=groupName; g.appendChild(title);
        remain.forEach(it=>{
          const btn=document.createElement('button'); btn.type='button'; btn.className='tag-item'; btn.textContent=it.label;
          btn.onclick=()=>{ selected.add(it.label); updateHidden(); renderChips(); box.style.display='none'; input.value=''; input.focus(); };
          g.appendChild(btn);
        });
        box.appendChild(g);
      });
      box.style.display = box.childNodes.length ? 'block' : 'none';
    }

    let timer=null;
    input.addEventListener('input', ()=>{
      const q=input.value.trim();
      if (timer) clearTimeout(timer);
      if (!q){ box.style.display='none'; return; }
      timer=setTimeout(async ()=>{
        const data = await fetchReasons(q);
        renderSuggest(data);
      }, 180);
    });

    document.addEventListener('click', (e)=>{ if (!box.contains(e.target) && e.target!==input) box.style.display='none'; });
    input.addEventListener('keydown', (e)=>{ if (e.key==='Enter'){ e.preventDefault(); const first=box.querySelector('.tag-item'); if (first) first.click(); } });

    // validação: se Não conforme, precisa de ao menos 1 tag
    document.querySelector('form[action="/audit-entries"]').addEventListener('submit', (e)=>{
      const isNC = (document.querySelector('input[name="is_compliant"]:checked')?.value === '0');
      const hasTags = (hidden.value.trim().length > 0);
      if (isNC && !hasTags) {
        e.preventDefault();
        alert('Selecione ao menos uma justificativa.');
        input.focus();
      }
    });

    renderChips();
  })();
  </script>
  <script>
(function(){
  const tn = document.getElementById('ticket_number');

  // Mapeia prefixo -> valor do radio de Tipo
  const map = {
    INC:      { value: 'INCIDENTE',  labelIdHint: 'Incidente' },
    RITM:     { value: 'REQUISIÇÃO', labelIdHint: 'Requisição' },
    SCTASK:   { value: 'TASK',       labelIdHint: 'Task' }
  };

  function normalizeTicket() {
    if (!tn) return;
    let v = tn.value.toUpperCase();

    // Remove espaços e normaliza separadores inadvertidos
    v = v.replace(/\s+/g, '');

    // Enforce prefixo permitido + dígitos somente após o prefixo
    // (não removemos caracteres inválidos do meio para não confundir o usuário;
    //  deixamos o pattern/validity fazer o papel de validação)
    tn.value = v;

    // Mensagem de validação amigável
    const ok = /^(INC|RITM|SCTASK)\d{6,}$/.test(v);
    tn.setCustomValidity(ok ? '' :
      'O ticket deve iniciar com INC, RITM ou SCTASK seguido de dígitos. Ex.: INC1234567');

    // Sincroniza Tipo do Ticket pelo prefixo
    const m = /^(INC|RITM|SCTASK)/.exec(v);
    if (m) {
      const prefix = m[1];
      const targetValue = map[prefix].value; // 'INCIDENTE', 'REQUISIÇÃO', 'TASK'
      const radios = document.querySelectorAll('input[name="ticket_type"]');
      radios.forEach(r => {
        r.checked = (r.value.toUpperCase() === targetValue);
        // atualiza aria-pressed nos labels (opcional, mas bacana para acessibilidade)
        const lab = r.id ? document.querySelector(`label[for="${r.id}"]`) : null;
        if (lab) lab.setAttribute('aria-pressed', r.checked ? 'true' : 'false');
      });
    }
  }

  if (tn) {
    tn.addEventListener('input', normalizeTicket);
    tn.addEventListener('blur', normalizeTicket);
    // Inicializa ao carregar (mantém consistência com $old ao reexibir form)
    normalizeTicket();
  }

  // Segurança extra: valida no submit também (caso pattern seja alterado pelo navegador)
  const form = document.querySelector('form[action="/audit-entries"]');
  if (form && tn) {
    form.addEventListener('submit', (e) => {
      normalizeTicket();
      if (!tn.checkValidity()) {
        e.preventDefault();
        tn.reportValidity();
        tn.focus();
      }
    });
  }
})();
</script>
</body>
</html>