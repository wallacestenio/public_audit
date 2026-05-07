<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Exportação de Cenário</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body{ font-family:Arial, sans-serif; padding:24px }
h1,h2{ margin-top:32px }
.chart{ width:480px; height:300px; margin-bottom:16px }
table{ border-collapse:collapse; width:480px; margin-bottom:40px }
td,th{ border:1px solid #ccc; padding:8px }
th{ background:#f5f5f5 }
</style>
</head>
<body>

<h1>Relatório de Não Conformidades</h1>
<p>
  <strong>Mês:</strong> <?= htmlspecialchars($month ?? 'Todos') ?><br>
  <strong>Mesa:</strong> <?= htmlspecialchars($resolverGroup ?? 'Todas') ?>
</p>

<hr>

<h2>Resumo do Cenário</h2>

<ul>
  <li><strong>Total de chamados auditados:</strong> <?= (int)$scenarioTotals['total'] ?></li>
  <li><strong>Não conformidades:</strong> <?= (int)$scenarioTotals['noncompliant'] ?></li>
  <li><strong>Em conformidade:</strong> <?= (int)$scenarioTotals['compliant'] ?></li>
</ul>

<hr>


<?php foreach ($statsByResolver as $resolver => $stats): ?>

<h2><?= htmlspecialchars($resolver) ?></h2>

<div class="chart">
  <canvas id="c<?= md5($resolver) ?>"></canvas>
</div>

<table>
  <tr><th>Descrição</th><th>Qtd.</th></tr>
  <?php foreach ($stats as $label => $count): ?>
    <tr>
      <td><?= htmlspecialchars($label) ?></td>
      <td><?= (int)$count ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<script>
new Chart(
  document.getElementById('c<?= md5($resolver) ?>'),
  {
    type: 'pie',
    data: {
      labels: <?= json_encode(array_keys($stats)) ?>,
      datasets: [{
        data: <?= json_encode(array_values($stats)) ?>,
        backgroundColor: ['#dc2626','#2563eb','#16a34a','#f97316']
      }]
    }
  }
);
</script>

<?php endforeach; ?>

</body>
</html>