<?php
$title = 'Cadastro de Usuário';
$old   = $old   ?? [];
$error = $error ?? null;
?>

<div class="card" style="max-width:680px;margin:0 auto">
  <h2>Cadastro de Usuário</h2>

  <?php if ($error): ?>
    <div class="alert alert-danger">
      <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>
  
<style>
    .field{
  margin-bottom:16px;
}

.field label{
  display:block;
  font-weight:600;
  margin-bottom:4px;
}

.field input,
.field select{
  width:100%;
  padding:8px 10px;
}

.actions{
  display:flex;
  gap:8px;
}

.muted{
  color:#6b7280;
  font-size:12px;
}

</style>

  <form method="post" action="<?= htmlspecialchars($base) ?>/register" novalidate>

    <!-- Email -->
    <div class="field">
      <label for="email">Email corporativo *</label>
      <input
        type="email"
        id="email"
        name="email"
        required
        autocomplete="off"
        value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
      >
      <small class="muted">
        O email precisa estar previamente autorizado para cadastro.
      </small>
    </div>

    <!-- Fiscal Petrobras -->
    <div class="field">
      <label for="inspector_id">Fiscal Petrobras *</label>
      <select id="inspector_id" name="inspector_id" required>
        <option value="">Selecione o fiscal</option>
        <?php foreach ($inspectors as $i): ?>
          <option
            value="<?= $i['id'] ?>"
            <?= (($old['inspector_id'] ?? '') == $i['id']) ? 'selected' : '' ?>
          >
            <?= htmlspecialchars($i['petrobras_inspector'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Localidade -->
    <div class="field">
      <label for="location_id">Localidade *</label>
      <select id="location_id" name="location_id" required>
        <option value="">Selecione a localidade</option>
        <?php foreach ($locations as $l): ?>
          <option
            value="<?= $l['id'] ?>"
            <?= (($old['location_id'] ?? '') == $l['id']) ? 'selected' : '' ?>
          >
            <?= htmlspecialchars($l['location'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Senha -->
    <div class="field">
      <label for="password">Senha *</label>
      <input
        type="password"
        id="password"
        name="password"
        required
        minlength="6"
        maxlength="15"
        pattern="^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{6,15}$"
      >
      <small class="muted">
        A senha deve conter de 6 a 15 caracteres, com letras e números.
      </small>
    </div>

    <!-- Confirmar Senha -->
    <div class="field">
      <label for="password_confirm">Confirmar Senha *</label>
      <input
        type="password"
        id="password_confirm"
        name="password_confirm"
        required
        minlength="6"
        maxlength="15"
      >
    </div>

    <!-- Ações -->
    <div class="actions" style="justify-content:flex-end;margin-top:16px">
      <a href="<?= htmlspecialchars($base) ?>/login" class="btn btn-light">
        Voltar
      </a>
      <button type="submit" class="btn">
        Cadastrar
      </button>
    </div>

  </form>
</div>