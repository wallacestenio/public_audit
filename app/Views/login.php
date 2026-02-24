<?php
$title = $title ?? 'Entrar';
$error = $error ?? null;
?>
<div class="card" style="max-width:420px">
  <h1 style="margin-top:0">Entrar</h1>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" action="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/login" novalidate>
    <div class="row">
      <div class="col">
        <label for="username">Usuário </label>
        <input id="username" name="username" required autocomplete="username"
               placeholder="Seu usuário">
      </div>
      <div class="col">
        <label for="password">Senha</label>
        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Sua senha">
      </div>
      <div class="col-full" style="margin-top:8px">
        <div class="actions" style="justify-content:flex-end">
          <button type="submit" class="btn">Entrar</button>
        </div>
      </div>
    </div>
  </form>

  <div class="muted" style="margin-top:8px">
    Dica: o usuário é seu <code>Nome no Formato Recebido</code>.
  </div>
</div>