<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title ?? 'Formulário de Chamados') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <main class="container py-3">
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php include __DIR__ . '/' . $view . '.php'; ?>
  </main>
  <script src="/assets/app.js"></script>
</body>
</html>