<?php
declare(strict_types=1);

/**
 * View: Estatísticas de Não Conformidades (por Mês e por Mesa)
 */

/* ✅ Segurança contra warnings */
$title                 = $title ?? 'Estatísticas de Não Conformidades';
$statsByResolver       = is_array($statsByResolver ?? null) ? $statsByResolver : [];
$months                = is_array($months ?? null) ? $months : [];
$resolverGroups        = is_array($resolverGroups ?? null) ? $resolverGroups : [];
$selectedMonth         = $selectedMonth ?? null;
$selectedResolverGroup = $selectedResolverGroup ?? null;
?>

<style>
.charts-grid{
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
  gap:24px;
  margin-top:24px;
}

.chart-card{
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:12px;
  padding:16px;
}

.chart-card h3{
  margin:0 0 12px;
  font-size:16px;
}

.chart-wrap{
  height:300px;
}

.nc-table{
  width:100%;
  margin-top:12px;
  border-collapse:collapse;
  table-layout:fixed;
  font-size:14px;
}

.nc-table th,
.nc-table td{
  padding:8px;
  border-bottom:1px solid #e5e7eb;
}

.nc-table th{
  background:#f1f5f9;
  text-align:left;
  font-weight:600;
}

.nc-table td:last-child,
.nc-table th:last-child{
  text-align:center;
}

.nc-table td:first-child{
  white-space:normal;
  word-wrap:break-word;
}
</style>

<!-- ✅ Chart.js SEMPRE ANTES -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="card">
  <h2><?= htmlspecialchars($title) ?></h2>

  <!-- ✅ FILTROS -->
  <form method="get" style="display:flex; gap:16px; margin-bottom:20px">
    <div>
      <label>Mês</label>
      <select name="month" onchange="this.form.submit()">
        <option value="">Todos</option>
        <?php foreach ($months as $m): ?>
          <option value="<?= htmlspecialchars($m) ?>"
            <?= $m === $selectedMonth ? 'selected' : '' ?>>
            <?= htmlspecialchars($m) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>


    <div>
      <label>Mesa</label>
      <select name="resolver_group" onchange="this.form.submit()">
        <option value="">Todas</option>
        <?php foreach ($resolverGroups as $rg): ?>
          <option value="<?= htmlspecialchars($rg) ?>"
            <?= $rg === $selectedResolverGroup ? 'selected' : '' ?>>
            <?= htmlspecialchars($rg) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <form method="get" action="<?= $this->base() ?>/export/scenario" style="margin-bottom:24px">
  <!-- mantém os filtros atuais -->
  <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth ?? '') ?>">
  <input type="hidden" name="resolver_group" value="<?= htmlspecialchars($selectedResolverGroup ?? '') ?>">

  <button type="submit">
    Exportar cenário (HTML)
  </button>
</form>

  <?php if (empty($statsByResolver)): ?>

  <p class="muted">Nenhum dado disponível para os filtros selecionados.</p>

<?php else: ?>

  <!-- ✅ RESUMO GERAL DO MÊS (ANTES DO GRID DE MESAS) -->
  <?php if (!empty($complianceSummary)): ?>

    <h3 style="margin-top:16px">
      Resumo Geral do Mês
      (<?= htmlspecialchars($selectedMonth ?? 'Todos') ?>)
    </h3>

    <div class="chart-wrap" style="max-width:480px; margin-bottom:32px">
      <canvas id="summaryChart"></canvas>
    </div>

    <script>
    new Chart(
      document.getElementById('summaryChart'),
      {
        type: 'doughnut',
        data: {
          labels: <?= json_encode($complianceSummary['labels'], JSON_UNESCAPED_UNICODE) ?>,
          datasets: [{
            data: <?= json_encode($complianceSummary['values']) ?>,
            backgroundColor: ['#16a34a','#dc2626'],
            hoverOffset: 10
          }]
        },
        options: {
          plugins: {
            legend: { position: 'bottom' }
          }
        }
      }
    );
    </script>

  <?php endif; ?>

  <!-- ✅ GRID DE GRÁFICOS + TABELAS -->
  <div class="charts-grid">


      <?php foreach ($statsByResolver as $resolver => $stats): ?>
        <?php if (empty($stats)) continue; ?>

        <div class="chart-card">
          <h3>Mesa: <?= htmlspecialchars($resolver) ?></h3>

          <!-- ✅ GRÁFICO DE PIZZA -->
          <div class="chart-wrap">
            <canvas id="chart-<?= md5($resolver) ?>"></canvas>
          </div>

          <!-- ✅ TABELA -->
          <table class="nc-table">
            <thead>
              <tr>
                <th>Descrição</th>
                <th>Qtd.</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($stats as $label => $count): ?>
                <tr>
                  <td><?= htmlspecialchars($label) ?></td>
                  <td><?= (int)$count ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- ✅ SCRIPT DO GRÁFICO -->
        <script>
        new Chart(
          document.getElementById('chart-<?= md5($resolver) ?>'),
          {
            type: 'pie',
            data: {
              labels: <?= json_encode(array_keys($stats), JSON_UNESCAPED_UNICODE) ?>,
              datasets: [{
                data: <?= json_encode(array_values($stats)) ?>,
                backgroundColor: [
                  '#dc2626','#2563eb','#16a34a','#f97316',
                  '#7c3aed','#0d9488','#ca8a04','#be185d'
                ],
                hoverOffset: 12
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  position: 'bottom',
                  labels: { boxWidth: 14 }
                },
                tooltip: {
                  callbacks: {
                    label(ctx){
                      return `${ctx.label}: ${ctx.parsed}`;
                    }
                  }
                }
              }
            }
          }
        );
        </script>

      <?php endforeach; ?>

    </div>

  <?php endif; ?>
</div>
