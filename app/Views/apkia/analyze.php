<?php
/**
 * View: APKIA — Assistente de Análise
 * Variáveis esperadas:
 * - $title (string)
 * - $base  (string)
 */

$title = $title ?? 'APKIA — Assistente de Análise';
$base  = $base  ?? '';
?>

<div class="container">

  <div class="card">

    <!-- Cabeçalho -->
    <header style="margin-bottom:16px">
      <h2><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="muted">
        Cole trechos do chamado (ServiceNow) para análise automática.
        O resultado é apenas um apoio ao auditor.
      </p>
    </header>

    <!-- Área de entrada -->
    <section class="apkia-input">

      <label for="apkia_text">
        Texto do chamado
      </label>

      <textarea
        id="apkia_text"
        rows="8"
        placeholder="Cole aqui histórico, notas de trabalho ou descrição do chamado..."
      ></textarea>

      <div class="actions" style="justify-content:flex-end; margin-top:12px">
        <button
          id="apkia_analyze_btn"
          class="btn"
          type="button"
          disabled
        >
          Analisar com APKIA
        </button>
      </div>

    </section>

    <!-- Resultado -->
    <section
      id="apkia_result"
      style="display:none; margin-top:24px"
      aria-live="polite"
    >

      <hr style="margin:24px 0">

      <h3>Resultado da Análise</h3>

      <div
        id="apkia_result_content"
        class="apkia-result-box"
      ></div>

      <!-- Próximo passo -->
      <div
        id="apkia_next_step"
        style="display:none; margin-top:20px"
      >

        <h4>O que deseja fazer agora?</h4>

        <div class="actions" style="justify-content:flex-start">
          <button
            id="go_audit_form"
            class="btn"
            type="button"
          >
            Auditoria de Chamados
          </button>

          <button
            id="go_estoque_form"
            class="btn btn-light"
            type="button"
          >
            Auditoria de Estoque
          </button>
        </div>

      </div>

    </section>

  </div>

  <!-- Resultado da análise APKIA -->
<div
  id="apkia-result"
  class="card"
  style="display:none; margin-top:16px;"
>
  <h4>Resultado da Análise (Sugestão do APKIA)</h4>

  <ul id="apkia-result-list" class="muted"></ul>

  <div class="actions" style="justify-content:flex-end; margin-top:12px">
    <button
      type="button"
      id="btn-go-audit"
      class="btn"
    >
      Ir para Auditoria
    </button>
  </div>
</div>

</div>
<style>
.apkia-result-box{
  background:#ffffff;
  border:1px solid #e3e6ea;
  border-radius:8px;
  padding:14px 16px;
  margin-top:10px;
}

.apkia-result-box ul{
  padding-left:18px;
  margin:8px 0;
}

.apkia-result-box li{
  margin:6px 0;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const textArea   = document.getElementById('apkia_text');
  const analyzeBtn = document.getElementById('apkia_analyze_btn');
  const resultBox  = document.getElementById('apkia_result');
  const resultCnt  = document.getElementById('apkia_result_content');
  const nextStep   = document.getElementById('apkia_next_step');

  const goAudit    = document.getElementById('go_audit_form');
  const goEstoque  = document.getElementById('go_estoque_form');

  if (!textArea || !analyzeBtn) return;

  // Habilita botão
  textArea.addEventListener('input', () => {
    analyzeBtn.disabled = textArea.value.trim().length < 30;
  });

  // Clique em analisar
  analyzeBtn.addEventListener('click', async () => {
    analyzeBtn.disabled = true;
    nextStep.style.display = 'none';
    resultBox.style.display = 'block';
    resultCnt.innerHTML = '<p class="muted">Analisando…</p>';

    try {
      const res = await fetch('<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/apkia/analyze', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          text: textArea.value
        })
      });

      if (!res.ok) {
        throw new Error('Erro ' + res.status);
      }

      const data = await res.json();

      renderResult(data);
      nextStep.style.display = 'block';

      // Guarda resultado para transição
      window.__APKIA_RESULT = data;

    } catch (e) {
      resultCnt.innerHTML =
        '<p class="error">Erro ao comunicar com o APKIA.</p>';
    } finally {
      analyzeBtn.disabled = false;
    }
  });

  // Navegação pós-análise
  goAudit.addEventListener('click', () => redirectTo('audit'));
  goEstoque.addEventListener('click', () => redirectTo('estoque'));

  function redirectTo(type) {
    if (!window.__APKIA_RESULT) return;

    const payload = encodeURIComponent(
      JSON.stringify(window.__APKIA_RESULT)
    );

    window.location.href =
      '<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/' +
      type +
      '/form?from=apkia&data=' + payload;
      
  }

  function renderResult(data) {
    let html = '';

    if (data.summary?.length) {
      html += '<ul>';
      data.summary.forEach(p => {
        html += `<li>${p}</li>`;
      });
      html += '</ul>';
    }

    if (data.ticket_number) {
      html += `<p><strong>Ticket:</strong> ${data.ticket_number}</p>`;
    }

    if (data.resolver_group) {
      html += `<p><strong>Mesa:</strong> ${data.resolver_group}</p>`;
    }

    resultCnt.innerHTML = html || '<p>Nenhum dado identificado.</p>';
  }
});
</script>