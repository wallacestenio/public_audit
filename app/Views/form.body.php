<h1 class="h4 mb-3">Novo Chamado Auditado</h1>

<form method="post" action="/audit-entries" class="row g-3">
  <div class="col-md-4">
    <label class="form-label">Número Ticket</label>
    <input name="ticket_number" class="form-control" required>
  </div>

  <div class="col-md-4">
    <label class="form-label">Tipo do Ticket</label>
    <input name="ticket_type" class="form-control ac" data-resource="ticket-types" placeholder="Digite para buscar…">
  </div>

  <div class="col-md-4">
    <label class="form-label">Auditor Kyndryl</label>
    <input name="kyndryl_auditor" class="form-control ac" data-resource="kyndryl-auditors">
  </div>

  <div class="col-md-4">
    <label class="form-label">Fiscal Petrobras</label>
    <input name="petrobras_inspector" class="form-control ac" data-resource="petrobras-inspectors">
  </div>

  <div class="col-md-4">
    <label class="form-label">Fornecedor Auditado</label>
    <input name="audited_supplier" class="form-control ac" data-resource="audited-suppliers">
  </div>

  <div class="col-md-4">
    <label class="form-label">Localidade</label>
    <input name="location" class="form-control ac" data-resource="locations">
  </div>

  <div class="col-md-4">
    <label class="form-label">Data da Auditoria</label>
    <input type="date" name="audit_date" class="form-control">
  </div>

  <div class="col-md-4">
    <label class="form-label">Prioridade</label>
    <input name="priority" class="form-control ac" data-resource="priorities">
  </div>

  <div class="col-md-4">
    <label class="form-label">Solicitante</label>
    <input name="requester_name" class="form-control" required>
  </div>

  <div class="col-md-4">
    <label class="form-label">Categoria</label>
    <input name="category" class="form-control ac" data-resource="categories">
  </div>

  <div class="col-md-4">
    <label class="form-label">Mesa Solucionadora</label>
    <input name="resolver_group" class="form-control ac" data-resource="resolver-groups">
  </div>

  <div class="col-md-4">
    <label class="form-label">Nível de Serviço Atingido?</label>
    <select name="sla_met" class="form-select">
      <option value="1">Sim</option>
      <option value="0">Não</option>
    </select>
  </div>

  <div class="col-md-4">
    <label class="form-label">Chamado Conforme?</label>
    <select name="is_compliant" id="is_compliant" class="form-select">
      <option value="1">Sim</option>
      <option value="0">Não</option>
    </select>
  </div>

  <div class="col-12" id="nc_block" style="display:none;">
    <label class="form-label">Justificativas da Não Conformidade</label>
    <input class="form-control ac-multi" data-resource="noncompliance-reasons" placeholder="Digite e tecle Enter para adicionar">
    <input type="hidden" name="noncompliance_reason" id="nc">
    <div id="nc_chips" class="mt-2"></div>
  </div>

  <div class="col-12 d-flex gap-2 mt-2">
    <button class="btn btn-primary">Salvar</button>
    <a class="btn btn-outline-secondary" href="/export/csv">Exportar CSV</a>
    <a class="btn btn-outline-secondary" href="/export/bridge">Exportar CSV (ponte)</a>
  </div>
</form>

<script>
document.getElementById('is_compliant').addEventListener('change', function(){
  document.getElementById('nc_block').style.display = (this.value === '0') ? 'block' : 'none';
});
</script>