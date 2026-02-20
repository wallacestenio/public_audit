<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

final class AuditEntry
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function getPdo(): \PDO
{
    return $this->pdo;
}

    /**
     * Insere a entrada principal e as razões (tabela-ponte) numa transação.
     * Retorna o ID da entrada criada.
     *
     * @param array      $data       Campos de audit_entries (SEM audit_date)
     * @param int[]      $reasonIds  IDs válidos de noncompliance_reasons
     * @return int
     * @throws PDOException
     */
    public function insertWithReasons(array $data, array $reasonIds = []): int
{
    $this->pdo->beginTransaction();
    try {
        // INSERT principal (gravando tudo direto em audit_entries)
        $sql = "INSERT INTO audit_entries
          (ticket_number, ticket_type, kyndryl_auditor, petrobras_inspector, audited_supplier, location,
           audit_month, priority, requester_name, category, resolver_group, sla_met, is_compliant,
           noncompliance_reason_ids, noncompliance_reasons)
          VALUES
          (:ticket_number, :ticket_type, :kyndryl_auditor, :petrobras_inspector, :audited_supplier, :location,
           :audit_month, :priority, :requester_name, :category, :resolver_group, :sla_met, :is_compliant,
           :nc_ids, :nc_labels)";

        $stmt = $this->pdo->prepare($sql);

        $ok = $stmt->execute([
            ':ticket_number'       => $data['ticket_number'],
            ':ticket_type'         => $data['ticket_type'],
            ':kyndryl_auditor'     => $data['kyndryl_auditor'],
            ':petrobras_inspector' => $data['petrobras_inspector'],
            ':audited_supplier'    => $data['audited_supplier'],
            ':location'            => $data['location'],
            ':audit_month'         => $data['audit_month'],
            ':priority'            => (int)$data['priority'],     // se a coluna ainda é TEXT, SQLite coerce sem erro
            ':requester_name'      => $data['requester_name'],
            ':category'            => $data['category'],
            ':resolver_group'      => $data['resolver_group'],
            ':sla_met'             => (int)$data['sla_met'],
            ':is_compliant'        => (int)$data['is_compliant'],

            // Novas colunas (strings separadas por ;)
            ':nc_ids'              => $data['noncompliance_reason_ids'] ?? null,   // ex.: "10;5;1;3"
            ':nc_labels'           => $data['noncompliance_reasons']    ?? null,   // ex.: "Label A;Label B;..."
        ]);

        // Obtém um ID para retorno:
        // 1) Se houver "id INTEGER PRIMARY KEY", lastInsertId() retorna o id autoincrement
        // 2) Se a PK for TEXT (ticket_number), lastInsertId() pode retornar 0; usamos fallback via rowid
        $entryId = (int)$this->pdo->lastInsertId();
        if ($entryId === 0) {
            $ridStmt = $this->pdo->prepare('SELECT rowid FROM audit_entries WHERE ticket_number = :tk');
            $ridStmt->execute([':tk' => $data['ticket_number']]);
            $entryId = (int)($ridStmt->fetchColumn() ?: 0);
        }

        $this->pdo->commit();

        // Logs de sanidade
        (new \App\Support\Logger())->write('debug.log',
            date('c') . " MODEL.insert ok=" . ($ok ? '1':'0')
            . " rc=" . $stmt->rowCount()
            . " lastId=" . $entryId
            . PHP_EOL
        );

        // SELECT de confirmação — garante que inseriu (ou mostra o motivo)
        $check = $this->pdo->prepare(
            "SELECT ticket_number, noncompliance_reason_ids 
             FROM audit_entries 
             WHERE ticket_number = :tk"
        );
        $check->execute([':tk' => $data['ticket_number']]);
        $row = $check->fetch(\PDO::FETCH_ASSOC);

        (new \App\Support\Logger())->write('debug.log',
            date('c') . ' MODEL.check row=' . json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL
        );

        return $entryId;

    } catch (\PDOException $e) {
        $this->pdo->rollBack();

        (new \App\Support\Logger())->write('debug.log',
            date('c') . " MODEL.PDOEX code={$e->getCode()} info=" . print_r($e->errorInfo, true)
            . " msg=" . $e->getMessage() . PHP_EOL
        );

        throw $e;
    }
}
}