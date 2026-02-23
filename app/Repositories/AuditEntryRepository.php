<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditEntry;

final class AuditEntryRepository
{
    public function __construct(
        private AuditEntry $model
    ) {}

    /** Cria a entrada */
    public function create(array $data, array $reasonIds = []): int
    {
        return $this->model->insertWithReasons($data, $reasonIds);
    }

    /** Checa duplicidade por ticket_number */
    public function existsByTicketNumber(string $ticketNumber): bool
    {
        if ($ticketNumber === '') return false;
        $pdo  = $this->model->getPdo();
        $stmt = $pdo->prepare('SELECT 1 FROM audit_entries WHERE ticket_number = :tk LIMIT 1');
        $stmt->execute([':tk' => $ticketNumber]);
        return (bool)$stmt->fetchColumn();
    }

    /** Expor PDO para o service (buscar labels) */
    public function rawPdo(): \PDO
    {
        return $this->model->getPdo();
    }

    /**
     * Export CSV – colunas e ordem exatas
     * Filtro opcional por audit_month (YYYY-MM)
     */
    public function exportRows(array $filters = []): array
    {
        $cols = [
            'ticket_number',
            'ticket_type',
            'kyndryl_auditor',
            'petrobras_inspector',
            'audited_supplier',
            'location',
            'audit_month',
            'priority',
            'requester_name',
            'category',
            'resolver_group',
            'sla_met',
            'is_compliant',
            'noncompliance_reasons',
        ];

        $sql    = 'SELECT ' . implode(',', $cols) . ' FROM audit_entries';
        $where  = [];
        $params = [];

        if (!empty($filters['audit_month'])) {
            $where[] = 'audit_month = :audit_month';
            $params[':audit_month'] = (string)$filters['audit_month'];
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY rowid ASC';

        $pdo  = $this->model->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $normalized = [];
        foreach ($rows as $r) {
            $line = [];
            foreach ($cols as $c) {
                $v = $r[$c] ?? '';
                if ($v === null) $v = '';
                $line[$c] = (string)$v;
            }
            $normalized[] = $line;
        }

        return $normalized;
    }
}