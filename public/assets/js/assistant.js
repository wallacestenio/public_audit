document.addEventListener('DOMContentLoaded', () => {
  const text     = document.getElementById('assistant-text');
  const btn      = document.getElementById('assistant-analyze');
  const result   = document.getElementById('assistant-result');
  const content  = document.getElementById('assistant-result-content');

  if (!text || !btn || !result || !content) return;

  text.addEventListener('input', () => {
    btn.disabled = text.value.trim().length < 20;
  });

  btn.addEventListener('click', async () => {
    btn.disabled = true;
    result.hidden = true;

    try {
      const res = await fetch('/assistant/analyze', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ text: text.value })
      });

      const data = await res.json();

      content.innerHTML = `
        <h5>${data.title}</h5>
        <ul>${data.points.map(p => `<li>${p}</li>`).join('')}</ul>
        <p><em>${data.note}</em></p>
      `;

    } catch {
      content.innerHTML = `<p class="error">Erro na análise.</p>`;
    } finally {
      result.hidden = false;
      btn.disabled = false;
    }
  });
});