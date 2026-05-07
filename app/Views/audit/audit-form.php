<?php
declare(strict_types=1);

$base = $base ?? '';
$apkia = [];
?>

<?php if (!empty($_GET['erro']) && $_GET['erro'] === 'missing_nc'): ?>
<script>
alert('Selecione ao menos um motivo de não conformidade.');
</script>
<?php endif; ?>

<?php
/**
 * Entrada via APKIA
 */
if (!empty($_GET['from']) && $_GET['from'] === 'apkia' && !empty($_GET['data'])) {
    $decoded = json_decode(urldecode($_GET['data']), true);
    if (is_array($decoded)) {
        $apkia = $decoded;
    }
}

/**
 * SLA
 */
$slaMet = array_key_exists('sla_met', $apkia)
    ? (int)$apkia['sla_met']
    : null;

/**
 * Tipo do Ticket
 */
$types = ['INC', 'REQ', 'TASK'];

$rawType = strtoupper(trim((string)($apkia['ticket_type'] ?? '')));
$currentType = match ($rawType) {
    'INC', 'INCIDENT', 'INCIDENTE' => 'INC',
    'RITM', 'REQUEST', 'REQUISICAO', 'REQUISIÇÃO' => 'REQ',
    'TASK', 'SCTASK' => 'TASK',
    default => '',
};
?>

<link rel="stylesheet" href="<?= $base ?>/assets/css/audit-form.css">


<div class="container">
<br><br><br>  
<div class="card">

    <h1>Auditoria de Chamado</h1>

    <!-- CONTEXTO -->
    <div class="row">
      <div class="col">
        <strong>Auditor (Kyndryl)</strong>
        <div><?= htmlspecialchars($auditor['name'] ?? '—') ?></div>
      </div>

      <div class="col">
        <strong>Inspetor Petrobras</strong>
        <div><?= htmlspecialchars($inspector['name'] ?? '—') ?></div>
      </div>

      <div class="col">
        <strong>Localidade</strong>
        <div><?= htmlspecialchars($location['name'] ?? '—') ?></div>
      </div>
    </div>

    <hr>

    <!-- ALERTAS APKIA -->
    <div class="alert alert-info">
      <strong>Sugestão do APKIA</strong><br>
      O APKIA analisou o chamado e preencheu este formulário automaticamente.<br>
      <strong>Ele não decide.</strong> A decisão final é do auditor.
    </div>

    <?php if (!empty($apkia['suggestions'])): ?>
      <div class="card" style="margin-top:12px; border-left:4px solid #f59e0b">
        <h4>Possíveis inconsistências</h4>
        <ul>
          <?php foreach ($apkia['suggestions'] as $s): ?>
            <li>
              <strong>[<?= htmlspecialchars($s['severity']) ?>]</strong>
              <?= htmlspecialchars($s['message']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="muted">Avalie antes de decidir.</p>
      </div>
    <?php endif; ?>

    <hr>

    <!-- FORM -->
    <form method="post" action="<?= htmlspecialchars($base) ?>/audit/confirm">

      <div class="row">
        <div class="col">
          <label>Número do Ticket *</label>
          <input name="ticket_number" required
                 value="<?= htmlspecialchars($apkia['ticket_number'] ?? '') ?>">
        </div>

        
<div class="row">
  <div class="col">
    <label>Tipo do Ticket *</label>
    <div class="segmented">
      <?php foreach ($types as $i => $type): ?>
        <?php $id = 'ticket_type_' . $i; ?>
        <input
          type="radio"
          id="<?= $id ?>"
          name="ticket_type"
          value="<?= $type ?>"
          <?= $currentType === $type ? 'checked' : '' ?>
          required
        >
        <label for="<?= $id ?>" class="segmented-btn">
          <?= $type ?>
        </label>
      <?php endforeach; ?>
    </div>
  </div>
</div>


        <div class="col">
          <label>Mês da Auditoria *</label>
          <input name="audit_month" required
                 value="<?= htmlspecialchars($apkia['audit_month'] ?? '') ?>">
        </div>

        <div class="col">
          <label>Prioridade *</label>
          <select name="priority" required>
            <option value="">Selecione…</option>
            <?php for ($i = 1; $i <= 4; $i++): ?>
              <option value="<?= $i ?>"
                <?= isset($apkia['priority']) && (int)$apkia['priority'] === $i ? 'selected' : '' ?>>
                <?= $i ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
      </div>

      <div class="row">
        <div class="col">
          <label>Mesa Solucionadora *</label>
          <input name="resolver_group" required
                 value="<?= htmlspecialchars($apkia['resolver_group'] ?? '') ?>">
        </div>

        <div class="col">
          <label>Categoria *</label>
          <select name="category" required>
            <option value="">Selecione…</option>
            <?php foreach ($categories ?? [] as $cat): ?>
              <option value="<?= htmlspecialchars($cat['name']) ?>">
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col">
          <label>Fornecedor Auditado *</label>
          <select name="audited_supplier" required>
            <option value="">Selecione…</option>
            <?php foreach ($suppliers ?? [] as $supplier): ?>
              <option value="<?= (int)$supplier['id'] ?>">
                <?= htmlspecialchars($supplier['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <hr>

      <div class="row">
        <div class="col">
          <label>ANS de tarefas atingido?</label>
          <div class="segmented">
            <input type="radio" name="sla_met" value="1" <?= $slaMet === 1 ? 'checked' : '' ?> required>
            <label class="segmented-btn">Sim</label>
            <input type="radio" name="sla_met" value="0" <?= $slaMet === 0 ? 'checked' : '' ?>>
            <label class="segmented-btn">Não</label>
          </div>
        </div>

        <div class="col">
          <label>Chamado Conforme?</label>
          <div class="segmented">
           
<?php
$idYes = 'is_compliant_1';
$idNo  = 'is_compliant_0';
?>

<input type="radio" id="<?= $idYes ?>" name="is_compliant" value="1" required>
<label for="<?= $idYes ?>" class="segmented-btn">Sim</label>

<input type="radio" id="<?= $idNo ?>" name="is_compliant" value="0">
<label for="<?= $idNo ?>" class="segmented-btn">Não</label>
 

          </div>
        </div>
      </div>

      <div id="noncompliance-block" class="alert alert-danger" style="display:none; margin-top:8px">
  <strong>Motivos da Não Conformidade</strong>
  <p class="muted">Obrigatório quando o chamado não estiver conforme ou o SLA não for atingido.</p>

  <?php foreach ($noncomplianceReasons ?? [] as $reason): ?>
    <div>
      <label>
        <input type="checkbox"
               name="noncompliance_reason_ids[]"
               value="<?= (int)$reason['id'] ?>">
        <?= htmlspecialchars($reason['noncompliance_reason']) ?>
      </label>
    </div>
  <?php endforeach; ?>
</div>


      <div class="actions" style="justify-content:flex-end">
        <button type="submit" class="btn">Continuar</button>
      </div>

    </form>

  </div>
</div>
<script>
function toggleNonCompliance() {
  const sla = document.querySelector('input[name="sla_met"]:checked')?.value;
  const compliant = document.querySelector('input[name="is_compliant"]:checked')?.value;

  const block = document.getElementById('noncompliance-block');
  if (!block) return;

  // Mostra se SLA = Não OU Chamado Conforme = Não
  block.style.display =
    (sla === '0' || compliant === '0') ? 'block' : 'none';
}

// Escuta ambos
document
  .querySelectorAll('input[name="sla_met"], input[name="is_compliant"]')
  .forEach(el => el.addEventListener('change', toggleNonCompliance));

// Executa no carregamento (APKIA pode vir preenchido)
toggleNonCompliance();
</script>
<script>
const form = document.querySelector('form');

form.addEventListener('submit', function(e) {

  const sla = document.querySelector('input[name="sla_met"]:checked')?.value;
  const compliant = document.querySelector('input[name="is_compliant"]:checked')?.value;

  const precisaMotivo = (sla === '0' || compliant === '0');

  if (precisaMotivo) {

    const selecionados = document.querySelectorAll(
      'input[name="noncompliance_reason_ids[]"]:checked'
    );

    if (selecionados.length === 0) {

      // 🔴 AQUI É O BLOQUEIO REAL
      e.preventDefault();

      alert('Selecione ao menos um motivo de não conformidade antes de continuar.');

      // destaque visual
      const block = document.getElementById('noncompliance-block');
      if (block) {
        block.style.border = '2px solid red';
        block.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }

      return;
    }
  }

});
</script>
