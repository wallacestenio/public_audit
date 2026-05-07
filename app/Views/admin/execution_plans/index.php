<h2>
    Planos de Execução
    <small>(Governança da Análise Automatizada)</small>
</h2>

<div class="actions" style="margin-bottom:16px;">
    <a href="/execution-plans/create" class="btn btn-primary">
        + Novo Plano de Execução
    </a>
</div>

<?php if (empty($plans)): ?>
    <p class="hint">Nenhum Plano de Execução cadastrado.</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Versão</th>
                <th>Tipo</th>
                <th>Status</th>
                <th>PDF</th>
                <th>Ativado em</th>
                <th>Ações</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($plans as $plan): ?>
                <tr>
                    <td><?= htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8') ?></td>

                    <td><?= htmlspecialchars($plan['version'], ENT_QUOTES, 'UTF-8') ?></td>

                    <td><?= ucfirst($plan['audit_type']) ?></td>

                    <td>
                        <?php if ($plan['status'] === 'active'): ?>
                            <span class="badge success">Ativo</span>
                        <?php elseif ($plan['status'] === 'draft'): ?>
                            <span class="badge warning">Rascunho</span>
                        <?php else: ?>
                            <span class="badge muted">Arquivado</span>
                        <?php endif; ?>
                    </td>

                    <!-- ✅ INDICADOR VISUAL DO PDF (AQUI ENTRA O TRECHO QUE VOCÊ PERGUNTOU) -->
                    <td style="text-align:center;">
    <?php if (!empty($plan['pdf_path'])): ?>
        <?php
            $pdfName = basename($plan['pdf_path']);
        ?>
        <a href="/execution-plans/pdf?id=<?= (int)$plan['id'] ?>"
           title="Baixar PDF do Plano de Execução: <?= htmlspecialchars($pdfName, ENT_QUOTES, 'UTF-8') ?>">
            📄
        </a>
    <?php else: ?>
        <span title="Plano sem documento PDF anexado" style="opacity:0.4;">
            —
        </span>
    <?php endif; ?>
</td>

                    <td><?= $plan['activated_at'] ?: '-' ?></td>

                    <td>
                        <?php if ($plan['status'] === 'draft'): ?>
                            <form method="POST" action="/execution-plans/activate" style="display:inline;">
    <input type="hidden" name="id" value="<?= (int)$plan['id'] ?>">
    <button type="submit"
            onclick="return confirm('Ativar este Plano de Execução? Isso tornará este plano a referência normativa vigente.')">
        Ativar Plano
    </button>
</form>
                        <?php else: ?>
                            <span style="color:#777;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>