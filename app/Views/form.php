<?php
/**
 * View: Formulário de Chamados
 * Esta view não possui CSS/JS inline. Toda a formatação está em /assets/style.css
 * e toda a lógica em /assets/scripts.js, carregados pelo layout.php.
 *
 * Variáveis esperadas: $title (string), $error (string|null), $old (array)
 */
$title = $title ?? 'Formulário de Chamados';
$old   = is_array($old ?? null) ? $old : [];
$error = $error ?? null;

ob_start();
?>
<div class="card">

  <?php
    // Banner de sucesso (?created=ID)
    $created = $_GET['created'] ?? null;
    if ($created !== null && $created !== ''):
  ?>
    <div class="alert alert-success" id="success-banner" data-redirect="true">
      Chamado <strong>#<?= htmlspecialchars((string)$created, ENT_QUOTES, 'UTF-8') ?></strong> criado com sucesso.
      <br>Você será redirecionado para o formulário em <span id="countdown">5</span> segundos…
      <div id="motivation_msgs" class="muted" style="margin-top:8px">
        <span class="msg">Ótimo trabalho! Continue nessa pegada. 🚀</span>
        <span class="msg" style="display:none">Cada registro preciso melhora a operação. 💪</span>
        <span class="msg" style="display:none">Disciplina hoje, resultado amanhã. ✅</span>
      </div>
    </div>
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
          <div id="ticket_error" class="field-error" aria-live="polite" style="display:none"></div>
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

        <input id="kyndryl_auditor_input" type="text" autocomplete="off"
               placeholder="Comece a digitar para buscar..."
               value="<?= htmlspecialchars((string)($old['kyndryl_auditor'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
               required>

        <input type="hidden" name="kyndryl_auditor" id="kyndryl_auditor_value"
               value="<?= htmlspecialchars((string)($old['kyndryl_auditor'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

        <input type="hidden" name="kyndryl_auditor_id" id="kyndryl_auditor_id"
               value="<?= htmlspecialchars((string)($old['kyndryl_auditor_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

        <div id="auditor_suggest"></div>
        <div class="muted">Selecione um nome da lista.</div>
      </div>

      <!-- Inspetor Petrobras -->
      <div class="col" style="position:relative">
        <label for="petrobras_inspector_input">Inspetor Petrobras *</label>

        <input id="petrobras_inspector_input" type="text" autocomplete="off"
               placeholder="Comece a digitar para buscar..."
               value="<?= htmlspecialchars((string)($old['petrobras_inspector'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
               required>

        <input type="hidden" name="petrobras_inspector" id="petrobras_inspector_value"
               value="<?= htmlspecialchars((string)($old['petrobras_inspector'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

        <input type="hidden" name="petrobras_inspector_id" id="petrobras_inspector_id"
               value="<?= htmlspecialchars((string)($old['petrobras_inspector_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

        <div id="inspector_suggest"></div>
        <div class="muted">Selecione um nome da lista.</div>
      </div>

      <!-- Fornecedor Auditado -->
      <div class="col" style="position:relative">
        <label for="audited_supplier_input">Fornecedor Auditado *</label>

        <input id="audited_supplier_input" type="text" autocomplete="off"
               placeholder="Comece a digitar para buscar..."
               value="<?= htmlspecialchars((string)($old['audited_supplier'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
               required>

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

        <input id="location_input" type="text" autocomplete="off"
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

      <!-- Solicitante -->
      <div class="col">
        <label for="requester_name">Solicitante *</label>
        <input id="requester_name" name="requester_name" required
               value="<?= htmlspecialchars((string)($old['requester_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <!-- Categoria -->
      <div class="col" style="position:relative">
        <label for="category_input">Categoria *</label>

        <input id="category_input" type="text" autocomplete="off"
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

      <!-- Mesa Solucionadora -->
      <div class="col" style="position:relative">
        <label for="resolver_group_input">Mesa Solucionadora *</label>

        <input id="resolver_group_input" type="text" autocomplete="off"
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
              // Não define "1" por padrão (evita pré-seleção visual)
              $slaOld = isset($old['sla_met']) ? (string)$old['sla_met'] : '';
              $opts=['1'=>'Sim','0'=>'Não']; $i=0;
              foreach($opts as $val=>$label){
                $id='sla_met_'.(++$i); $checked=($slaOld!=='' && $slaOld===$val)?'checked':''; $req=($i===1)?'required':'';
                echo "<input type='radio' id='{$id}' name='sla_met' value='{$val}' {$checked} {$req}>
                      <label for='{$id}' class='segmented-btn' aria-pressed='".($checked?'true':'false')."'>{$label}</label>";
              }
            ?>
          </div>
          <div class="muted">Selecione <strong>Sim</strong> ou <strong>Não</strong>.</div>
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

      <!-- Ações -->
      <div class="col-full" style="margin-top:8px">
        <div class="actions" style="justify-content:flex-end">
          <button type="reset" class="btn btn-light" title="Limpar formulário">Limpar</button>
          <button id="btn-open-confirm" type="button" class="btn" title="Salvar chamado">Salvar</button>
        </div>
      </div>

    </div>
  </form>

  <!-- SANFONA DE EXPORTAÇÃO -->
  <div class="export-accordion" style="max-width:1000px;margin:12px auto 0">
    <button id="export_toggle" type="button" class="btn btn-light export-toggle"
            title="Mostrar/Ocultar opções de exportação" aria-expanded="false" aria-controls="export_panel">
      <span class="label-closed">Opções de Exportação</span>
      <span class="label-open"   style="display:none">Ocultar opções</span>
      <span class="chev" aria-hidden="true">▼</span>
    </button>

    <div id="export_panel" class="export-panel" hidden>
      <div class="actions" style="justify-content:flex-end">
        <a href="/export/csv" class="btn btn-gray">Exportar CSV (toda base)</a>
        <button id="btn-export-month" type="button" class="btn btn-info">Exportar CSV (mês do formulário)</button>
      </div>
    </div>
  </div>

</div>

<!-- Modal de Confirmação (ENVIO) -->
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

<!-- Modal de Confirmação (LIMPAR JUSTIFICATIVAS ao mudar para SIM) -->
<div id="nc-overlay" aria-hidden="true"
     style="position:fixed; inset:0; background:rgba(17,24,39,.45); display:none; z-index:9994"></div>

<div id="nc-modal" role="dialog" aria-modal="true" aria-labelledby="nc-title" aria-describedby="nc-desc"
     style="position:fixed; inset:0; display:none; z-index:9995; align-items:center; justify-content:center; padding:16px;">
  <div style="width:min(520px, 96vw); background:#fff; border:1px solid var(--bd); border-radius:10px; box-shadow:0 12px 28px rgba(0,0,0,.18);">
    <div style="padding:14px 16px; border-bottom:1px solid var(--bd2);">
      <h2 id="nc-title" style="margin:0; font-size:18px;">Alterar para <strong>Conforme</strong></h2>
    </div>
    <div id="nc-desc" style="padding:14px 16px; color:var(--tx);">
      <p style="margin:0 0 10px 0">
        Você está mudando o chamado para <strong>Sim (Conforme)</strong>.<br>
        Todas as <strong>não conformidades selecionadas</strong> serão <strong>apagadas</strong>.
      </p>
      <div class="muted">Confirme para limpar as justificativas e continuar.</div>
    </div>
    <div style="padding:12px 16px; border-top:1px solid var(--bd2); display:flex; gap:8px; justify-content:flex-end;">
      <button id="nc-cancel" type="button" class="btn btn-light" style="background:var(--btn3);">Cancelar</button>
      <button id="nc-confirm" type="button" class="btn">Estou ciente e limpar</button>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';