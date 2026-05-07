<?php if (!empty($_SESSION['form_errors'])): ?>
    <div class="alert error">
        <ul>
            <?php foreach ($_SESSION['form_errors'] as $err): ?>
                <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php unset($_SESSION['form_errors']); ?>
<?php endif; ?>

<style>
/* ===== Estilo exclusivo: Plano de Execução (Governança IA) ===== */

.pe-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 24px 32px;
}

.pe-container h2 {
    margin-bottom: 24px;
}

.pe-container h2 small {
    font-weight: normal;
    font-size: 0.6em;
    color: #555;
}

.pe-section {
    margin-bottom: 20px;
}

.pe-field {
    margin-bottom: 12px;
}

.pe-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 4px;
}

.pe-field input,
.pe-field select {
    min-width: 260px;
    padding: 6px 8px;
}

.pe-highlight {
    background: #f8f9fa;
    border: 1px solid #ccc;
    padding: 16px;
}

.pe-highlight h3 {
    margin-top: 0;
}

.pe-description {
    font-size: 0.95em;
    color: #444;
    margin-bottom: 10px;
}

.pe-highlight textarea {
    width: 100%;
    min-height: 200px;
    font-family: Consolas, monospace;
    font-size: 0.95em;
    padding: 10px;
}

.pe-warning {
    margin-top: 12px;
    padding: 12px 14px;
    background: #fff6e5;
    border-left: 4px solid #e6a700;
    font-size: 0.9em;
}

.pe-warning ul {
    margin: 8px 0 0 18px;
}

.pe-actions {
    margin-top: 20px;
}

.pe-actions button {
    padding: 8px 14px;
    font-weight: 600;
}
</style>

<div class="pe-container">


<form method="POST"
      action="/execution-plans/store"
      enctype="multipart/form-data">


    <h2>
        Novo Plano de Execução
        <small>(Governança da Análise Automatizada)</small>
    </h2>

    <div class="pe-section">

        <div class="pe-field">
            <label>Nome do Plano *</label>
            <input type="text" name="name" required>
        </div>

        <div class="pe-field">
            <label>Versão *</label>
            <input type="text" name="version" placeholder="Ex: 2026.1" required>
        </div>

        <div class="pe-field">
            <label>Tipo de Auditoria *</label>
            <select name="audit_type" required>
                <option value="estoque">Auditoria de Estoque</option>
                <option value="chamados">Auditoria de Chamados</option>
                <option value="ambos">Ambos</option>
            </select>
        </div>

    </div>

    <hr>

    <hr>

    <div class="pe-section pe-highlight">

    <h3>Texto Normativo do Plano de Execução *</h3>

    <p class="pe-description">
        Defina aqui as regras explícitas e obrigatórias que orientam
        a análise automatizada. Este texto é a autoridade decisória
        do APKIA e não pode ser substituído pelo PDF.
    </p>

    <textarea
        name="normative_summary"
        id="normative_summary"
        required
        placeholder="Descreva aqui as regras normativas, critérios objetivos e diretrizes formais da auditoria..."
    ><?= htmlspecialchars($_POST['normative_summary'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

</div>

<div class="pe-section pe-highlight">

    <h3>Documento oficial do Plano de Execução (PDF)</h3>

    <p class="pe-description">
        Anexe o documento oficial do Plano de Execução contendo
        explicações detalhadas, tabelas, fluxos operacionais e telas
        do ServiceNow.
    </p>

    <div class="pe-warning">
        <strong>📎 Uso pelo APKIA</strong>
        <ul>
            <li>Este documento será utilizado pelo APKIA como
                <strong>contexto interpretativo</strong></li>
            <li>O texto normativo definido acima permanece como
                <strong>autoridade decisória</strong></li>
            <li>O PDF não substitui regras explícitas</li>
            <li>Alterações no PDF podem refinar as orientações geradas</li>
        </ul>
    </div>

    <div class="pe-field" style="margin-top:12px;">
        <label>Anexar PDF do Plano de Execução</label>
        <input type="file"
               name="execution_plan_pdf"
               accept="application/pdf">
        <small>
            Somente arquivos PDF. Recomenda-se incluir tabelas,
            exemplos visuais e descrições completas do processo.
        </small>
    </div>

</div>


    <div class="pe-actions">
        <button type="submit">Salvar como rascunho</button>
        <a href="/execution-plans">Cancelar</a>
    </div>

</form>

</div>
