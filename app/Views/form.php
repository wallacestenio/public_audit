<?php
/**
 * View: Formulário de Chamados
 * Salvar como UTF-8 (sem BOM).
 * Variáveis esperadas: $title (string), $error (string|null), $old (array)
 */
$title = $title ?? 'Formulário de Chamados';
$old   = is_array($old ?? null) ? $old : [];
$error = $error ?? null;
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
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

    /* Alerts */
    .alert{padding:15px;border-radius:6px;font-size:16px;margin-bottom:12px;border:1px solid transparent}
    .alert-success{background:var(--success-bg);border-color:var(--success-bd);color:var(--success-tx)}
    .alert-danger{background:var(--danger-bg);border-color:var(--danger-bd);color:var(--danger-tx)}

    /* Botões segmentados (radios) */
    .segmented{ display:flex; gap:8px; flex-wrap:wrap; align-items:stretch; min-height:42px; }
    .segmented > input[type="radio"]{ position:absolute; opacity:0; pointer-events:none; }
    .segmented-btn{
      flex:1 1 0; min-width:60px; text-align:center;
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
    @media (max-width:980px){ .segmented{ flex-wrap:wrap; } .segmented-btn{ min-width:calc(25% - 6px);} }
    @media (max-width:680px){ .segmented-btn{ min-width:48%; } }

    /* TAG GROUP (justificativas) */
    .tag-input{ width:100%; padding:10px; border:1px solid var(--bd2); border-radius:6px; background:#fff; margin-bottom:6px; }
    .preset-wrap{ border:1px solid var(--bd2); border-radius:6px; background:#fff; padding:8px; max-height:260px; overflow:auto; margin-bottom:6px; }
    .preset-group{ padding:6px 4px; border-top:1px solid #eef2f7; }
    .preset-group:first-child{ border-top:none; }
    .preset-title{ font-weight:700; font-size:13px; color:#64748b; text-transform:uppercase; letter-spacing:.02em; margin:6px 2px; }
    .preset-list{ display:flex; flex-wrap:wrap; gap:8px; }
    .preset-badge{
      display:inline-flex; align-items:center; justify-content:center;
      padding:6px 10px; border-radius:20px; border:1px solid #cbd5e1; background:#f8fafc;
      color:#0f172a; cursor:pointer; user-select:none; transition:background .15s ease, border-color .15s ease, color .15s ease;
    }
    .preset-badge:hover{ background:#eef2f7; border-color:#94a3b8; }

    .tag-chips{ display:flex; flex-wrap:wrap; gap:8px; }
    .tag-chip{
      --chip-bg:#eef2f7; --chip-bd:#cbd5e1; --chip-tx:#0f172a;
      --chip-x-bg:#e2e8f0; --chip-x-tx:#334155; --chip-x-bg-hover:#e11d48; --chip-x-tx-hover:#ffffff;
      display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border:1px solid var(--chip-bd);
      background:var(--chip-bg); color:var(--chip-tx); border-radius:999px; line-height:1; font-size:14px;
      box-shadow:0 1px 0 rgba(15,23,42,.05);
    }
    .tag-chip .x{
      display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%;
      border:1px solid var(--chip-bd); background:var(--chip-x-bg); color:var(--chip-x-tx); cursor:pointer; line-height:1;
      font-weight:700; font-size:14px; padding:0; transition:background .15s ease, color .15s ease, transform .08s ease, border-color .15s ease;
      -webkit-appearance:none; appearance:none;
    }
    .tag-chip .x:hover{ background:var(--chip-x-bg-hover); border-color:var(--chip-x-bg-hover); color:var(--chip-x-tx-hover); }
    .tag-chip .x:focus{ outline:3px solid rgba(14,165,233,.35); outline-offset:1px; }
    .tag-chip .x:active{ transform:scale(.94); }

    /* Sugestões dropdown (para ambos autocompletes) */
    #auditor_suggest, #inspector_suggest{
      position:absolute; top:100%; left:0; right:0;
      background:#fff; border:1px solid var(--bd2); border-radius:6px;
      box-shadow:0 8px 20px rgba(0,0,0,.08);
      max-height:260px; overflow:auto; z-index:9999; display:none; padding:6px;
    }
    #auditor_suggest, #inspector_suggest, #supplier_suggest{
  position:absolute; top:100%; left:0; right:0;
  background:#fff; border:1px solid var(--bd2); border-radius:6px;
  box-shadow:0 8px 20px rgba(0,0,0,.08);
  max-height:260px; overflow:auto; z-index:9999; display:none; padding:6px;
}
#auditor_suggest, #inspector_suggest, #supplier_suggest,
#location_suggest, #category_suggest, #resolver_suggest{
  position:absolute; top:100%; left:0; right:0;
  background:#fff; border:1px solid var(--bd2); border-radius:6px;
  box-shadow:0 8px 20px rgba(0,0,0,.08);
  max-height:260px; overflow:auto; z-index:9999; display:none; padding:6px;
} 
  </style>
</head>
<body>
  <div class="card">

    <?php
      // Banner de sucesso (?created=ID)
      $created = $_GET['created'] ?? null;
      if ($created !== null && $created !== ''):
    ?>
      <div class="alert alert-success">
        Chamado <strong>#<?= htmlspecialchars((string)$created, ENT_QUOTES, 'UTF-8') ?></strong> criado com sucesso.
        <br>Você será redirecionado para o formulário em <span id="countdown">5</span> segundos…
      </div>
      <script>
        (function(){
          let timeLeft = 5;
          const el = document.getElementById('countdown');
          const t = setInterval(()=>{
            timeLeft--;
            if (el) el.textContent = String(timeLeft);
            if (timeLeft <= 0) {
              clearInterval(t);
              const url = new URL(window.location.href);
              url.searchParams.delete('created');
              window.history.replaceState({}, '', url.pathname + (url.search ? '?' + url.searchParams.toString() : ''));
              const first = document.querySelector('input[name="ticket_number"]');
              if (first) first.focus();
            }
          }, 1000);
        })();
      </script>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <h1 style="margin-top:0"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>

    <!-- FORM -->
    <form method="post" action="/audit-entries" novalidate>
      <div class="row">

        <!-- Número do Ticket -->
        <div class="col">
          <div class="field">
            <label for="ticket_number">Número Ticket *</label>
            <input
              id="ticket_number"
              name="ticket_number"
              required
              placeholder="INC1234567, RITM1234567, SCTASK1234567"
              value="<?= htmlspecialchars((string)($old['ticket_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
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

        <!-- Tipo do Ticket -->
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

        <!-- Auditor Kyndryl -->
        <div class="col" style="position:relative">
          <label for="kyndryl_auditor_input">Auditor Kyndryl *</label>

          <input id="kyndryl_auditor_input"
                 type="text"
                 autocomplete="off"
                 placeholder="Comece a digitar para buscar..."
                 value="<?= htmlspecialchars((string)($old['kyndryl_auditor'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                 required>

          <input type="hidden" name="kyndryl_auditor" id="kyndryl_auditor_value"
                 value="<?= htmlspecialchars((string)($old['kyndryl_auditor'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

          <input type="hidden" name="kyndryl_auditor_id" id="kyndryl_auditor_id"
                 value="<?= htmlspecialchars((string)($old['kyndryl_auditor_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

          <!-- Dropdown de sugestões -->
          <div id="auditor_suggest"></div>

          <div class="muted">Selecione um nome da lista.</div>
        </div>

        <!-- Inspetor Petrobras -->
        <div class="col" style="position:relative">
          <label for="petrobras_inspector_input">Inspetor Petrobras *</label>

          <input id="petrobras_inspector_input"
                 type="text"
                 autocomplete="off"
                 placeholder="Comece a digitar para buscar..."
                 value="<?= htmlspecialchars((string)($old['petrobras_inspector'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                 required>

          <input type="hidden" name="petrobras_inspector"
                 id="petrobras_inspector_value"
                 value="<?= htmlspecialchars((string)($old['petrobras_inspector'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

          <input type="hidden" name="petrobras_inspector_id"
                 id="petrobras_inspector_id"
                 value="<?= htmlspecialchars((string)($old['petrobras_inspector_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

          <!-- Dropdown de sugestões -->
          <div id="inspector_suggest"></div>

          <div class="muted">Selecione um nome da lista.</div>
        </div>

        <!-- Fornecedor Auditado (autocomplete) -->
<div class="col" style="position:relative">
  <label for="audited_supplier_input">Fornecedor Auditado *</label>

  <input id="audited_supplier_input"
         type="text"
         autocomplete="off"
         placeholder="Comece a digitar para buscar..."
         value="<?= htmlspecialchars((string)($old['audited_supplier'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
         required>

  <!-- Hidden com o valor oficial para o POST -->
  <input type="hidden" name="audited_supplier" id="audited_supplier_value"
         value="<?= htmlspecialchars((string)($old['audited_supplier'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

  <!-- (Opcional) Hidden com o ID do fornecedor -->
  <input type="hidden" name="audited_supplier_id" id="audited_supplier_id"
         value="<?= htmlspecialchars((string)($old['audited_supplier_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

  <!-- Dropdown de sugestões -->
  <div id="supplier_suggest"></div>

  <div class="muted">Selecione um nome da lista.</div>
</div>

        <!-- Localidade (autocomplete) -->
<div class="col" style="position:relative">
  <label for="location_input">Localidade *</label>

  <input id="location_input"
         type="text"
         autocomplete="off"
         placeholder="Comece a digitar para buscar..."
         value="<?= htmlspecialchars((string)($old['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
         required>

  <input type="hidden" name="location" id="location_value"
         value="<?= htmlspecialchars((string)($old['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

  <input type="hidden" name="location_id" id="location_id"
         value="<?= htmlspecialchars((string)($old['location_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

  <div id="location_suggest"></div>

  <div class="muted">Selecione uma localidade da lista.</div>
</div>

        <div class="col">
          <label for="audit_month">Mês da Auditoria *</label>
          <input
            id="audit_month"
            name="audit_month" required
            placeholder="fevereiro 2026, fev 2026, 02/2026 ou 2026-02"
            list="month_names" autocomplete="off"
            value="<?= htmlspecialchars((string)($old['audit_month'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
          >
          <datalist id="month_names">
            <option value="janeiro"></option><option value="fevereiro"></option><option value="março"></option>
            <option value="abril"></option><option value="maio"></option><option value="junho"></option>
            <option value="julho"></option><option value="agosto"></option><option value="setembro"></option>
            <option value="outubro"></option><option value="novembro"></option><option value="dezembro"></option>
          </datalist>
          <div class="muted">Dica: “fevereiro 2026”, “fev 2026”, “02/2026” ou “2026-02”.</div>
        </div>

        <!-- Prioridade -->
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
          <label for="requester_name">Solicitante *</label>
          <input id="requester_name" name="requester_name" required value="<?= htmlspecialchars((string)($old['requester_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <!-- Categoria (autocomplete) -->
<div class="col" style="position:relative">
  <label for="category_input">Categoria *</label>

  <input id="category_input"
         type="text"
         autocomplete="off"
         placeholder="Comece a digitar para buscar..."
         value="<?= htmlspecialchars((string)($old['category'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
         required>

  <input type="hidden" name="category" id="category_value"
         value="<?= htmlspecialchars((string)($old['category'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

  <input type="hidden" name="category_id" id="category_id"
         value="<?= htmlspecialchars((string)($old['category_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

  <div id="category_suggest"></div>

  <div class="muted">Selecione uma categoria da lista.</div>
</div>

        <!-- Mesa Solucionadora (autocomplete) -->
<div class="col" style="position:relative">
  <label for="resolver_group_input">Mesa Solucionadora *</label>

  <input id="resolver_group_input"
         type="text"
         autocomplete="off"
         placeholder="Comece a digitar para buscar..."
         value="<?= htmlspecialchars((string)($old['resolver_group'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
         required>

  <input type="hidden" name="resolver_group" id="resolver_group_value"
         value="<?= htmlspecialchars((string)($old['resolver_group'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

  <input type="hidden" name="resolver_group_id" id="resolver_group_id"
         value="<?= htmlspecialchars((string)($old['resolver_group_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

  <div id="resolver_suggest"></div>

  <div class="muted">Selecione uma mesa solucionadora da lista.</div>
</div>

        <!-- SLA -->
        <div class="col">
          <div class="field">
            <label>SLA Atingido? *</label>
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

        <!-- Conforme -->
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

        <!-- Justificativas -->
        <div class="col-full justify-block" id="just_block" style="display: <?= ((string)($old['is_compliant'] ?? '1')==='0'?'block':'none') ?>;">
          <label>Justificativas (obrigatórias quando Não conforme)</label>

          <!-- Busca/filtro -->
          <input type="text" id="nc_search" class="tag-input" placeholder="Buscar justificativas..." autocomplete="off">

          <!-- Presets do banco (agrupados) -->
          <div id="nc_presets" class="preset-wrap" aria-label="Presets de justificativas"></div>

          <!-- Chips selecionados -->
          <div id="nc_chips" class="tag-chips" aria-live="polite"></div>

          <!-- hidden enviado no POST (IDs separados por ;) -->
          <input type="hidden" name="noncompliance_reason_ids" id="nc_ids"
                 value="<?= htmlspecialchars((string)($old['noncompliance_reason_ids'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

          <div class="muted">Clique nos presets para adicionar. Clique na tag para remover.</div>
        </div>

        <!-- Ações (único bloco) -->
        <div class="col-full" style="margin-top:8px">
          <div class="actions" style="justify-content:flex-end">
            <button type="reset" class="btn btn-light" title="Limpar formulário">Limpar</button>
            <!-- Salvar abre o modal de confirmação -->
            <button id="btn-open-confirm" type="button" class="btn" title="Salvar chamado">Salvar</button>
          </div>
        </div>

      </div>
    </form>

    <!-- Ações (fora do form) -->
    <div class="actions" style="justify-content:flex-end;max-width:1000px;margin:12px auto 0">
      <a href="/export/csv" class="btn btn-gray">Exportar CSV (toda base)</a>
      <!-- Desativados por escolha:
      <a href="/export/bridge" class="btn btn-light">Exportar CSV (Justificativas)</a>
      <a href="/export/csv/full" class="btn btn-light">Exportar CSV (base + justificativas)</a> -->
      <button type="button" onclick="exportByMonth()" class="btn btn-info">Exportar CSV (mês do formulário)</button>
    </div>

  </div>

  <!-- Modal de Confirmação (dentro do body) -->
  <div id="confirm-overlay" aria-hidden="true"
       style="position:fixed; inset:0; background:rgba(17,24,39,.45); display:none; z-index:9998"></div>

  <div id="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-desc"
       style="position:fixed; inset:0; display:none; z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div style="width:min(560px, 96vw); background:#fff; border:1px solid var(--bd); border-radius:10px; box-shadow:0 12px 28px rgba(0,0,0,.18);">
      <div style="padding:16px 18px; border-bottom:1px solid var(--bd2);">
        <h2 id="confirm-title" style="margin:0; font-size:18px;">Confirmação antes de enviar</h2>
      </div>
      <div id="confirm-desc" style="padding:16px 18px; color:var(--tx);">
        <p style="margin:0 0 12px 0">Antes de prosseguir, confirme:</p>
        <label style="display:flex; gap:10px; align-items:flex-start; cursor:pointer;">
          <input id="ack-check" type="checkbox"
                 style="width:18px; height:18px; margin-top:2px; accent-color: var(--btn);">
          <span>
            Estou ciente de que <strong>todos os dados</strong> informados são verdadeiros e precisos, e autorizo o envio deste registro.
          </span>
        </label>
        <div class="muted" style="margin-top:8px">Você poderá revisar novamente em caso de erro.</div>
      </div>
      <div style="padding:12px 18px; border-top:1px solid var(--bd2); display:flex; gap:8px; justify-content:flex-end;">
        <button id="btn-cancel-confirm" type="button" class="btn btn-light" style="background:var(--btn3);" title="Voltar e revisar">
          Cancelar
        </button>
        <button id="btn-submit-confirm" type="button" class="btn" title="Confirmar e enviar" disabled
                style="opacity:.8; cursor:not-allowed;">
          Confirmar e Enviar
        </button>
      </div>
    </div>
  </div>

  <!-- Scripts -->
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

  <!-- TAG GROUP: busca / chips (IDs) -->
  <script>
  (function(){
    const input   = document.getElementById('nc_search');
    const presets = document.getElementById('nc_presets');
    const chips   = document.getElementById('nc_chips');
    const hidden  = document.getElementById('nc_ids');

    if (!input || !presets || !chips || !hidden) return;

    // Estado: Set de IDs e Map id->label
    const selectedIds = new Set(
      (hidden.value || '').split(/[;,]/)
        .map(s => s.trim()).filter(Boolean)
        .map(x => parseInt(x, 10))
        .filter(Number.isInteger)
    );
    const idToLabel = new Map();
    let allReasons = [];
    let filtered   = [];

    function updateHidden() {
      hidden.value = Array.from(selectedIds).join(';');
    }

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
        let res = await fetch('/api/catalog?resource=noncompliance-reasons');
        if (!res.ok) res = await fetch('/api/catalog?resource=noncompliance-reasons&q=');
        if (!res.ok) throw new Error('Falha ao carregar presets');

        const data = await res.json();
        allReasons = (Array.isArray(data) ? data : [])
          .map(r => ({
            id: parseInt(r.id, 10),
            // aceita "label" ou "noncompliance_reason"
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

    // Validação no submit: se Não conforme, exigir ao menos 1 ID
    const form = document.querySelector('form[action="/audit-entries"]');
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
  </script>

  <!-- Normalizador do número do ticket e sincronização do tipo -->
  <script>
  (function(){
    const tn = document.getElementById('ticket_number');
    if (!tn) return;

    // Radios do Tipo do Ticket
    const radios = Array.from(document.querySelectorAll('input[name="ticket_type"]'));

    // Mapa de prefixo -> valor do radio (como está no value dos inputs)
    const prefixMap = {
      INC:    'INCIDENTE',
      RITM:   'REQUISIÇÃO',
      SCTASK: 'TASK'
    };

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
      v = v.replace(/\s+/g, ''); // remove espaços
      tn.value = v;

      const ok = /^(INC|RITM|SCTASK)\d{6,}$/.test(v);
      tn.setCustomValidity(ok ? '' :
        'O ticket deve iniciar com INC, RITM ou SCTASK seguido de dígitos. Ex.: INC1234567');

      const m = /^(INC|RITM|SCTASK)/.exec(v);
      if (m) {
        const prefix = m[1];
        const targetValue = prefixMap[prefix]; // 'INCIDENTE' | 'REQUISIÇÃO' | 'TASK'
        if (targetValue) selectTicketType(targetValue);
      }
    }

    tn.addEventListener('input', normalizeTicket);
    tn.addEventListener('blur', normalizeTicket);

    const form = document.querySelector('form[action="/audit-entries"]');
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

    // Estado inicial
    normalizeTicket();
  })();
  </script>

  <!-- Normalizador de audit_month -->
  <script>
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

      // YYYY-MM
      if (/^\d{4}-(0[1-9]|1[0-2])$/.test(v)) return v;

      // MM/YYYY
      let m1 = v.match(/^(0?[1-9]|1[0-2])\s*\/\s*(\d{4})$/);
      if (m1) return `${m1[2]}-${String(m1[1]).padStart(2,'0')}`;

      // "fevereiro 2026"
      let m2 = v.match(/^([a-zçõ]+)\s+(\d{4})$/);
      if (m2 && map[m2[1]]) return `${m2[2]}-${map[m2[1]]}`;

      // só mês -> assume ano atual
      if (map[v]) {
        const year = new Date().getFullYear();
        return `${year}-${map[v]}`;
      }
      return v;
    }

    m.addEventListener('blur', ()=>{ m.value = norm(m.value); });
  })();
  </script>

  <!-- Autocomplete: Auditor Kyndryl -->
  <script>
(function(){
  const input   = document.getElementById('kyndryl_auditor_input');
  const hidden  = document.getElementById('kyndryl_auditor_value');
  const hidId   = document.getElementById('kyndryl_auditor_id');
  const popup   = document.getElementById('auditor_suggest');
  if (!input || !hidden || !hidId || !popup) return;

  let timer = null;
  let cache = []; // [{id,name}, ...]

  function closePopup(){ popup.style.display = 'none'; popup.innerHTML = ''; }
  function openPopup(){ popup.style.display = 'block'; }

  function renderList(items){
    popup.innerHTML = '';
    if (!items.length) { closePopup(); return; }
    const list = document.createElement('div');
    list.style.display = 'flex';
    list.style.flexDirection = 'column';
    items.forEach(it => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = it.name;
      btn.className = 'preset-badge';
      btn.style.textAlign = 'left';
      btn.style.margin = '4px';
      btn.onclick = () => selectItem(it);
      list.appendChild(btn);
    });
    popup.appendChild(list);
    openPopup();
  }

  async function fetchAuditors(q){
    try {
      const url = '/api/catalog?resource=kyndryl-auditors&q=' + encodeURIComponent(q || '');
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('fail');
      const data = await res.json();
      cache = Array.isArray(data) ? data : [];
      renderList(cache);
    } catch(e) {
      cache = [];
      closePopup();
      console.error(e);
    }
  }

  function selectItem(it){
    input.value  = it.name;
    hidden.value = it.name;
    hidId.value  = String(it.id);
    closePopup();
  }

  function clearSelection(){
    hidden.value = '';
    hidId.value  = '';
  }

  input.addEventListener('input', () => {
    clearSelection();
    const q = input.value.trim();
    clearTimeout(timer);
    if (q.length < 2) { closePopup(); return; }
    timer = setTimeout(() => fetchAuditors(q), 180);
  });

  input.addEventListener('focus', () => {
    const q = input.value.trim();
    if (q.length >= 2) fetchAuditors(q);
  });

  document.addEventListener('click', (e) => {
    if (!popup.contains(e.target) && e.target !== input) closePopup();
  });

  const form = document.querySelector('form[action="/audit-entries"]');
  if (form) {
    form.addEventListener('submit', (e) => {
      const vis = (input.value || '').trim();
      const sel = (hidden.value || '').trim();
      if (vis === '' || sel === '') {
        e.preventDefault();
        alert('Selecione um auditor válido da lista.');
        input.focus();
        return;
      }
      if (vis !== sel) {
        e.preventDefault();
        alert('O valor informado não corresponde a um auditor válido. Selecione na lista.');
        input.focus();
        return;
      }
    });
  }
})();
  </script>

  <!-- Autocomplete: Inspetor Petrobras (mesma formatação do Kyndryl) -->
  <script>
(function(){
  const input  = document.getElementById('petrobras_inspector_input');
  const hidden = document.getElementById('petrobras_inspector_value');
  const hidId  = document.getElementById('petrobras_inspector_id');
  const popup  = document.getElementById('inspector_suggest');
  if (!input || !hidden || !hidId || !popup) return;

  let timer = null;
  let cache = [];

  function closePopup(){ popup.style.display='none'; popup.innerHTML=''; }
  function openPopup(){ popup.style.display='block'; }

  function selectItem(it){
    const name = (it && (it.name || it.petrobras_inspector || it.label || '')) || '';
    input.value  = name;
    hidden.value = name;
    hidId.value  = String(it.id ?? '');
    closePopup();
  }

  function renderList(items){
    popup.innerHTML = '';
    if (!items || !items.length) { closePopup(); return; }

    // idêntico ao Kyndryl
    const list = document.createElement('div');
    list.style.display = 'flex';
    list.style.flexDirection = 'column';

    items.forEach(it => {
      const name = (it && (it.name || it.petrobras_inspector || it.label || '')) || '';
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

  async function fetchInspectors(q){
    try {
      const url = '/api/catalog?resource=petrobras-inspectors&q=' + encodeURIComponent(q || '');
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      cache = Array.isArray(data) ? data : [];
      renderList(cache);
    } catch(e) {
      cache = [];
      closePopup();
      console.error(e);
    }
  }

  function clearSelection(){ hidden.value=''; hidId.value=''; }

  input.addEventListener('input', () => {
    clearSelection();
    const q = input.value.trim();
    clearTimeout(timer);
    if (q.length < 2) { closePopup(); return; }
    timer = setTimeout(() => fetchInspectors(q), 180);
  });

  input.addEventListener('focus', () => {
    const q = input.value.trim();
    if (q.length >= 2) fetchInspectors(q);
  });

  document.addEventListener('click', (e) => {
    if (!popup.contains(e.target) && e.target !== input) closePopup();
  });

  const form = document.querySelector('form[action="/audit-entries"]');
  if (form) {
    form.addEventListener('submit', (e) => {
      const vis = (input.value || '').trim();
      const sel = (hidden.value || '').trim();
      if (vis === '' || sel === '') {
        e.preventDefault();
        alert('Selecione um inspetor Petrobras válido da lista.');
        input.focus();
        return;
      }
      if (vis !== sel) {
        e.preventDefault();
        alert('O valor informado não corresponde a um inspetor Petrobras válido. Selecione na lista.');
        input.focus();
        return;
      }
    });
  }
})();
  </script>

  <!-- Modal: controle -->
  <script>
(function(){
  const form        = document.querySelector('form[action="/audit-entries"]');
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
  </script>
  <script>
(function(){
  const input  = document.getElementById('audited_supplier_input');
  const hidden = document.getElementById('audited_supplier_value');
  const hidId  = document.getElementById('audited_supplier_id');
  const popup  = document.getElementById('supplier_suggest');
  if (!input || !hidden || !hidId || !popup) return;

  let timer = null;
  let cache = [];

  function closePopup(){ popup.style.display='none'; popup.innerHTML=''; }
  function openPopup(){ popup.style.display='block'; }

  function selectItem(it){
    const name = (it && (it.name || it.audited_supplier || it.label || '')) || '';
    input.value  = name;
    hidden.value = name;
    hidId.value  = String(it.id ?? '');
    closePopup();
  }

  function renderList(items){
    popup.innerHTML = '';
    if (!items || !items.length) { closePopup(); return; }

    // idêntico ao Kyndryl/Inspector: coluna + botões preset-badge
    const list = document.createElement('div');
    list.style.display = 'flex';
    list.style.flexDirection = 'column';

    items.forEach(it => {
      const name = (it && (it.name || it.audited_supplier || it.label || '')) || '';
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

  async function fetchSuppliers(q){
    try {
      const url = '/api/catalog?resource=audited-suppliers&q=' + encodeURIComponent(q || '');
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      cache = Array.isArray(data) ? data : [];
      renderList(cache);
    } catch(e) {
      cache = [];
      closePopup();
      console.error(e);
    }
  }

  function clearSelection(){ hidden.value=''; hidId.value=''; }

  input.addEventListener('input', () => {
    clearSelection();
    const q = input.value.trim();
    clearTimeout(timer);
    if (q.length < 2) { closePopup(); return; }
    timer = setTimeout(() => fetchSuppliers(q), 180);
  });

  input.addEventListener('focus', () => {
    const q = input.value.trim();
    if (q.length >= 2) fetchSuppliers(q);
  });

  document.addEventListener('click', (e) => {
    if (!popup.contains(e.target) && e.target !== input) closePopup();
  });

  // Validação no submit: idêntica às outras (auditor/inspector)
  const form = document.querySelector('form[action="/audit-entries"]');
  if (form) {
    form.addEventListener('submit', (e) => {
      const vis = (input.value || '').trim();
      const sel = (hidden.value || '').trim();
      if (vis === '' || sel === '') {
        e.preventDefault();
        alert('Selecione um fornecedor auditado válido da lista.');
        input.focus();
        return;
      }
      if (vis !== sel) {
        e.preventDefault();
        alert('O valor informado não corresponde a um fornecedor auditado válido. Selecione na lista.');
        input.focus();
        return;
      }
    });
  }
})();
</script>
<script>
(function(){
  const input  = document.getElementById('location_input');
  const hidden = document.getElementById('location_value');
  const hidId  = document.getElementById('location_id');
  const popup  = document.getElementById('location_suggest');
  if (!input || !hidden || !hidId || !popup) return;

  let timer = null, cache = [];

  function closePopup(){ popup.style.display='none'; popup.innerHTML=''; }
  function openPopup(){ popup.style.display='block'; }
  function clearSelection(){ hidden.value=''; hidId.value=''; }

  function selectItem(it){
    const name = (it && (it.name || it.location || it.label || '')) || '';
    input.value  = name;
    hidden.value = name;
    hidId.value  = String(it.id ?? '');
    closePopup();
  }

  function renderList(items){
    popup.innerHTML = '';
    if (!items?.length) { closePopup(); return; }
    const list = document.createElement('div');
    list.style.display = 'flex';
    list.style.flexDirection = 'column';
    items.forEach(it => {
      const name = (it && (it.name || it.location || it.label || '')) || '';
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
      const url = '/api/catalog?resource=locations&q=' + encodeURIComponent(q || '');
      const res = await fetch(url, { headers: { 'Accept':'application/json' } });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      cache = Array.isArray(data) ? data : [];
      renderList(cache);
    } catch(e){ cache = []; closePopup(); console.error(e); }
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

  const form = document.querySelector('form[action="/audit-entries"]');
  if (form) {
    form.addEventListener('submit', (e) => {
      const vis = (input.value || '').trim();
      const sel = (hidden.value || '').trim();
      if (vis === '' || sel === '') { e.preventDefault(); alert('Selecione uma localidade válida da lista.'); input.focus(); return; }
      if (vis !== sel)              { e.preventDefault(); alert('O valor informado não corresponde a uma localidade válida. Selecione na lista.'); input.focus(); return; }
    });
  }
})();
</script>
<script>
(function(){
  const input  = document.getElementById('category_input');
  const hidden = document.getElementById('category_value');
  const hidId  = document.getElementById('category_id');
  const popup  = document.getElementById('category_suggest');
  if (!input || !hidden || !hidId || !popup) return;

  let timer = null, cache = [];

  function closePopup(){ popup.style.display='none'; popup.innerHTML=''; }
  function openPopup(){ popup.style.display='block'; }
  function clearSelection(){ hidden.value=''; hidId.value=''; }

  function selectItem(it){
    const name = (it && (it.name || it.category || it.label || '')) || '';
    input.value  = name;
    hidden.value = name;
    hidId.value  = String(it.id ?? '');
    closePopup();
  }

  function renderList(items){
    popup.innerHTML = '';
    if (!items?.length) { closePopup(); return; }
    const list = document.createElement('div');
    list.style.display = 'flex';
    list.style.flexDirection = 'column';
    items.forEach(it => {
      const name = (it && (it.name || it.category || it.label || '')) || '';
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
      const url = '/api/catalog?resource=categories&q=' + encodeURIComponent(q || '');
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

  const form = document.querySelector('form[action="/audit-entries"]');
  if (form) {
    form.addEventListener('submit', (e) => {
      const vis = (input.value || '').trim();
      const sel = (hidden.value || '').trim();
      if (vis === '' || sel === '') { e.preventDefault(); alert('Selecione uma categoria válida da lista.'); input.focus(); return; }
      if (vis !== sel)              { e.preventDefault(); alert('O valor informado não corresponde a uma categoria válida. Selecione na lista.'); input.focus(); return; }
    });
  }
})();
</script>
<script>
(function(){
  const input  = document.getElementById('resolver_group_input');
  const hidden = document.getElementById('resolver_group_value');
  const hidId  = document.getElementById('resolver_group_id');
  const popup  = document.getElementById('resolver_suggest');
  if (!input || !hidden || !hidId || !popup) return;

  let timer = null, cache = [];

  function closePopup(){ popup.style.display='none'; popup.innerHTML=''; }
  function openPopup(){ popup.style.display='block'; }
  function clearSelection(){ hidden.value=''; hidId.value=''; }

  function selectItem(it){
    const name = (it && (it.name || it.resolver_group || it.label || '')) || '';
    input.value  = name;
    hidden.value = name;
    hidId.value  = String(it.id ?? '');
    closePopup();
  }

  function renderList(items){
    popup.innerHTML = '';
    if (!items?.length) { closePopup(); return; }
    const list = document.createElement('div');
    list.style.display = 'flex';
    list.style.flexDirection = 'column';
    items.forEach(it => {
      const name = (it && (it.name || it.resolver_group || it.label || '')) || '';
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
      const url = '/api/catalog?resource=resolver-groups&q=' + encodeURIComponent(q || '');
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

  const form = document.querySelector('form[action="/audit-entries"]');
  if (form) {
    form.addEventListener('submit', (e) => {
      const vis = (input.value || '').trim();
      const sel = (hidden.value || '').trim();
      if (vis === '' || sel === '') { e.preventDefault(); alert('Selecione uma mesa solucionadora válida da lista.'); input.focus(); return; }
      if (vis !== sel)              { e.preventDefault(); alert('O valor informado não corresponde a uma mesa solucionadora válida. Selecione na lista.'); input.focus(); return; }
    });
  }
})();
</script>
</body>
</html>