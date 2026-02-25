<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditEntry;
use PDO;

final class AuditEntryRepository
{
    public function __construct(private AuditEntry $model) {}

    
    public function existsTicket(string $ticketNumber): bool
{
    $pdo = $this->rawPdo();
    $sql = "SELECT 1 FROM audit_entries WHERE ticket_number = :tk LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':tk' => $ticketNumber]);
    return (bool)$stmt->fetchColumn();
}
    public function rawPdo(): PDO { return $this->model->getPdo(); }

    /**
     * Cria um registro em audit_entries (tudo em uma única tabela).
     * Espera $data com as chaves abaixo (strings/int coerentes).
     */
    public function create(array $data, array $unused = []): int
{
    $pdo = $this->rawPdo();

    $sql = "INSERT INTO audit_entries
        (ticket_number, ticket_type, kyndryl_auditor, petrobras_inspector, audited_supplier, location,
         audit_month, priority, requester_name, category, resolver_group, sla_met, is_compliant,
         noncompliance_reason_ids, noncompliance_reasons, user_id)
        VALUES
        (:ticket_number, :ticket_type, :kyndryl_auditor, :petrobras_inspector, :audited_supplier, :location,
         :audit_month, :priority, :requester_name, :category, :resolver_group, :sla_met, :is_compliant,
         :nc_ids, :nc_labels, :user_id)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ticket_number'       => (string)$data['ticket_number'],
        ':ticket_type'         => (string)$data['ticket_type'],
        ':kyndryl_auditor'     => (string)$data['kyndryl_auditor'],
        ':petrobras_inspector' => (string)$data['petrobras_inspector'],
        ':audited_supplier'    => (string)$data['audited_supplier'],
        ':location'            => (string)$data['location'],
        ':audit_month'         => (string)$data['audit_month'],
        ':priority'            => (int)$data['priority'],
        ':requester_name'      => (string)$data['requester_name'],
        ':category'            => (string)$data['category'],
        ':resolver_group'      => (string)$data['resolver_group'],
        ':sla_met'             => (int)$data['sla_met'],
        ':is_compliant'        => (int)$data['is_compliant'],
        ':nc_ids'              => $data['noncompliance_reason_ids'] ?? null,
        ':nc_labels'           => $data['noncompliance_reasons'] ?? null,
        ':user_id'             => isset($data['user_id']) ? (int)$data['user_id'] : null, // << AQUI
    ]);

    $id = (int)$pdo->lastInsertId();
    if ($id === 0) {
        $ridStmt = $pdo->prepare('SELECT rowid FROM audit_entries WHERE ticket_number = :tk');
        $ridStmt->execute([':tk' => (string)$data['ticket_number']]);
        $id = (int)($ridStmt->fetchColumn() ?: 0);
    }
    return $id;
}

    /**
     * Exporta dados para CSV (ordem de colunas e filtro por mês).
     */
    public function exportRows(array $filters = []): array
{
    // Colunas exportadas (mantidas)
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

    // Constroi SELECT
    $sql = 'SELECT ' . implode(',', $cols) . ' FROM audit_entries';

    $where  = [];
    $params = [];

    // 🔐 Filtro OBRIGATÓRIO por user_id (apenas registros do usuário logado)
    $userId = isset($filters['user_id']) ? (int)$filters['user_id'] : 0;
    if ($userId > 0) {
        $where[] = 'user_id = :user_id';
        $params[':user_id'] = $userId;
    } else {
        // Se por algum motivo vier sem user_id, não retornamos nada
        // (proteção extra; na prática, o controller sempre envia)
        return [];
    }

    // 🔎 Filtro opcional por mês (mantido)
    if (!empty($filters['audit_month'])) {
        $where[] = 'audit_month = :audit_month';
        $params[':audit_month'] = (string)$filters['audit_month'];
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY rowid ASC';

    $pdo = $this->rawPdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Normaliza null->'' e mantém ordem das colunas
    $out = [];
    foreach ($rows as $r) {
        $line = [];
        foreach ($cols as $c) {
            $line[$c] = (string)($r[$c] ?? '');
        }
        $out[] = $line;
    }
    return $out;
    }
}