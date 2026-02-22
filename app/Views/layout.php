<?php
declare(strict_types=1);

/**
 * Layout base das páginas.
 *
 * Espera variáveis:
 *  - string $title
 *  - string $content (HTML da página)
 *
 * Este layout carrega:
 *  - /assets/style.css
 *  - /assets/scripts.js (defer)
 */

$title  = $title  ?? 'Aplicação';
$content = $content ?? '';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/assets/style.css" rel="stylesheet">
</head>
<body>
  <?= $content ?>

  <!-- Scripts globais deferidos (não bloqueiam renderização) -->
  <script src="/assets/scripts.js" defer></script>
</body>
</html>