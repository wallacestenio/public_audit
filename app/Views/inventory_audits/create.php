<h2>Auditoria de Estoque</h2>

<div class="card">

  <form method="POST" action="/inventory-audits">

    <!-- CSRF -->
    <input type="hidden" name="csrf_token"
           value="<?= $_SESSION['csrf_token'] ?? '' ?>">

    <!-- =========================
         LINHA 1 — 4 CAMPOS (GRID)
         ========================= -->
    <div class="grid-4">

      <!-- MÊS -->
      <div class="field">
        <label>Mês da Auditoria *</label>
        <input type="text"
               name="audit_month"
               placeholder="Janeiro, Fevereiro, Março..."
               required>
        <small class="hint">
          Em janeiro, auditorias de dezembro são registradas como do ano anterior.
        </small>
      </div>

      <!-- AUDITOR -->
      <div class="field">
        <label>Auditor Kyndryl *</label>
        <input type="text"
               value="<?= htmlspecialchars($_SESSION['user']['name'] ?? '') ?>"
               readonly>
        <input type="hidden"
               name="auditor_user_id"
               value="<?= htmlspecialchars($_SESSION['user']['id'] ?? '') ?>">
        <small class="hint">Este campo é determinado pela sua sessão.</small>
      </div>

      <!-- LOCALIDADE -->
      <div class="field">
        <label>Localidade *</label>
        <select name="location_id" required>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= $loc['id'] ?>"
              <?= (($_SESSION['user']['location_id'] ?? null) == $loc['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['location']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small class="hint">
          Localidade definida automaticamente com base no seu perfil.
        </small>
      </div>

      <!-- ITEM -->
      <div class="field">
        <label>Item Auditado *</label>
        <select name="item_id" required>
          <option value="">Selecione…</option>
          <?php foreach ($inventoryItems as $item): ?>
            <option value="<?= $item['id'] ?>">
              <?= htmlspecialchars($item['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

    </div>

    <!-- =========================
         LINHA 2 — 3 CAMPOS (GRID)
         ========================= -->
    <div class="grid-3">

      <div class="field">
        <label>BDGC *</label>
        <input type="number"
               name="bdgc_quantity"
               id="bdgc_quantity"
               min="0"
               required>
      </div>

      <div class="field">
        <label>Encontrados *</label>
        <input type="number"
               name="found_quantity"
               id="found_quantity"
               min="0"
               required>
      </div>

      <div class="field divergence-field">
        <label>Divergência</label>
        <input type="number"
               id="divergence_quantity_display"
               readonly>
        <input type="hidden"
               name="divergence_quantity"
               id="divergence_quantity">
        <small class="hint">
          Calculado automaticamente (Encontrados − BDGC).
        </small>
      </div>

    </div>

    <!-- =========================
         OBSERVAÇÕES
         ========================= -->
    <div class="field" style="margin-top:16px;">
      <label>Observações</label>
      <textarea name="divergence_notes"
                rows="4"
                placeholder="Detalhe as divergências encontradas (opcional)"></textarea>
    </div>

    <!-- AÇÕES -->
    <div class="actions">
      <button type="submit" class="btn btn-primary">Salvar</button>
    </div>

  </form>

</div>

  <script>
/**
 * ✅ Divergência Auditoria de Estoque
 * Totalmente isolado — NÃO depende do scripts.js global
 */
(function () {
  const bdgcInput   = document.getElementById('bdgc_quantity');
  const foundInput  = document.getElementById('found_quantity');
  const display     = document.getElementById('divergence_quantity_display');
  const hiddenField = document.getElementById('divergence_quantity');

  if (!bdgcInput || !foundInput || !display || !hiddenField) {
    return;
  }

  function updateDivergence() {
    const bdgc  = Number(bdgcInput.value);
    const found = Number(foundInput.value);

    // Campos ainda vazios
    if (!Number.isFinite(bdgc) || !Number.isFinite(found)) {
      display.value = '';
      hiddenField.value = '';
      display.classList.remove('divergence-ok', 'divergence-error');
      return;
    }

    const diff = found - bdgc;
    const absDiff = Math.abs(diff);

    display.value = absDiff;
    hiddenField.value = absDiff;

    display.classList.remove('divergence-ok', 'divergence-error');

    if (diff === 0) {
      display.classList.add('divergence-ok'); // ✅ verde
    } else {
      display.classList.add('divergence-error'); // ❌ vermelho
    }
  }

  bdgcInput.addEventListener('input', updateDivergence);
  foundInput.addEventListener('input', updateDivergence);
})();
</script>
<style>
/* ===== GRID PADRÃO ===== */
.grid-4 {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.grid-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-top: 16px;
}

/* RESPONSIVO */
@media (max-width: 980px) {
  .grid-4 { grid-template-columns: repeat(2, 1fr); }
  .grid-3 { grid-template-columns: 1fr; }
}

@media (max-width: 640px) {
  .grid-4 { grid-template-columns: 1fr; }
}

/* DIVERGÊNCIA */
.divergence-field {
  max-width: 220px;
  justify-self: center;
  text-align: center;
}   
</style>

</div>