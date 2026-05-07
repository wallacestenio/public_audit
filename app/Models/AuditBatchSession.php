<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class AuditBatchSession
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Cria uma nova sessão de auditoria em lote.
     * Retorna o ID da sessão criada.
     */
    public function create(
        int $createdBy,
        string $sourceType,
        ?string $sourceName = null
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_batch_sessions (
                created_by,
                created_at,
                source_type,
                source_name,
                status,
                total_items,
                processed_items
            ) VALUES (
                :created_by,
                datetime('now'),
                :source_type,
                :source_name,
                'open',
                0,
                0
            )
        ");

        $stmt->execute([
            ':created_by' => $createdBy,
            ':source_type' => $sourceType,
            ':source_name' => $sourceName,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Busca uma sessão pelo ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM audit_batch_sessions
            WHERE id = :id
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

 /**
 * Marca a sessão como finalizada
 */
public function markAsFinished(int $sessionId): void
{
    $stmt = $this->pdo->prepare("
        UPDATE audit_batch_sessions
        SET status = 'finished'
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $sessionId,
    ]);
}

/**
 * Retorna o resumo final da sessão
 */
public function getSessionSummary(int $sessionId): array
{
    // resumo dos itens
    $stmt = $this->pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'saved' THEN 1 ELSE 0 END) AS saved,
            SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) AS skipped,
            SUM(CASE WHEN attention_level = 'HIGH' THEN 1 ELSE 0 END) AS high,
            SUM(CASE WHEN attention_level = 'MEDIUM' THEN 1 ELSE 0 END) AS medium,
            SUM(CASE WHEN attention_level = 'LOW' THEN 1 ELSE 0 END) AS low
        FROM audit_batch_items
        WHERE session_id = :session_id
    ");
    $stmt->execute([':session_id' => $sessionId]);
    $items = $stmt->fetch(PDO::FETCH_ASSOC);

    // dados da sessão
    $stmt = $this->pdo->prepare("
        SELECT created_at, finished_at
        FROM audit_batch_sessions
        WHERE id = :id
    ");
    $stmt->execute([':id' => $sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    // duração (em segundos)
    $durationSeconds = null;
    if (!empty($session['finished_at'])) {
        $start = strtotime($session['created_at']);
        $end   = strtotime($session['finished_at']);
        $durationSeconds = max(0, $end - $start);
    }

    return [
        'total'    => (int)$items['total'],
        'saved'    => (int)$items['saved'],
        'skipped'  => (int)$items['skipped'],
        'high'     => (int)$items['high'],
        'medium'   => (int)$items['medium'],
        'low'      => (int)$items['low'],
        'created_at'  => $session['created_at'],
        'finished_at' => $session['finished_at'],
        'duration_seconds' => $durationSeconds,
    ];
}
}   