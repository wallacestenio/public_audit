<!-- <div class="alert alert-success">Chamado #<?= htmlspecialchars((string)($id ?? '')) ?> criado com sucesso.</div>
<a class="btn btn-outline-primary" href="/">Novo</a> -->

<div id="redir-box"
     style="max-width:780px;margin:20px auto;padding:16px;border:1px solid #10b981;background:#d1fae5;
            color:#065f46;border-radius:6px;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
            font-size:1.1rem;line-height:1.45">
  <strong>✔ Auditoria criada com sucesso!</strong><br>
 <!-- Você será redirecionado para a página principal em <strong><span id="count">3</span></strong> segundos... -->
  <div id="motivation" style="margin-top:10px;opacity:1;transition:opacity .28s ease">
    <em>Construindo excelência, um chamado por vez.</em>
  </div>
</div>

<script>
(function () {
  // ===== Configuração =====
  const path    = '/'; // <-- destino final (raiz). Troque para '/audit-entries' se quiser.
  const frases  = [
    'Construindo excelência, um chamado por vez.',
    'Qualidade é hábito. Consistência é resultado.',
    'Você fez acontecer. Bora para o próximo! 💪',
    'Pequenas entregas, grandes impactos.',
    'Cada registro é um passo a mais na melhoria contínua.'
  ];

  // ===== Alvos =====
  const origin   = window.location.origin;           // dinâmico (http(s)+host+porta)
  const destino  = origin + path;
  const elCount  = document.getElementById('count');
  const elMotiv  = document.getElementById('motivation');

  // ===== Semente diferente a cada sucesso (muda a frase “inicial” por chamado) =====
  const qs       = new URLSearchParams(window.location.search);
  const created  = qs.get('created') || '';          // id (quando você manda via ?created=)
  const ticket   = qs.get('ticket')  || '';          // ticket (quando você manda via ?ticket=)
  const digits   = (s) => (s || '').match(/\d+/g)?.join('') || '';
  const seedStr  = digits(created) || digits(ticket) || String(Date.now());
  const seedNum  = Number(seedStr) || 1;

  let idx        = (seedNum % frases.length + frases.length) % frases.length;
  let contador   = 10; // 3 → 2 → 1

  // ===== Funções de fade =====
  const setFrase = (texto) => { elMotiv.textContent = texto; };
  const fadeTo   = (texto) => {
    elMotiv.style.opacity = '0';
    // troca o texto após a transição começar
    setTimeout(() => { setFrase(texto); elMotiv.style.opacity = '4'; }, 180);
  };

  // Frase inicial baseada na semente
  setFrase(frases[idx]);

  // ===== Relógio 1s: troca a frase e atualiza contagem =====
  const timer = setInterval(() => {
    // contagem
    contador--;
    if (elCount) elCount.textContent = String(contador);

    // próxima frase (também muda a cada “tick”)
    idx = (idx + 1) % frases.length;
    fadeTo(frases[idx]);

    // fim → redireciona
    if (contador <= 0) {
      clearInterval(timer);
      window.location.assign(destino);
    }
  }, 1000);

  // Fail-safe: se algo travar, garante redirect em ~3,2s
  setTimeout(() => {
    try { clearInterval(timer); } catch (e) {}
    window.location.assign(destino);
  }, 4800);
})();
</script>

