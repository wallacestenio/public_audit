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
     * Insere a entrada principal (salvando campos + strings de reasons).
     * @param array $data
     * @param int[] $reasonIds (atualmente não grava ponte, mantemos as strings em audit_entries)
     * @return int ID gerado (ou rowid fallback)
     * @throws PDOException
     */
    public function insertWithReasons(array $data, array $reasonIds = []): int
    {
        $this->pdo->beginTransaction();
        try {
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
                ':priority'            => (int)$data['priority'],
                ':requester_name'      => $data['requester_name'],
                ':category'            => $data['category'],
                ':resolver_group'      => $data['resolver_group'],
                ':sla_met'             => (int)$data['sla_met'],
                ':is_compliant'        => (int)$data['is_compliant'],
                ':nc_ids'              => $data['noncompliance_reason_ids'] ?? null,
                ':nc_labels'           => $data['noncompliance_reasons']    ?? null,
            ]);

            // Obtém ID: para PK autoincrement, lastInsertId(); senão tenta rowid por ticket_number
            $entryId = (int)$this->pdo->lastInsertId();
            if ($entryId === 0) {
                $ridStmt = $this->pdo->prepare('SELECT rowid FROM audit_entries WHERE ticket_number = :tk');
                $ridStmt->execute([':tk' => $data['ticket_number']]);
                $entryId = (int)($ridStmt->fetchColumn() ?: 0);
            }

            $this->pdo->commit();

            if (class_exists(\App\Support\Logger::class)) {
                (new \App\Support\Logger())->write(
                    'debug.log',
                    date('c') . " MODEL.insert ok=" . ($ok ? '1':'0')
                    . " rc=" . $stmt->rowCount()
                    . " lastId=" . $entryId
                    . PHP_EOL
                );
            }

            return $entryId;

        } catch (\PDOException $e) {
            $this->pdo->rollBack();

            if (class_exists(\App\Support\Logger::class)) {
                (new \App\Support\Logger())->write(
                    'debug.log',
                    date('c') . " MODEL.PDOEX code={$e->getCode()} info=" . print_r($e->errorInfo, true)
                    . " msg=" . $e->getMessage() . PHP_EOL
                );
            }

            throw $e;
        }
    }
}