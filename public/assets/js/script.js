document.addEventListener('DOMContentLoaded', () => {
  const bdgc   = document.getElementById('bdgc_quantity');
  const found  = document.getElementById('found_quantity');
  const disp   = document.getElementById('divergence_quantity_display');
  const hidden = document.getElementById('divergence_quantity');

  if (!bdgc || !found || !disp || !hidden) return;

  function toNumber(v) {
    if (v === '') return null;
    const n = Number(v);
    return Number.isNaN(n) ? null : n;
  }

  function calcular() {
    const a = toNumber(found.value);
    const b = toNumber(bdgc.value);

    if (a === null || b === null) {
      disp.value = '';
      hidden.value = '';
      return;
    }

    const diff = a - b;
    disp.value = diff;
    hidden.value = diff;
  }

  bdgc.addEventListener('input', calcular);
  found.addEventListener('input', calcular);
});