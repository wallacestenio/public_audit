<?php
$title = $title ?? 'Estatísticas';
$labels = array_keys($stats);
$values = array_values($stats);
?>

<style>

.table-wrap{
  margin-top:24px;
  display:flex;
  justify-content:center;   /* centraliza horizontalmente */
}

/* ===== Tabela de Não Conformidades ===== */

.nc-table{
  width:100%;
  max-width:900px;          /* largura máxima centralizada */
  border-collapse:collapse;
  table-layout:fixed;
  font-size:14px;
  background:#fff;
  border:1px solid #d1d5db; /* borda externa clara */
}

/* Cabeçalho */
.nc-table thead th{
  text-align:left;
  padding:12px 10px;
  background:#f1f5f9;
  border-bottom:2px solid #cbd5e1;
  font-weight:600;
  color:#0f172a;
}

/* Células */
.nc-table tbody td{
  padding:10px;
  vertical-align:top;
  color:#111827;
  border-bottom:1px solid #e5e7eb;  /* linha horizontal */
}

/* ✅ Linha vertical separando as colunas */
.nc-table th + th,
.nc-table td + td{
  border-left:2px solid #e5e7eb;
}

/* Coluna da não conformidade */
.nc-table .col-label{
  width:80%;
  word-wrap:break-word;
  white-space:normal;
}

/* Coluna da quantidade */
.nc-table .col-count{
  width:20%;
  text-align:center;
  font-weight:700;
  color:#2563eb;
}

/* Zebra para leitura */
.nc-table tbody tr:nth-child(even){
  background:#fafafa;
}

/* Hover leve */
.nc-table tbody tr:hover{
  background:#f8fafc;
}

.chart-wrap{
  width:100%;
  max-width:900px;
  margin:0 auto 32px;
  height:420px;               /* desktop */
}

@media (max-width: 1024px){
  .chart-wrap{ height:360px; }
}

@media (max-width: 768px){
  .chart-wrap{ height:320px; }
}

@media (max-width: 480px){
  .chart-wrap{ height:260px; }
}

</style>

<div class="card">
  <h2><?= htmlspecialchars($title) ?></h2>

  <!-- 🔍 Filtro por mês -->
  <form method="get" style="margin-bottom:16px">
    <label for="month">Mês da Auditoria:</label>
    <select name="month" id="month" onchange="this.form.submit()">
      <option value="">Todos os meses</option>
      <?php foreach ($months as $m): ?>
        <option value="<?= $m ?>" <?= $m === $selectedMonth ? 'selected' : '' ?>>
          <?= $m ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <!-- 📊 Gráfico -->
  
<div class="chart-wrap">
  <canvas id="ncChart"></canvas>
</div>


  <!-- 📋 Tabela -->
<div class="table-wrap">
  <table class="nc-table">
    <thead>
      <tr>
        <th class="col-label">Não Conformidade</th>
        <th class="col-count">Quantidade</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($stats as $label => $count): ?>
        <tr>
          <td class="col-label">
            <?= htmlspecialchars($label) ?>
          </td>
          <td class="col-count">
            <?= $count ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('ncChart');

const chart = new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
      label: 'Quantidade',
      data: <?= json_encode($values) ?>,
      backgroundColor: '#dc2626',
      borderRadius: 6,
      maxBarThickness: 28   // ✅ barras mais largas
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,   // ✅ usa o container
    indexAxis: 'y',               // ✅ HORIZONTAL (MUITO IMPORTANTE)

    plugins: {
      legend: { display: false }
    },

    scales: {
      x: {
        beginAtZero: true,
        ticks: { precision: 0 }
      },
      y: {
        ticks: {
          autoSkip: false,        // ✅ não esconde rótulos
          font: { size: 12 }
        }
      }
    },

    // ✅ animação simples, estável e SEM LOOP
    animation: {
      duration: 1200,
      easing: 'easeOutCubic',
      onComplete() {
        chart.options.animation = false; // anima só uma vez
      }
    }
  }
});
</script>

