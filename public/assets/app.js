
// ===== utilitários =====
function pad2(n){ return String(n).padStart(2,'0'); }
function currentYear(){ return new Date().getFullYear(); }
function stripAccents(s){ return s.normalize('NFD').replace(/[\u0300-\u036f]/g,''); }

// Palavras de dias da semana (para bloquear)
const WEEKDAYS = ['segunda','terca','terça','quarta','quinta','sexta','sabado','sábado','domingo'];

// Converte diversas entradas para 'YYYY-MM'
function normalizeToYYYYMM(input, preferYear) {
  if (!input) return null;
  let s = String(input).trim().toLowerCase();
  s = stripAccents(s);

  // se contiver dia da semana, rejeita
  for (const d of WEEKDAYS) {
    if (s.includes(d)) return null;
  }

  const map = {
    'jan':'01','janeiro':'01',
    'fev':'02','fevereiro':'02',
    'mar':'03','marco':'03',
    'abr':'04','abril':'04',
    'mai':'05','maio':'05',
    'jun':'06','junho':'06',
    'jul':'07','julho':'07',
    'ago':'08','agosto':'08',
    'set':'09','setembro':'09',
    'out':'10','outubro':'10',
    'nov':'11','novembro':'11',
    'dez':'12','dezembro':'12'
  };

  // yyyy-mm
  let m = s.match(/^(\d{4})-(\d{1,2})$/);
  if (m) {
    const yyyy = m[1], mm = pad2(m[2]);
    if (+mm>=1 && +mm<=12) return `${yyyy}-${mm}`;
  }

  // mm/yyyy
  m = s.match(/^(\d{1,2})\/(\d{4})$/);
  if (m) {
    const mm = pad2(m[1]), yyyy = m[2];
    if (+mm>=1 && +mm<=12) return `${yyyy}-${mm}`;
  }

  // "fev 2026" | "fevereiro 2026" | "fev-2026"
  m = s.match(/^([a-z]+)[\s\-\/]?(\d{4})$/);
  if (m) {
    const nome = m[1], yyyy = m[2], mm = map[nome];
    if (mm) return `${yyyy}-${mm}`;
  }

  // apenas nome/abreviação -> usa ano preferido (ou atual)
  if (map[s]) {
    const yyyy = Number.isFinite(preferYear) ? preferYear : currentYear();
    return `${yyyy}-${map[s]}`;
  }

  // "02/26" -> 2026-02 (heurística)
  m = s.match(/^(\d{1,2})\/(\d{2})$/);
  if (m) {
    const mm = pad2(m[1]);
    const yy = +m[2];
    const yyyy = yy >= 70 ? (1900 + yy) : (2000 + yy);
    if (+mm>=1 && +mm<=12) return `${yyyy}-${mm}`;
  }

  return null;
}

function setupAuditMonthAutocomplete() {
  const hidden = document.getElementById('audit_month');
  const input  = document.getElementById('audit_month_label');
  const yearEl = document.getElementById('audit_year');
  if (!hidden || !input) return;

  const preferYear = () => {
    const v = yearEl ? parseInt(yearEl.value, 10) : NaN;
    return Number.isFinite(v) ? v : currentYear();
  };

  // sincroniza valores existentes
  if (input.value && !hidden.value) {
    const norm = normalizeToYYYYMM(input.value, preferYear());
    if (norm) hidden.value = norm;
  } else if (hidden.value && !input.value) {
    input.value = hidden.value;
  }

  input.addEventListener('input', () => {
    const norm = normalizeToYYYYMM(input.value, preferYear());
    if (norm) hidden.value = norm;
  });

  input.addEventListener('blur', () => {
    const norm = normalizeToYYYYMM(input.value, preferYear());
    if (norm) {
      input.value  = norm;   // mostra yyyy-mm
      hidden.value = norm;
    } else {
      hidden.value = '';     // inválido
    }
  });

  if (yearEl) {
    yearEl.addEventListener('change', () => {
      const norm = normalizeToYYYYMM(input.value, preferYear());
      if (norm) {
        input.value  = norm;
        hidden.value = norm;
      }
    });
  }

  // Validação final no submit do form
  const form = input.closest('form');
  if (form) {
    form.addEventListener('submit', (e) => {
      const norm = normalizeToYYYYMM(input.value, preferYear());
      if (!norm) {
        e.preventDefault();
        alert('Informe o Mês da Auditoria válido (ex.: 2026-02, 02/2026, Fevereiro 2026).');
        input.focus();
        return;
      }
      hidden.value = norm;
      input.value  = norm; // uniformiza
    });
  }
}

// Garanta que rode no carregamento
document.addEventListener('DOMContentLoaded', setupAuditMonthAutocomplete);



async function fetchOptions(resource, q) {
  const res = await fetch(`/api/catalog?resource=${encodeURIComponent(resource)}&q=${encodeURIComponent(q)}`);
  return res.json();
}

// normaliza recursos de catálogo (ajuste aqui se seus endpoints diferirem)
function resourceFor(base) {
  const map = {
    ticket_type: 'ticket-types',
    kyndryl_auditor: 'kyndryl-auditors',
    petrobras_inspector: 'petrobras-inspectors',
    audited_supplier: 'audited-suppliers',
    location: 'locations',
    priority: 'priorities',
    category: 'categories',
    resolver_group: 'resolver-groups',
    'noncompliance-reasons': 'noncompliance-reasons'
  };
  return map[base] || `${base}s`;
}

function wireAutocomplete(group) {
  const hidden = group.querySelector('input[type=hidden]');     // nome = coluna (ex.: ticket_type)
  const input  = group.querySelector('input.ac');               // visível: nome = ticket_type_label
  const box    = group.querySelector('.ac-box');
  const base   = hidden.name;
  const resource = resourceFor(base);

  input.addEventListener('input', async (e) => {
    const q = e.target.value.trim();
    if (!q) { box.innerHTML=''; return; }
    const data = await fetchOptions(resource, q);
    box.innerHTML = '';
    data.forEach(row => {
      const btn = document.createElement('button');
      btn.type='button';
      btn.className='list-group-item list-group-item-action';
      btn.textContent = row.label;
      btn.onclick = () => {
        input.value  = row.label;   // mostra ao usuário
        hidden.value = row.label;   // valor enviado ao servidor
        box.innerHTML = '';
      };
      box.appendChild(btn);
    });
  });

  // Se usuário só digitou e saiu, replicar para o hidden
  input.addEventListener('blur', () => {
    const label = input.value.trim();
    if (label && !hidden.value) hidden.value = label;
  });

  document.addEventListener('click', (e)=>{ if (!group.contains(e.target)) box.innerHTML=''; });
}

// aplica em todos os grupos .ac-group
document.querySelectorAll('.ac-group').forEach(wireAutocomplete);

// Multi justificativas em uma string separada por ';'
(function(){
  const input  = document.querySelector('.ac-multi');
  if (!input) return;
  const chips  = document.getElementById('nc_chips');
  const hidden = document.getElementById('nc_ids');
  const selected = new Set((hidden.value||'').split(/[;,]/).map(s=>s.trim()).filter(Boolean));

  function render() {
    chips.innerHTML='';
    selected.forEach(label=>{
      const chip=document.createElement('span');
      chip.className='badge text-bg-secondary me-2';
      chip.textContent=label;
      chip.title='Clique para remover';
      chip.style.cursor='pointer';
      chip.onclick=()=>{ selected.delete(label); hidden.value=[...selected].join(';'); render(); };
      chips.appendChild(chip);
    });
  }
  render();

  input.addEventListener('keydown', (e)=>{
    if (e.key==='Enter') {
      e.preventDefault();
      const q = input.value.trim();
      if (!q) return;
      selected.add(q);
      hidden.value = [...selected].join(';');
      input.value = '';
      render();
    }
  });
})();

// Mostrar bloco de NC quando Não conforme
const sel = document.getElementById('is_compliant');
function toggleNC(){ document.getElementById('nc_block').style.display = (sel && sel.value === '0') ? 'block' : 'none'; }
if (sel) { sel.addEventListener('change', toggleNC); toggleNC(); }