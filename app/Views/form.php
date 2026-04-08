
<?php



//echo password_hash('adm123', PASSWORD_DEFAULT);



/*echo '<pre>';
var_dump($ref);
echo '</pre>';
exit;
*/


/**
 * View: Auditoria de Chamados
 * Variáveis: $title (string), $error (?string), $old (array), $base (string), $form_token_catalog (string)
 */
$title = $title ?? 'Auditoria de Chamados';
$old   = is_array($old ?? null) ? $old : [];
$error = $error ?? null;

// Banner ?created=ID
$created = $_GET['created'] ?? null;
?>

<div class="card">

<?php if ($created !== null && $created !== ''): ?>
<script>
  alert('Auditoria de Chamados atualizada com sucesso!');
</script>
<?php endif; ?>

<!-- ========================================================= -->
<!-- ✅ JUSTIFICATIVAS (MOVE ENTRE DISPONÍVEIS / SELECIONADAS) -->
<!-- ========================================================= -->
<script>
document.addEventListener('click', function (e) {
  const presetsEl = document.getElementById('nc_presets');
  const chipsEl   = document.getElementById('nc_chips');
  const idsInput  = document.getElementById('nc_ids');

  if (!presetsEl || !chipsEl || !idsInput) return;

  const available = e.target.closest('#nc_presets [data-id]');
  if (available) {
    available.textContent = available.textContent.replace(' ✕','') + ' ✕';
    chipsEl.appendChild(available);
    sync();
    return;
  }

  const selected = e.target.closest('#nc_chips [data-id]');
  if (selected) {
    selected.textContent = selected.textContent.replace(' ✕','');
    presetsEl.appendChild(selected);
    sync();
    return;
  }

  function sync() {
    idsInput.value = Array.from(
      chipsEl.querySelectorAll('[data-id]')
    ).map(el => el.dataset.id).join(';');
  }
});
</script>

<!-- ========================================================= -->
<!-- ✅ VALIDAÇÃO ASSÍNCRONA DE TICKET (ANTES DO SUBMIT) -->
<!-- ========================================================= -->
<script>
(() => {
  const input    = document.getElementById('ticket_number');
  const feedback = document.getElementById('ticket-feedback');
  const openBtn  = document.getElementById('btn-open-confirm');
  const submitBtn = document.getElementById('btn-submit-confirm');

  if (!input || !feedback || !openBtn) return;

  let timer   = null;
  let blocked = false;

  function lock(msg) {
    blocked = true;
    feedback.textContent = msg;
    feedback.style.display = 'block';
    feedback.style.color = '#dc2626';

    openBtn.disabled = true;
    submitBtn && (submitBtn.disabled = true);

    openBtn.style.opacity = '0.6';
  }

  function unlock() {
    blocked = false;
    feedback.style.display = 'none';

    openBtn.disabled = false;
    submitBtn && (submitBtn.disabled = false);

    openBtn.style.opacity = '';
  }

  input.addEventListener('input', () => {
    clearTimeout(timer);
    unlock();

    const v = input.value.trim();
    if (v.length < 8) return;

    timer = setTimeout(() => validate(v), 400);
  });

  openBtn.addEventListener('click', (e) => {
    if (blocked) {
      e.preventDefault();
      e.stopPropagation();
    }
  });

  async function validate(ticket) {
    try {
      const base  = '<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>';
      const token = '<?= htmlspecialchars($form_token_catalog ?? '', ENT_QUOTES, 'UTF-8') ?>';

      const res = await fetch(
        `${base}/api/validate/ticket?number=${encodeURIComponent(ticket)}`,
        {
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
      if (data.duplicate) {
        lock('⚠️ Este chamado já está cadastrado.');
      }

    } catch (err) {
      console.error('Erro na validação do ticket:', err);
    }
  }
})();
</script>

<!-- ========================================================= -->
<!-- ✅ CSS LOCAL DO FORM -->
<!-- ========================================================= -->
<style>
#nc_presets [data-id]{
  background:#f3f4f6;
  border:1px solid #d1d5db;
  margin:4px;
  padding:6px 10px;
  border-radius:6px;
  cursor:pointer;
}

#nc_chips [data-id]{
  background:#dc2626;
  color:#fff;
  margin:4px;
  padding:6px 10px;
  border-radius:16px;
  cursor:pointer;
}
</style>

<?php if (!empty($error)): ?>
<div class="alert alert-danger">
  <?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<!-- ========================================================= -->
<!-- ✅ FORM -->
<!-- ========================================================= -->

<form method="post"
      action="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/audit-entries"
      novalidate>

<div class="row">

  <!-- Número do Ticket -->
  <div class="col">
    <label for="ticket_number">Número Ticket *</label>
    <input id="ticket_number"
           name="ticket_number"
           required
           autofocus
           autocomplete="off"
           placeholder="INC1234567, RITM1234567, SCTASK1234567"
           pattern="^(INC|RITM|SCTASK)\d{6,}$"
           value="<?= htmlspecialchars($old['ticket_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div id="ticket-feedback" class="muted" style="display:none;margin-top:4px"></div>
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
        <input
          id="kyndryl_auditor_input"
          type="text"
          autocomplete="off"
          placeholder="Seu usuário (preenchido automaticamente)"
          value="<?= htmlspecialchars((string)($old['kyndryl_auditor'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
          <?= !empty($old['_lock_kyndryl_field']) ? 'readonly data-locked="1" aria-readonly="true" tabindex="-1"' : 'required' ?>
        >
        <input
          type="hidden"
          name="kyndryl_auditor"
          id="kyndryl_auditor_value"
          value="<?= htmlspecialchars((string)($old['kyndryl_auditor'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        >
        <input
          type="hidden"
          name="kyndryl_auditor_id"
          id="kyndryl_auditor_id"
          value="<?= htmlspecialchars((string)($old['kyndryl_auditor_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        >
        <div id="auditor_suggest"></div>
        <div class="muted">
          <?= !empty($old['_lock_kyndryl_field'])
            ? 'Este campo é determinado pela sua sessão.'
            : 'Selecione um nome da lista.' ?>
        </div>
      </div>

      <!-- Inspetor Petrobras -->
<div class="col" style="position:relative">
  <label for="petrobras_inspector_input">Inspetor Petrobras *</label>

  <!-- Campo visível -->
  <input id="petrobras_inspector_input"
         type="text"
         autocomplete="off"
         value="<?= htmlspecialchars($old['petrobras_inspector'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
         <?= !empty($old['_lock_inspector_field'])
              ? 'readonly tabindex="-1" aria-readonly="true"'
              : 'required' ?>>

  <!-- Nome (POST) -->
  <input type="hidden"
         name="petrobras_inspector"
         value="<?= htmlspecialchars($old['petrobras_inspector'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

  <!-- ID correto (FK) -->
  <input type="hidden"
         name="petrobras_inspector_id"
         value="<?= htmlspecialchars((string)($old['petrobras_inspector_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

  <div class="muted">
    <?= !empty($old['_lock_inspector_field'])
        ? 'Inspetor definido automaticamente com base no seu perfil.'
        : 'Selecione um inspetor da lista.' ?>
  </div>
</div>
``

      <!-- Fornecedor Auditado -->
      <div class="col" style="position:relative">
        <label for="audited_supplier_input">Fornecedor Auditado *</label>
        <input id="audited_supplier_input" type="text" autocomplete="off"
               placeholder="Comece a digitar para buscar..."
               value="<?= htmlspecialchars((string)($old['audited_supplier'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        <input type="hidden" name="audited_supplier" id="audited_supplier_value"
               value="<?= htmlspecialchars((string)($old['audited_supplier'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="audited_supplier_id" id="audited_supplier_id"
               value="<?= htmlspecialchars((string)($old['audited_supplier_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <div id="supplier_suggest"></div>
        <div class="muted">Selecione um nome da lista.</div>
      </div>

    <!-- Localidade -->
<div class="col" style="position:relative">
  <label for="location_input">Localidade *</label>

  <!-- Campo visível -->
  <input id="location_input"
         type="text"
         autocomplete="off"
         value="<?= htmlspecialchars($old['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
         <?= !empty($old['_lock_location_field'])
              ? 'readonly tabindex="-1" aria-readonly="true"'
              : 'required' ?>>

  <!-- Nome (POST) -->
  <input type="hidden"
         name="location"
         value="<?= htmlspecialchars($old['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

  <!-- ID correto (FK) -->
  <input type="hidden"
         name="location_id"
         value="<?= htmlspecialchars((string)($old['location_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

  <div class="muted">
    <?= !empty($old['_lock_location_field'])
        ? 'Localidade definida automaticamente com base no seu perfil.'
        : 'Selecione uma localidade da lista.' ?>
  </div>
</div>

      <!-- Mês da Auditoria -->
      <div class="col">
        <label for="audit_month">Mês da Auditoria *</label>
        <input id="audit_month" name="audit_month" required
               placeholder="fevereiro 2026, fev 2026, 02/2026 ou 2026-02"
               list="month_names" autocomplete="off"
               value="<?= htmlspecialchars((string)($old['audit_month'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
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

      <!-- Solicitante 
      <div class="col">
        <label for="requester_name">Solicitante *</label>
        <input id="requester_name" name="requester_name" required
               value="" placeholder="Campos Solicitante no ServiceNow">
      </div>
      -->

      <!-- Categoria -->

      <!-- Categoria -->
      <div class="col" style="position:relative">
        <label for="category_input">Categoria *</label>
        <input id="category_input" type="text" autocomplete="off" placeholder="Comece a digitar para buscar..."
               value="<?= htmlspecialchars((string)($old['category'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        <input type="hidden" name="category" id="category_value"
               value="<?= htmlspecialchars((string)($old['category'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="category_id" id="category_id"
               value="<?= htmlspecialchars((string)($old['category_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <div id="category_suggest"></div>
        <div class="muted">Selecione uma categoria da lista.</div>
      </div>

      <!-- Mesa Solucionadora -->
      <div class="col" style="position:relative">
        <label for="resolver_group_input">Mesa Solucionadora *</label>
        <input id="resolver_group_input" type="text" autocomplete="off" placeholder="Comece a digitar para buscar..."
               value="<?= htmlspecialchars((string)($old['resolver_group'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
        <input type="hidden" name="resolver_group" id="resolver_group_value"
               value="<?= htmlspecialchars((string)($old['resolver_group'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="resolver_group_id" id="resolver_group_id"
               value="<?= htmlspecialchars((string)($old['resolver_group_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <div id="resolver_suggest"></div>
        <div class="muted">Selecione uma mesa solucionadora da lista.</div>
      </div>

      <!-- SLA -->
      <!-- SLA -->
<div class="col" style="display:inline-block;min-width:240px;text-align:left">
  <div class="field">
    <label>ANS de tarefas atingido? *</label>
    <div class="segmented" role="radiogroup" aria-label="SLA Atingido">
      <?php
        $slaOld = (string)($old['sla_met'] ?? '1');
        $opts = ['1' => 'Sim', '0' => 'Não'];
        $i = 0;
        foreach ($opts as $val => $label) {
          $id = 'sla_met_' . (++$i);
          $checked = ($slaOld === $val) ? 'checked' : '';
          $req = ($i === 1) ? 'required' : '';
          $dangerClass = ($val === '0') ? ' danger-option' : '';

          echo "<input type='radio' id='{$id}' name='sla_met' value='{$val}' {$checked} {$req}>
                <label for='{$id}' class='segmented-btn{$dangerClass}' aria-pressed='".($checked ? 'true' : 'false')."'>{$label}</label>";
        }
      ?>
    </div>
  </div>
</div>

      <!-- Chamado Conforme -->
      <div class="col" style="display:inline-block;min-width:240px;text-align:left">
        <div class="field">
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

        <input type="text" id="nc_search" class="tag-input" placeholder="Buscar justificativas..." autocomplete="off">
        <div id="nc_presets" class="preset-wrap" aria-label="Presets de justificativas"></div>
        <div id="nc_chips" class="tag-chips" aria-live="polite"></div>

        <input type="hidden" name="noncompliance_reason_ids" id="nc_ids"
               value="<?= htmlspecialchars((string)($old['noncompliance_reason_ids'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

        <div class="muted">Clique nos presets para adicionar. Clique na tag para remover.</div>
      </div>

      <!-- Ações -->
      <div class="col-full" style="margin-top:8px">
        <div class="actions" style="justify-content:flex-end">
          <button type="reset" class="btn btn-light" title="Limpar formulário">Limpar</button>
          <button id="btn-open-confirm" type="button" class="btn" title="Salvar chamado">Salvar</button>
        </div>
      </div>

    </div>
  </form>

  <script>
(() => {
  const ticketInput = document.getElementById('ticket_number');
  if (!ticketInput) return;

  const map = {
    'INC': 'INCIDENTE',
    'RITM': 'REQUISIÇÃO',
    'SCTASK': 'TASK'
  };

  function selectTicketType(typeValue) {
    const radios = document.querySelectorAll('input[name="ticket_type"]');
    radios.forEach(radio => {
      const label = document.querySelector(`label[for="${radio.id}"]`);
      if (!label) return;

      if (radio.value === typeValue) {
        radio.checked = true;
        label.setAttribute('aria-pressed', 'true');
      } else {
        label.setAttribute('aria-pressed', 'false');
      }
    });
  }

  function detectType(value) {
    const v = value.toUpperCase().trim();

    for (const prefix in map) {
      if (v.startsWith(prefix)) {
        selectTicketType(map[prefix]);
        return;
      }
    }
  }

  ticketInput.addEventListener('input', () => {
    detectType(ticketInput.value);
  });

  // ✅ Detecta também se o campo já vier preenchido (refresh, voltar, erro)
  if (ticketInput.value) {
    detectType(ticketInput.value);
  }
})();
</script>

  <!-- SANFONA: Opções de exportação -->
  <section class="export-accordion" aria-label="Opções de exportação">
    <button id="export-toggle"
            class="btn export-toggle"
            type="button"
            aria-expanded="false"
            aria-controls="export-panel"
            title="Mostrar/ocultar opções de exportação">
      <span class="label-closed">Opções de exportação</span>
      <span class="label-open">Ocultar opções</span>
      <span class="chev">▾</span>
    </button>

    <div id="export-panel" class="export-panel" hidden>
      <div class="actions" style="justify-content:flex-end; margin:8px 0 0">
        <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/export/csv"
           class="btn btn-gray">Exportar CSV (toda base)</a>

        <button type="button" onclick="exportByMonth()" class="btn btn-info">
          Exportar CSV (mês do formulário)
        </button>
      </div>
    </div>
  </section>

</div>

<!-- Modal de Confirmação -->
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
        <input id="ack-check" type="checkbox" style="width:18px; height:18px; margin-top:2px; accent-color: var(--btn);">
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