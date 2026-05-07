
<link rel="stylesheet" href="/assets/style.css">

<div class="container">
  <div class="card">

    <h2><div><?= htmlspecialchars($entry['ticket_number']) ?></div> Auditado.</h2>

    <!-- === Identificação do chamado === -->
    <div class="row">
      <div class="col">
        <strong>Ticket</strong>
        <div><?= htmlspecialchars($entry['ticket_number']) ?></div>
      </div>

      <div class="col">
        <strong>Tipo</strong>
        <div><?= htmlspecialchars($entry['ticket_type']) ?></div>
      </div>

      <div class="col">
        <strong>Mês da Auditoria</strong>
        <div><?= htmlspecialchars($entry['audit_month']) ?></div>
      </div>

      <div class="col">
        <strong>Prioridade</strong>
        <div><?= htmlspecialchars((string)$entry['priority']) ?></div>
      </div>
    </div>

    <hr>

    <!-- === Pessoas / Contexto === -->
    <div class="row">
      <div class="col">
        <strong>Auditor (Kyndryl)</strong>
        <div><?= htmlspecialchars($entry['kyndryl_auditor']) ?></div>
      </div>

      <div class="col">
        <strong>Inspetor Petrobras</strong>
        <div><?= htmlspecialchars($entry['petrobras_inspector']) ?></div>
      </div>

      <div class="col">
        <strong>Localidade</strong>
        <div><?= htmlspecialchars($entry['location']) ?></div>
      </div>

      <div class="col">
        <strong>Fornecedor Auditado</strong>
        <div><?= htmlspecialchars($entry['audited_supplier']) ?></div>
      </div>
    </div>

    <hr>

    <!-- === Classificação / Resultado === -->
    <div class="row">
      <div class="col">
        <strong>Categoria</strong>
        <div><?= htmlspecialchars($entry['category']) ?></div>
      </div>

      <div class="col">
        <strong>Mesa Solucionadora</strong>
        <div><?= htmlspecialchars($entry['resolver_group']) ?></div>
      </div>

      <div class="col">
        <strong>ANS atingido?</strong>
        <div>
          <?php if ($entry['sla_met']): ?>
            <span class="status-badge success">Sim</span>
          <?php else: ?>
            <span class="status-badge danger">Não</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="col">
        <strong>Chamado Conforme?</strong>
        <div>
          <?php if ($entry['is_compliant']): ?>
            <span class="status-badge success">Sim</span>
          <?php else: ?>
            <span class="status-badge danger">Não</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!$entry['is_compliant']): ?>
      <hr>

      <div class="alert-danger">
        <strong>Motivos da Não Conformidade</strong>
        <div style="margin-top:8px">
          <?= nl2br(htmlspecialchars($entry['noncompliance_reasons'])) ?>
        </div>
      </div>
    <?php endif; ?>


<hr>
<div style="margin-top:20px">
  <a href="/apkia" class="btn">
    ✅ Nova Auditoria
  </a>
</div>
