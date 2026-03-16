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

    // << agora vem sempre vazio (ou ajuste para null se a coluna permitir):
    ':requester_name'      => (string)($data['requester_name'] ?? ''),

    ':category'            => (string)$data['category'],
    ':resolver_group'      => (string)$data['resolver_group'],
    ':sla_met'             => (int)$data['sla_met'],
    ':is_compliant'        => (int)$data['is_compliant'],
    ':nc_ids'              => $data['noncompliance_reason_ids'] ?? null,
    ':nc_labels'           => $data['noncompliance_reasons'] ?? null,
    ':user_id'             => isset($data['user_id']) ? (int)$data['user_id'] : null,
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
    $sql = 'SELECT
              ticket_number,
              ticket_type,
              kyndryl_auditor,
              petrobras_inspector,
              audited_supplier,
              location,
              audit_month,
              priority,
              category,
              resolver_group,
              sla_met,
              is_compliant,
              noncompliance_reasons
            FROM audit_entries';

    $where  = [];
    $params = [];

    $userId = isset($filters['user_id']) ? (int)$filters['user_id'] : 0;
    if ($userId > 0) {
        $where[] = 'user_id = :user_id';
        $params[':user_id'] = $userId;
    } else {
        return [];
    }

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

    $out = [];
    foreach ($rows as $r) {
        // Conversões solicitadas:
        $sla    = ((string)($r['sla_met'] ?? '')) === '1' ? 'Sim' : 'Não';
        $pri    = 'Prioridade ' . (string)(int)($r['priority'] ?? 0);
        $isComp = ((string)($r['is_compliant'] ?? '')) === '1' ? 'Sim' : 'Não';

        // ORDEM EXATA do CSV:
        // Número Ticket, Tipo do Ticket, Auditor Kyndryl, Inspetor Petrobras,
        // Fornecedor Auditado, Localidade, Mês da Auditoria, SLA Atingido?,
        // Prioridade, Categoria, Mesa Solucionadora, Chamado Conforme?,
        // Justificativas de não conformidade

        // Converter audit_month (YYYY-MM → Nome do mês PT-BR)
$mesIso = (string)($r['audit_month'] ?? '');
$mesNome = '';

if (preg_match('/^\d{4}-(\d{2})$/', $mesIso, $m)) {
    $mapMes = [
        '01' => 'Janeiro',
        '02' => 'Fevereiro',
        '03' => 'Março',
        '04' => 'Abril',
        '05' => 'Maio',
        '06' => 'Junho',
        '07' => 'Julho',
        '08' => 'Agosto',
        '09' => 'Setembro',
        '10' => 'Outubro',
        '11' => 'Novembro',
        '12' => 'Dezembro',
    ];
    $mesNome = $mapMes[$m[1]] ?? '';
}
        $line = [
            'ticket_number'         => (string)($r['ticket_number'] ?? ''),
            'ticket_type'           => (string)($r['ticket_type'] ?? ''),
            'kyndryl_auditor'       => (string)($r['kyndryl_auditor'] ?? ''),
            'petrobras_inspector'   => (string)($r['petrobras_inspector'] ?? ''),
            'audited_supplier'      => (string)($r['audited_supplier'] ?? ''),
            'location'              => (string)($r['location'] ?? ''),
            'audit_month' => $mesNome,
            'sla_met_label'         => $sla,
            'priority_label'        => $pri,
            'category'              => (string)($r['category'] ?? ''),
            'resolver_group'        => (string)($r['resolver_group'] ?? ''),
            'is_compliant_label'    => $isComp, // << AQUI vai Sim/Não
            'noncompliance_reasons' => (string)($r['noncompliance_reasons'] ?? ''),
        ];
        $out[] = $line;
    }

    return $out;
}


}