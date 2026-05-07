<?php
// $session  -> dados da sessão
// $items    -> chamados da sessão
?>

<h1>Sessão de Auditoria</h1>

<p>
    <strong>ID da Sessão:</strong> <?= htmlspecialchars((string)$session['id']) ?><br>
    <strong>Mês:</strong> <?= htmlspecialchars((string)$session['audit_month']) ?><br>
    <strong>Status:</strong> <?= htmlspecialchars((string)$session['status']) ?>
</p>

<hr>

<h2>Importar chamados</h2>

<?php require __DIR__ . '/_import_form.php'; ?>

<hr>

<h2>Chamados da sessão</h2>

<p>
    Total: <?= count($items) ?> |
    Pendentes: <?= count(array_filter($items, fn($i) => $i['status'] === 'PENDING')) ?> |
    Analisados: <?= count(array_filter($items, fn($i) => $i['status'] === 'ANALYZED')) ?> |
    Removidos: <?= count(array_filter($items, fn($i) => $i['status'] === 'REMOVED')) ?>
</p>

<table border="1" cellpadding="6" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th>Ticket</th>
            <th>Origem</th>
            <th>Status</th>
            <th>Categoria SN</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr style="background-color: <?= $item['status'] === 'REMOVED' ? '#eee' : '#fff' ?>">
                <td><?= htmlspecialchars($item['ticket_number'] ?? '-') ?></td>
                <td><?= htmlspecialchars($item['import_source']) ?></td>
                <td><?= htmlspecialchars($item['status']) ?></td>
                <td><?= htmlspecialchars($item['sn_category'] ?? '-') ?></td>
                <td>
                    <?php if ($item['status'] === 'PENDING'): ?>
                        <form method="post" action="/audit-session/remove-item" style="display:inline">
                            <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                            <button type="submit" onclick="return confirm('Remover este chamado da sessão?')">
                                Remover
                            </button>
                        </form>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<hr>

<p>
    <a href="/audit-session/pending?session_id=<?= (int)$session['id'] ?>">
        Ver chamados pendentes (JSON)
    </a>
</p>
