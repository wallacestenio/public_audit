<h1>Auditorias Salvas</h1>

<table border="1" cellpadding="6" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th>Ticket</th>
            <th>Mês</th>
            <th>Categoria</th>
            <th>Conformidade</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($entries as $e): ?>
            <tr>
                <td><?= htmlspecialchars($e['ticket_number']) ?></td>
                <td><?= htmlspecialchars($e['audit_month']) ?></td>
                <td><?= htmlspecialchars($e['category']) ?></td>
                <td><?= $e['is_compliant'] ? 'Conforme' : 'Não conforme' ?></td>
                <td>
                    <a href="/audit/view?id=<?= (int)$e['id'] ?>">Ver</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>