// ===========================
// APKIA – Erros e utilidades
// ===========================

function showApkiaError(message) {
  const el = document.getElementById('apkia-error');

  if (!el) {
    console.warn('Elemento #apkia-error não encontrado');
    return;
  }

  el.textContent = message;
  el.style.display = 'block';
}

// ===========================
// Validação do resultado APKIA
// ===========================

function validateApkiaResult(result) {
  if (!result || typeof result !== 'object') {
    return { ok: false, error: 'Resposta inválida do APKIA.' };
  }

  if (result.ticket_number !== null && typeof result.ticket_number !== 'string') {
    return { ok: false, error: 'Número do ticket inválido.' };
  }

  const validTypes = ['INCIDENTE', 'REQUISIÇÃO', 'TASK'];
  if (
    result.ticket_type !== null &&
    result.ticket_type !== undefined &&
    !validTypes.includes(result.ticket_type)
  ) {
    return { ok: false, error: 'Tipo de ticket inválido.' };
  }

  if (result.priority !== null && !Number.isInteger(result.priority)) {
    return { ok: false, error: 'Prioridade inválida.' };
  }

  if (result.sla_met !== null && typeof result.sla_met !== 'boolean') {
    return { ok: false, error: 'Valor inválido para SLA.' };
  }

  if (result.is_compliant !== null && typeof result.is_compliant !== 'boolean') {
    return { ok: false, error: 'Valor inválido para conformidade.' };
  }

  if (result.suggestions !== undefined && !Array.isArray(result.suggestions)) {
    return { ok: false, error: 'Formato inválido de sugestões.' };
  }

  return { ok: true };
}

// ===========================
// Renderização do resultado
// ===========================

function renderApkiaResult(result) {
  const container = document.getElementById('apkia-result');
  const list = document.getElementById('apkia-result-list');

  if (!container || !list) {
    console.warn('Container de resultado APKIA não encontrado');
    return;
  }

  list.innerHTML = '';

  if (Array.isArray(result.suggestions) && result.suggestions.length > 0) {
    result.suggestions.forEach(function (item) {
      const li = document.createElement('li');
      li.textContent = item;
      list.appendChild(li);
    });
  } else {
    const li = document.createElement('li');
    li.textContent =
      'Nenhuma inconsistência relevante foi identificada automaticamente.';
    list.appendChild(li);
  }

  container.style.display = 'block';
}

// ===========================
// Navegação para o formulário
// ===========================

function redirectToAuditForm(result) {
  const payload = encodeURIComponent(JSON.stringify(result));
  window.location.href = '/audit/form?from=apkia&data=' + payload;
}

// ===========================
// Tratamento do resultado
// ===========================

function handleApkiaResult(result) {
  const validation = validateApkiaResult(result);

  if (!validation.ok) {
    showApkiaError(validation.error);
    return;
  }

  renderApkiaResult(result);

  const btn = document.getElementById('btn-go-audit');
  if (!btn) {
    console.warn('Botão btn-go-audit não encontrado');
    return;
  }

  btn.onclick = function () {
    redirectToAuditForm(result);
  };
}

// ===========================
// Chamada do APKIA
// ===========================

fetch('/apkia/analyze', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ text: textoDoChamado })
})
  .then(r => r.json())
  .then(result => {
    handleApkiaResult(result);
  })
  .catch(err => {
    console.error(err);
    showApkiaError('Erro ao comunicar com o APKIA.');
  });