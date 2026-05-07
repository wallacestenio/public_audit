<section class="assistant-card" aria-labelledby="assistant-title">
  <header>
    <h3 id="assistant-title">Assistente de Análise</h3>
    <p class="assistant-subtitle">
      Analisa trechos do chamado segundo diretrizes internas.
    </p>
  </header>

  <textarea
    id="assistant-text"
    rows="6"
    placeholder="Cole aqui trechos do chamado para análise">
  </textarea>

  <div class="assistant-actions">
    <button id="assistant-analyze" disabled>
      Analisar
    </button>
  </div>

  <div id="assistant-result" hidden>
    <h4>Resultado da Análise</h4>
    <div id="assistant-result-content"></div>
  </div>
</section>