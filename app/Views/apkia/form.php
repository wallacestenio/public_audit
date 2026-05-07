<br><br><br><br>
<link rel="stylesheet" href="/assets/css/audit-form.css">

<div class="container">
  <div class="card">

<?php if (!empty($_GET['duplicate'])): ?>
<script>
  alert('⚠️ Este chamado já foi registrado anteriormente.');
  window.location.href = '<?= htmlspecialchars($base ?? '') ?>/apkia';
</script>
<?php endif; ?>

    <h1>APKIA – Análise de Chamado</h1>

    <p class="muted">
      Cole abaixo o texto bruto do chamado do ServiceNow.
    </p>

    <hr>

    <form method="post" action="/apkia/process">

      <label>Texto do Chamado *</label>

      <textarea
  name="raw_text"
  rows="18"
  required
  placeholder="Cole aqui o texto completo do chamado do ServiceNow..."
  style="width:100%">
</textarea>

      <div class="actions" style="justify-content:flex-end; margin-top:16px">
        <button type="submit" class="btn">
          Analisar com APKIA
        </button>
      </div>

    </form>

  </div>
</div>