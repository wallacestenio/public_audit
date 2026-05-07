
<link rel="stylesheet" href="/assets/style.css">

<div class="container">
  <br><br><br>
  <div class="card">

    <h1>Confirmar as Informações</h1>

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


<form method="post" action="/audit/save">

<?php
// ✅ campos que o save() espera receber
$fields = [
    'ticket_number',
    'ticket_type',
    'audit_month',
    'priority',
    'category',
    'resolver_group',
    'sla_met',
    'is_compliant',
    'audited_supplier',
    'noncompliance_reason_ids',
    'noncompliance_reasons',
];
?>

<?php foreach ($fields as $field): ?>
    <?php if (isset($entry[$field])): ?>
        <?php if (is_array($entry[$field])): ?>
            <?php foreach ($entry[$field] as $v): ?>
                <input
                    type="hidden"
                    name="<?= $field ?>[]"
                    value="<?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>"
                >
            <?php endforeach; ?>
        <?php else: ?>
            <input
                type="hidden"
                name="<?= $field ?>"
                value="<?= htmlspecialchars((string)$entry[$field], ENT_QUOTES, 'UTF-8') ?>"
            >
        <?php endif; ?>
    <?php endif; ?>
<?php endforeach; ?>

    <div style="margin-top:20px">
        <button type="submit">✅ Salvar Auditoria</button>
        <a href="javascript:history.back()" style="margin-left:16px">
            ⬅ Voltar para edição
        </a>
    </div>

</form>



