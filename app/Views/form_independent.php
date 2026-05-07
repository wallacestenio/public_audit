<?php
$title = $title ?? 'Auditoria de Chamados';
$old   = is_array($old ?? null) ? $old : [];
$error = $error ?? null;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- CSS do formulário -->
  <link rel="stylesheet"
        href="<?= htmlspecialchars($base ?? '', ENT_QUOTES, 'UTF-8') . '/assets/css/audit-form.css' ?>">
</head>

<body>

<div class="card">

  <form id="audit-form"
        method="post"
        action="<?= htmlspecialchars($base ?? '', ENT_QUOTES, 'UTF-8') . '/audit-entries' ?>">

    <!-- ================= TICKET ================= -->
    <div class="field">
      <label for="ticket_number">Número do Ticket *</label>
      <input
  id="ticket_number"
  name="ticket_number"
  required
  pattern="^(INC|RITM|REQ|SCTASK|TASK)[0-9]+$"
  autocomplete="off"
>
      <div id="ticket-feedback" class="field-feedback"></div>
    </div>

    <!-- Tipo do ticket (hidden) -->
    <input type="hidden" name="ticket_type" id="ticket_type_hidden">

    <div class="field">
      <label>Tipo do Ticket *</label>
      <div class="segmented" data-segmented="ticket_type">
        <button type="button" data-value="INCIDENTE">Incidente</button>
        <button type="button" data-value="REQUISIÇÃO">Requisição</button>
        <button type="button" data-value="TASK">Task</button>
      </div>
    </div>

    <!-- ================= AUDITOR ================= -->
    <div class="field">
      <label for="kyndryl_auditor_input">Auditor Kyndryl *</label>
      <input id="kyndryl_auditor_input" type="text" required autocomplete="off">
      <input type="hidden" name="kyndryl_auditor_id" id="kyndryl_auditor_id">
    </div>

    <!-- ================= INSPETOR ================= -->
    <div class="field">
      <label for="petrobras_inspector_input">Inspetor Petrobras *</label>
      <input id="petrobras_inspector_input" type="text" required autocomplete="off">
      <input type="hidden" name="petrobras_inspector_id" id="petrobras_inspector_id">
    </div>

    <!-- ================= FORNECEDOR ================= -->
    <div class="field">
      <label for="audited_supplier_input">Fornecedor Auditado *</label>
      <input id="audited_supplier_input" type="text" required autocomplete="off">
      <input type="hidden" name="audited_supplier_id" id="audited_supplier_id">
    </div>

    <!-- ================= LOCALIDADE ================= -->
    <div class="field">
      <label for="location_input">Localidade *</label>
      <input id="location_input" type="text" required autocomplete="off">
      <input type="hidden" name="location_id" id="location_id">
    </div>

    <!-- ================= MÊS ================= -->
    <div class="field">
      <label for="audit_month">Mês da Auditoria *</label>
      <input id="audit_month" name="audit_month" required placeholder="YYYY-MM">
    </div>

    <!-- ================= PRIORIDADE ================= -->
    <div class="field">
      <label>Prioridade *</label>
      <div class="segmented" data-segmented="priority">
        <button type="button" data-value="1">1</button>
        <button type="button" data-value="2">2</button>
        <button type="button" data-value="3">3</button>
        <button type="button" data-value="4">4</button>
      </div>
      <input type="hidden" name="priority">
    </div>

    <!-- ================= SLA ================= -->
    <div class="field">
      <label>ANS de tarefas atingido? *</label>
      <div class="segmented" data-segmented="sla_met">
        <button type="button" data-value="1">Sim</button>
        <button type="button" data-value="0" class="danger">Não</button>
      </div>
      <input type="hidden" name="sla_met">
    </div>

    <!-- ================= CONFORMIDADE ================= -->
    <div class="field">
      <label>Chamado Conforme? *</label>
      <div class="segmented" data-segmented="is_compliant">
        <button type="button" data-value="1">Sim</button>
        <button type="button" data-value="0" class="danger">Não</button>
      </div>
      <input type="hidden" name="is_compliant">
    </div>

    <!-- ================= JUSTIFICATIVAS ================= -->
    <div id="nc_block" class="field hidden">
      <label>Justificativas *</label>
      <input type="hidden" name="noncompliance_reason_ids" id="nc_ids">
      <div id="nc_presets"></div>
      <div id="nc_chips"></div>
    </div>

    <!-- ================= AÇÕES ================= -->
    <div class="actions">
      <button type="reset">Limpar</button>
      <button type="button" id="btn-submit">Salvar</button>
    </div>

  </form>
</div>

<!-- JS do formulário -->
<script src="<?= htmlspecialchars($base ?? '', ENT_QUOTES, 'UTF-8') . '/assets/js/audit-form.js' ?>"></script>

</body>
</html>