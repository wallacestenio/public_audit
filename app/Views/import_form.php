<?php



$error   = $_SESSION['flash_error']   ?? null;
$success = $_SESSION['flash_success'] ?? null;

unset($_SESSION['flash_error'], $_SESSION['flash_success']);
?>

<div class="card" style="max-width:600px;margin:0 auto">
  <h2>Importar Planilha de Auditoria</h2>

  <?php if ($error): ?>
    <div class="alert alert-danger">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <form method="post" action="/import" enctype="multipart/form-data">

    <div class="field">
      <label>Selecione o arquivo (CSV)</label>
      <input
        type="file"
        name="file"
        accept=".csv"
        required
      >
    </div>

    <div class="actions" style="margin-top:16px">
      <button type="submit" class="btn">
        Importar
      </button>
    </div>

  </form>

  <p class="muted" style="margin-top:12px">
    O arquivo deve seguir exatamente o layout padrão exportado pelo sistema.
  </p>
</div>