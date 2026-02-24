<?php
/**
 * Layout base.
 * Exige: $title, $view, $base
 */
$title = $title ?? 'Auditoria de Chamados';
$view  = $view  ?? null;

// base dinâmico (caso não venha do controller)
$base  = $base  ?? (function(){
    $s = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $d = rtrim(str_replace('\\','/',dirname($s)), '/');
    return ($d==='/'||$d==='.')?'':$d;
})();

// sessão corrente
$user = $_SESSION['user'] ?? null;

// caminho da requisição atual (para esconder a saudação em /audit-entries)
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$hideUserGreeting = ($reqPath === $base . '/audit-entries'); // 👈 Aqui a mágica
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>window.APP_BASE = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;</script>
  <link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/style.css">
  <style>
    .topbar{display:flex;gap:12px;align-items:center;justify-content:space-between;margin:0 auto 12px;max-width:1100px}
    .topbar a{color:#0f172a;text-decoration:none;font-weight:600}
    .topbar small{color:#64748b}
    .topbar form{margin:0}
  </style>
</head>
<body>

  <div class="topbar">
    <div><a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/"></a></div>

    <?php if (!$hideUserGreeting): // 👈 Esconde o bloco à direita somente em /audit-entries ?>
      <div>
        <?php if ($user): ?>
          <small>Olá, <strong><?= htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8') ?></strong></small>
          <?php if ((int)($user['user_type'] ?? 1) === 0): ?>
            &nbsp;|&nbsp;<a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/admin">Admin</a>
          <?php endif; ?>
          &nbsp;|&nbsp;
          <form method="post" action="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/logout" style="display:inline">
            <button type="submit" class="btn btn-light" style="background:#ddd;color:#111">Sair</button>
          </form>
        <?php else: ?>
          <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/login"></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <main class="container">
    <?php
      $file = __DIR__ . '/' . $view . '.php';
      if (!empty($view) && is_file($file)) {
        include $file;
      } else {
        echo '<div class="alert alert-warning">Nenhuma view definida ou arquivo não encontrado.</div>';
      }
    ?>
  </main>

  <script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/scripts.js"></script>
</body>
</html>