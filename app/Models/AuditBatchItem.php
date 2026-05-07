<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class AuditBatchItem
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Adiciona um novo item (chamado) à sessão de auditoria em lote
     */
    public function addItem(
        int $sessionId,
        int $itemIndex,
        string $rawText,
        ?string $parsedTicket = null
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_batch_items (
                session_id,
                item_index,
                raw_text,
                parsed_ticket,
                status,
                created_at
            ) VALUES (
                :session_id,
                :item_index,
                :raw_text,
                :parsed_ticket,
                'pending',
                datetime('now')
            )
        ");

        $stmt->execute([
            ':session_id'    => $sessionId,
            ':item_index'    => $itemIndex,
            ':raw_text'      => $rawText,
            ':parsed_ticket' => $parsedTicket,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Retorna todos os itens de uma sessão, ordenados
     */
    public function findBySession(int $sessionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM audit_batch_items
            WHERE session_id = :session_id
            ORDER BY item_index ASC
        ");

        $stmt->execute([
            ':session_id' => $sessionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza o status do item
     * pending | reviewing | saved | skipped
     */
    public function updateStatus(int $itemId, string $status): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE audit_batch_items
            SET status = :status,
                reviewed_at = datetime('now')
            WHERE id = :id
        ");

        $stmt->execute([
            ':status' => $status,
            ':id'     => $itemId,
        ]);
    }

    

    /**
     * Atualiza o resultado da análise APKIA
     */
    public function updateAnalysis(
        int $itemId,
        string $apkiaResultJson,
        ?string $attentionLevel = null,
        bool $hasCritical = false,
        ?int $slaMet = null,
        int $riskScore = 0
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE audit_batch_items
            SET
                apkia_result = :apkia_result,
                attention_level = :attention_level,
                has_critical = :has_critical,
                sla_met = :sla_met,
                risk_score = :risk_score
            WHERE id = :id
        ");

        $stmt->execute([
            ':apkia_result'    => $apkiaResultJson,
            ':attention_level' => $attentionLevel,
            ':has_critical'    => $hasCritical ? 1 : 0,
            ':sla_met'         => $slaMet,
            ':risk_score'      => $riskScore,
            ':id'              => $itemId,
        ]);
    }

    
/**
     * Retorna o próximo item da sessão, priorizado por risco
     */
    public function getNextItem(int $sessionId): ?array
{
    $stmt = $this->pdo->prepare("
        SELECT *
        FROM audit_batch_items
        WHERE session_id = :session_id
          AND status IN ('pending', 'reviewing')
          AND attention_level IS NOT NULL
          AND risk_score > 0
          AND apkia_result IS NOT NULL
        ORDER BY
          CASE attention_level
            WHEN 'HIGH' THEN 1
            WHEN 'MEDIUM' THEN 2
            WHEN 'LOW' THEN 3
          END,
          risk_score DESC,
          item_index ASC
        LIMIT 1
    ");

    $stmt->execute([
        ':session_id' => $sessionId,
    ]);

    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    return $item ?: null;
}

    /**
 * Retorna estatísticas de progresso da sessão
 */
public function getSessionProgress(int $sessionId): array
{
    $stmt = $this->pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status IN ('saved', 'skipped') THEN 1 ELSE 0 END) AS done,
            SUM(CASE WHEN status IN ('pending', 'reviewing') THEN 1 ELSE 0 END) AS remaining
        FROM audit_batch_items
        WHERE session_id = :session_id
    ");

    $stmt->execute([
        ':session_id' => $sessionId,
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total' => 0,
        'done' => 0,
        'remaining' => 0,
    ];
}

/**
 * Retorna itens saved para exportação
 */
public function getSavedItemsForExport(int $sessionId): array
{
    $stmt = $this->pdo->prepare("
        SELECT
            id,
            item_index,
            raw_text,
            attention_level,
            risk_score,
            sla_met,
            apkia_result,
            reviewed_at
        FROM audit_batch_items
        WHERE session_id = :session_id
          AND status = 'saved'
        ORDER BY item_index ASC
    ");

    $stmt->execute([':session_id' => $sessionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getPendingItemsBySession(int $sessionId): array
{
    $stmt = $this->pdo->prepare("
        SELECT *
        FROM audit_batch_items
        WHERE session_id = :session_id
          AND status = 'pending'
    ");
    $stmt->execute([':session_id' => $sessionId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getSkippedItemsBySession(int $sessionId): array
{
    $stmt = $this->pdo->prepare("
        SELECT
            id,
            item_index,
            raw_text,
            attention_level,
            risk_score,
            apkia_result,
            status,
            created_at
        FROM audit_batch_items
        WHERE session_id = :session_id
          AND status = 'skipped'
        ORDER BY item_index ASC
    ");

    $stmt->execute([
        ':session_id' => $sessionId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function updateAfterApkia(
    int $itemId,
    string $attentionLevel,
    int $riskScore,
    string $apkiaResult
): void {
    $stmt = $this->pdo->prepare("
        UPDATE audit_batch_items
        SET
          attention_level = :attention_level,
          risk_score = :risk_score,
          apkia_result = :apkia_result
        WHERE id = :id
    ");
    $stmt->execute([
        ':attention_level' => $attentionLevel,
        ':risk_score'      => $riskScore,
        ':apkia_result'    => $apkiaResult,
        ':id'              => $itemId,
    ]);
}


    
}
