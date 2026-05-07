(() => {
  const form = document.getElementById("audit-form");
  if (!form) return;

  function qs(name) {
    return form.querySelector(`[name="${name}"]`);
  }

  function val(name) {
    return qs(name)?.value.trim();
  }

  function fail(msg, field) {
    alert(msg);
    field?.focus();
    throw new Error(msg);
  }

  // SEGMENTED BUTTONS
  document.querySelectorAll(".segmented").forEach(seg => {
    const hidden = qs(seg.dataset.segmented);

    seg.querySelectorAll("button").forEach(btn => {
      btn.addEventListener("click", () => {
        seg.querySelectorAll("button").forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        hidden.value = btn.dataset.value;

        // Conformidade → NC
        if (hidden.name === "is_compliant") {
          document.getElementById("nc_block")
            .classList.toggle("hidden", hidden.value !== "0");
        }
      });
    });
  });

  document.getElementById("btn-submit").addEventListener("click", () => {

    const required = [
      "ticket_number",
      "ticket_type",
      "kyndryl_auditor_id",
      "petrobras_inspector_id",
      "audited_supplier_id",
      "location_id",
      "audit_month",
      "priority",
      "sla_met",
      "is_compliant"
    ];

    required.forEach(name => {
      if (!val(name)) {
        fail(`Campo obrigatório não preenchido: ${name}`, qs(name));
      }
    });

    if (val("is_compliant") === "0" && !val("noncompliance_reason_ids")) {
      fail("Justificativas são obrigatórias quando o chamado não é conforme.");
    }

    form.submit();
  });
})();
