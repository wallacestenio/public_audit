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

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * ✅ MÉTODO PRINCIPAL (já existente)
     * Insere entrada + razões numa transação
     */
    



    /**
     * ✅ MÉTODO NOVO (fluxo individual via APKIA)
     * NÃO substitui o acima — complementa
     */
    public function insertSingle(array $data): int
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                '
                INSERT INTO audit_entries (
                    user_id,
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
                    noncompliance_reason_ids,
                    noncompliance_reasons
                ) VALUES (
                    :user_id,
                    :ticket_number,
                    :ticket_type,
                    :kyndryl_auditor,
                    :petrobras_inspector,
                    :audited_supplier,
                    :location,
                    :audit_month,
                    :priority,
                    :category,
                    :resolver_group,
                    :sla_met,
                    :is_compliant,
                    :nc_ids,
                    :nc_labels
                )
                '
            );

            $stmt->execute([
                ':user_id'             => (int) $data['user_id'],
                ':ticket_number'       => strtoupper(trim($data['ticket_number'])),
                ':ticket_type'         => $data['ticket_type'] ?? null,
                ':kyndryl_auditor'     => $data['kyndryl_auditor'] ?? null,
                ':petrobras_inspector' => $data['petrobras_inspector'] ?? null,
                ':audited_supplier'    => $data['audited_supplier'] ?? null,
                ':location'            => $data['location'] ?? null,
                ':audit_month'         => $data['audit_month'] ?? null,
                ':priority'            => (int) ($data['priority'] ?? 0),
                ':category'            => $data['category'] ?? null,
                ':resolver_group'      => $data['resolver_group'] ?? null,
                ':sla_met'             => (int) ($data['sla_met'] ?? 1),
                ':is_compliant'        => (int) ($data['is_compliant'] ?? 1),
                ':nc_ids'              => $data['noncompliance_reason_ids'] ?? null,
                ':nc_labels'           => $data['noncompliance_reasons'] ?? null,
            ]);

            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            return $id;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
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
        $sql = "
            INSERT INTO audit_entries (
                user_id,
                ticket_number,
                ticket_type,
                kyndryl_auditor,
                petrobras_inspector,
                audited_supplier,
                location,
                audit_month,
                priority,
                requester_name,
                category,
                resolver_group,
                sla_met,
                is_compliant,
                noncompliance_reason_ids,
                noncompliance_reasons
            ) VALUES (
                :user_id,
                :ticket_number,
                :ticket_type,
                :kyndryl_auditor,
                :petrobras_inspector,
                :audited_supplier,
                :location,
                :audit_month,
                :priority,
                :requester_name,
                :category,
                :resolver_group,
                :sla_met,
                :is_compliant,
                :nc_ids,
                :nc_labels
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            // ✅ CAMPO CRÍTICO (corrige o seu problema)
            ':user_id'             => (int) $data['user_id'],

            ':ticket_number'       => $data['ticket_number'],
            ':ticket_type'         => $data['ticket_type'],
            ':kyndryl_auditor'     => $data['kyndryl_auditor'],
            ':petrobras_inspector' => $data['petrobras_inspector'],
            ':audited_supplier'    => $data['audited_supplier'],
            ':location'            => $data['location'],
            ':audit_month'         => $data['audit_month'],
            ':priority'            => (int) $data['priority'],
            ':requester_name'      => $data['requester_name'] ?? null,
            ':category'            => $data['category'],
            ':resolver_group'      => $data['resolver_group'],
            ':sla_met'             => (int) $data['sla_met'],
            ':is_compliant'        => (int) $data['is_compliant'],

            // ✅ Não conformidades consolidadas
            ':nc_ids'              => $data['noncompliance_reason_ids'] ?? null,
            ':nc_labels'           => $data['noncompliance_reasons'] ?? null,
        ]);

        $entryId = (int) $this->pdo->lastInsertId();

        // fallback seguro (caso lastInsertId não funcione)
        if ($entryId === 0) {
            $check = $this->pdo->prepare(
                'SELECT rowid FROM audit_entries WHERE ticket_number = :tk'
            );
            $check->execute([':tk' => $data['ticket_number']]);
            $entryId = (int) ($check->fetchColumn() ?: 0);
        }

        $this->pdo->commit();
        return $entryId;

    } catch (PDOException $e) {
        $this->pdo->rollBack();
        throw $e;
    }
}

public function getInspectorAndLocationByAuditor(int $auditorId): ?array
{
    $sql = "
        SELECT
    k.kyndryl_auditor,
    pi.petrobras_inspector,
    l.location
FROM kyndryl_auditors k
LEFT JOIN petrobras_inspectors pi ON pi.id = k.inspector_id
LEFT JOIN locations l ON l.id = k.location_id
WHERE k.user_id = :id
LIMIT 1
    ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id' => $auditorId]);

    return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

}