<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditEntry;

final class AuditEntryRepository
{
    public function __construct(
        private AuditEntry $model
    ) {}

    /**
     * Cria a entrada e vincula as razões (IDs) na ponte.
     *
     * @param array $data
     * @param int[] $reasonIds
     * @return int
     */
    public function create(array $data, array $reasonIds = []): int
    {
        return $this->model->insertWithReasons($data, $reasonIds);
    }

    /** Verifica duplicidade pelo número do ticket (true = já existe) */
    public function existsByTicketNumber(string $ticketNumber): bool
    {
        if ($ticketNumber === '' || !method_exists($this->model, 'getPdo')) return false;
        $pdo  = $this->model->getPdo();
        $stmt = $pdo->prepare('SELECT 1 FROM audit_entries WHERE ticket_number = :tk LIMIT 1');
        $stmt->execute([':tk' => $ticketNumber]);
        return (bool)$stmt->fetchColumn();
    }

    public function rawPdo(): \PDO
    {
        return $this->model->getPdo();
    }

    /**
     * Retorna linhas para export CSV, nas colunas e ordem exatas solicitadas.
     * Filtro opcional por audit_month (YYYY-MM) via $filters['audit_month'].
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

        if (!method_exists($this->model, 'getPdo')) {
            throw new \RuntimeException('Model não expõe getPdo(). Adicione getPdo(): \PDO no Model.');
        }
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