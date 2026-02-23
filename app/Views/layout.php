<?php
/**
 * Layout base.
 * Detecta automaticamente o base path (subpasta) e expõe em JS: window.APP_BASE
 * Espera: $title, $view, $base.
 */

$base = isset($base) ? (string)$base : (function () {
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return ($dir === '/' || $dir === '.') ? '' : $dir;
})();

$title = $title ?? 'Auditoria de Chamados';
$view  = $view  ?? null;
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <script>window.APP_BASE = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;</script>

  <link rel="stylesheet" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/style.css">
</head>
<body>
  <main class="container">
    <?php
      if (!empty($view) && is_file(__DIR__ . '/' . $view . '.php')) {
        include __DIR__ . '/' . $view . '.php';
      } else {
        echo '<div class="alert alert-warning">Nenhuma view definida ou arquivo não encontrado.</div>';
      }
    ?>
  </main>

  <script defer src="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/scripts.js"></script>
</body>
</html>