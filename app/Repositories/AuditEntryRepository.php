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

    $sql = "
INSERT INTO audit_entries (
  ticket_number,
  ticket_type,
  kyndryl_auditor,
  petrobras_inspector,
  audited_supplier,
  location,
  audit_month,
  sla_met,
  priority,
  category,
  resolver_group,
  is_compliant,
  noncompliance_reasons,
  noncompliance_reason_ids,
  import_batch_id
) VALUES (
  :ticket_number,
  :ticket_type,
  :kyndryl_auditor,
  :petrobras_inspector,
  :audited_supplier,
  :location,
  :audit_month,
  :sla_met,
  :priority,
  :category,
  :resolver_group,
  :is_compliant,
  :noncompliance_reasons,
  :noncompliance_reason_ids,
  :import_batch_id
)
";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
  ':ticket_number' => $data['ticket_number'] ?? null,
  ':ticket_type' => $data['ticket_type'] ?? null,
  ':kyndryl_auditor' => $data['kyndryl_auditor'] ?? null,
  ':petrobras_inspector' => $data['petrobras_inspector'] ?? null,
  ':audited_supplier' => $data['audited_supplier'] ?? null,
  ':location' => $data['location'] ?? null,
  ':audit_month' => $data['audit_month'] ?? null,
  ':sla_met' => $data['sla_met'] ?? null,
  ':priority' => $data['priority'] ?? null,
  ':category' => $data['category'] ?? null,
  ':resolver_group' => $data['resolver_group'] ?? null,
  ':is_compliant' => $data['is_compliant'] ?? null,
  ':noncompliance_reasons' => $data['noncompliance_reasons'] ?? null,
  ':noncompliance_reason_ids' => $data['noncompliance_reason_ids'] ?? null,
  ':import_batch_id' => $data['import_batch_id'] ?? null,
]);


    $id = (int)$pdo->lastInsertId();
    if ($id === 0) {
        $ridStmt = $pdo->prepare('SELECT id FROM audit_entries WHERE ticket_number = :tk');
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

    // ✅ filtro por usuário
    if (!empty($filters['user_id'])) {
        $where[] = 'user_id = :user_id';
        $params[':user_id'] = (int)$filters['user_id'];
    }

    // ✅ filtro por mês
    if (!empty($filters['audit_month'])) {
        $where[] = 'substr(audit_month, 1, 7) = :audit_month';
        $params[':audit_month'] = $filters['audit_month'];
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY id ASC';

    $pdo  = $this->rawPdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // ✅ GUARDA PRIMEIRO
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $out = [];

    foreach ($rows as $r) {

        $sla    = ((string)$r['sla_met'] === '1') ? 'Sim' : 'Não';
        $pri    = 'Prioridade ' . (int)$r['priority'];
        $isComp = ((string)$r['is_compliant'] === '1') ? 'Sim' : 'Não';

        // Converter mês
        $mesIso  = (string)$r['audit_month'];
        $mesNome = '';

        if (preg_match('/^\d{4}-(\d{2})$/', $mesIso, $m)) {
            $mapMes = [
                '01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril',
                '05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto',
                '09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro',
            ];
            $mesNome = $mapMes[$m[1]] ?? '';
        }

        // ✅ quebra das NC
        $ncsRaw = trim((string)($r['noncompliance_reasons'] ?? ''));
        $ncs = $ncsRaw !== ''
            ? array_map('trim', explode(';', $ncsRaw))
            : [];

        // sem NC → 1 linha
        if (!$ncs) {
            $out[] = [
                'ticket_number'         => $r['ticket_number'],
                'ticket_type'           => $r['ticket_type'],
                'kyndryl_auditor'       => $r['kyndryl_auditor'],
                'petrobras_inspector'   => $r['petrobras_inspector'],
                'audited_supplier'      => $r['audited_supplier'],
                'location'              => $r['location'],
                'audit_month'           => $mesNome,
                'sla_met_label'         => $sla,
                'priority_label'        => $pri,
                'category'              => $r['category'],
                'resolver_group'        => $r['resolver_group'],
                'is_compliant_label'    => $isComp,
                'noncompliance_reasons' => '',
            ];
            continue;
        }

        // ✅ 1 NC = 1 linha
        foreach ($ncs as $nc) {
            $out[] = [
                'ticket_number'         => $r['ticket_number'],
                'ticket_type'           => $r['ticket_type'],
                'kyndryl_auditor'       => $r['kyndryl_auditor'],
                'petrobras_inspector'   => $r['petrobras_inspector'],
                'audited_supplier'      => $r['audited_supplier'],
                'location'              => $r['location'],
                'audit_month'           => $mesNome,
                'sla_met_label'         => $sla,
                'priority_label'        => $pri,
                'category'              => $r['category'],
                'resolver_group'        => $r['resolver_group'],
                'is_compliant_label'    => $isComp,
                'noncompliance_reasons' => $nc,
            ];
        }
    }

    return $out;
}


public function fetchNoncomplianceStats(?string $month = null): array
{
    $pdo = $this->rawPdo();

    $sql = "
        SELECT noncompliance_reasons
        FROM audit_entries
        WHERE is_compliant = 0
          AND noncompliance_reasons IS NOT NULL
          AND TRIM(noncompliance_reasons) <> ''
    ";

    $params = [];

    if ($month) {
        $sql .= " AND audit_month = :month";
        $params[':month'] = $month;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
}
public function listAuditMonths(): array
{
    $pdo = $this->rawPdo();

    $sql = "
        SELECT DISTINCT audit_month
        FROM audit_entries
        ORDER BY audit_month DESC
    ";

    $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_COLUMN);

    return $rows ?: [];
}

public function fetchNoncomplianceGroupedByResolver(
    int $userId,
    ?string $month,
    ?string $resolverGroup = null
): array {
    $sql = "
        SELECT resolver_group, noncompliance_reasons
        FROM audit_entries
        WHERE user_id = :user_id
          AND (:month IS NULL OR audit_month = :month)
          AND (:resolver_group IS NULL OR resolver_group = :resolver_group)
    ";

    $stmt = $this->rawPdo()->prepare($sql);
    $stmt->execute([
        'user_id'        => $userId,
        'month'          => $month,
        'resolver_group' => $resolverGroup,
    ]);

    return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
}



public function listAuditMonthsByUser(int $userId): array
{
    $pdo  = $this->rawPdo();
    $stmt = $pdo->prepare(
        "SELECT DISTINCT audit_month
         FROM audit_entries
         WHERE user_id = :user_id
         ORDER BY audit_month DESC"
    );

    $stmt->execute([':user_id' => $userId]);

    return $stmt->fetchAll(\PDO::FETCH_COLUMN);
}
public function insertWithReasons(array $data, array $reasonIds = []): int
{
    return $this->model->insertWithReasons($data, $reasonIds);
}

/**
 * Retorna um mapa [label => id] para as justificativas de não conformidade.
 */
public function mapNoncomplianceReasonLabelToId(): array
{
    $stmt = $this->rawPdo()->query(
        "SELECT id, noncompliance_reason FROM noncompliance_reasons"
    );

    $map = [];
    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $map[trim($row['noncompliance_reason'])] = (int) $row['id'];
    }
    return $map;
}
// fim da classe//


public function fetchNoncomplianceStatsByUser(
    int $userId,
    ?string $month = null,
    ?string $resolverGroup = null
): array {
    $sql = "
        SELECT noncompliance_reasons
        FROM audit_entries
        WHERE user_id = :user_id
          AND (:month IS NULL OR audit_month = :month)
          AND (:resolver_group IS NULL OR resolver_group = :resolver_group)
    ";

    $stmt = $this->rawPdo()->prepare($sql);
    $stmt->execute([
        'user_id'        => $userId,
        'month'          => $month,
        'resolver_group' => $resolverGroup,
    ]);

    return $stmt->fetchAll(\PDO::FETCH_COLUMN);
}

/**
 * Associa um registro de audit_entries a um lote de importação (import_batches).
 * Útil para rastrear quais registros vieram de qual arquivo.
 */
public function attachImportBatch(string $ticketNumber, int $batchId): void
{
    $stmt = $this->rawPdo()->prepare(
        "UPDATE audit_entries
         SET import_batch_id = :batch
         WHERE TRIM(UPPER(ticket_number)) = TRIM(UPPER(:ticket))"
    );

    $stmt->execute([
        ':batch'  => $batchId,
        ':ticket' => $ticketNumber,
    ]);
}

public function listResolverGroupsByUser(int $userId): array
{
    $sql = "
        SELECT DISTINCT resolver_group
        FROM audit_entries
        WHERE user_id = :user_id
          AND resolver_group IS NOT NULL
          AND TRIM(resolver_group) <> ''
        ORDER BY resolver_group
    ";

    $stmt = $this->rawPdo()->prepare($sql);
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
}

public function countAuditsByMonth(
    int $userId,
    ?string $month
): array {
    $sql = "
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN is_compliant = 0 THEN 1 ELSE 0 END) AS noncompliant
        FROM audit_entries
        WHERE user_id = :user_id
          AND (:month IS NULL OR audit_month = :month)
    ";

    $stmt = $this->rawPdo()->prepare($sql);
    $stmt->execute([
        'user_id' => $userId,
        'month'   => $month,
    ]);

    return $stmt->fetch(\PDO::FETCH_ASSOC)
        ?: ['total' => 0, 'noncompliant' => 0];
}




}