<?php
$title = $title ?? 'Sistema de Auditoria';
$view  = $view  ?? null;

$base  = $base ?? '';
$user  = $_SESSION['user'] ?? null;
?>

<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="<?= $base ?>/assets/style.css">

  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
    }

    /* MENU FIXO */
    .top-menu {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 56px;
      background: #111;
      color: #fff;
      display: flex;
      align-items: center;
      padding: 0 16px;
      z-index: 1000;
    }

    .menu-left {
      display: flex;
      gap: 16px;
    }

    .menu-left a {
      color: #fff;
      text-decoration: none;
      font-weight: 600;
    }

    .menu-right {
      margin-left: auto;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .logout-btn {
      background: none;
      border: 1px solid #fff;
      color: #fff;
      padding: 4px 10px;
      cursor: pointer;
      border-radius: 4px;
    }

    main {
      padding-top: 72px;
      max-width: 1100px;
      margin: auto;
    }
  </style>
</head>

<body>

<?php if ($user): ?>
<div class="top-menu">

  <div class="menu-left">
    <a href="<?= $base ?>/">Formulário</a>
    <a href="<?= $base ?>/apkia">APKIA</a>
    <a href="<?= $base ?>/stats/noncompliance">Estatísticas</a>

    <?php if (!empty($user['is_admin'])): ?>
      <a href="<?= $base ?>/admin">Admin</a>
    <?php endif; ?>
  </div>

  <div class="menu-right">
    <span><?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>

    <form method="post" action="<?= $base ?>/logout" style="margin:0;">
      <button type="submit" class="logout-btn">Sair</button>
    </form>
  </div>

</div>
<?php endif; ?>

<main>

<?php
$file = __DIR__ . '/' . $view . '.php';

if (!empty($view) && is_file($file)) {
    include $file;
}/* else {
    echo "<h2>View não encontrada: {$view}</h2>";
}*/
?>

</main>

<script src="<?= $base ?>/assets/scripts.js"></script>

</body>
</html>
