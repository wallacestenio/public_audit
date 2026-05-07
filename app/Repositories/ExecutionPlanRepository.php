<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class ExecutionPlanRepository
{
    /**
     * Cria um Plano de Execução em estado de rascunho
     * (com PDF opcional)
     */
    public static function createDraft(
        PDO $pdo,
        string $name,
        string $version,
        string $auditType,
        string $normativeSummary,
        int $createdBy,
        ?string $pdfPath = null,
        ?string $pdfHash = null
    ): void {

        // Hash do texto normativo (régua da IA)
        $normativeHash = hash('sha256', trim($normativeSummary));

        $stmt = $pdo->prepare("
            INSERT INTO execution_plans (
                name,
                version,
                audit_type,
                status,
                normative_summary,
                hash_fingerprint,
                pdf_path,
                pdf_hash,
                pdf_uploaded_at,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :version,
                :audit_type,
                'draft',
                :normative_summary,
                :hash_fingerprint,
                :pdf_path,
                :pdf_hash,
                :pdf_uploaded_at,
                :created_by,
                datetime('now'),
                datetime('now')
            )
        ");

        $stmt->execute([
            ':name'              => $name,
            ':version'           => $version,
            ':audit_type'        => $auditType,
            ':normative_summary' => $normativeSummary,
            ':hash_fingerprint'  => $normativeHash,
            ':pdf_path'          => $pdfPath,
            ':pdf_hash'          => $pdfHash,
            ':pdf_uploaded_at'   => $pdfPath !== null ? date('Y-m-d H:i:s') : null,
            ':created_by'        => $createdBy,
        ]);
    }

    /**
     * Lista todos os Planos de Execução
     */
    public static function listAll(PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT *
            FROM execution_plans
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
 * Busca um Plano de Execução pelo ID
 */
public static function findById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM execution_plans
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    return $plan ?: null;
}
public static function getActive(PDO $pdo): ?array
{
    $stmt = $pdo->query("
        SELECT *
        FROM execution_plans
        WHERE status = 'active'
        LIMIT 1
    ");

    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    return $plan ?: null;
}
}
