<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class AuditSessionItem
{
    public static function insert(PDO $pdo, array $data): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_session_items
            (session_id, ticket_number, raw_text, sn_category, sn_service, sn_item,
             resolver_group, priority, import_source, status, created_at)
            VALUES
            (:session_id, :ticket_number, :raw_text, :sn_category, :sn_service, :sn_item,
             :resolver_group, :priority, :import_source, :status, datetime("now"))'
        );

        $stmt->execute($data);
    }

    public static function listBySession(PDO $pdo, int $sessionId, string $status = 'PENDING'): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM audit_session_items
             WHERE session_id = :sid AND status = :status'
        );

        $stmt->execute([
            'sid' => $sessionId,
            'status' => $status
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function remove(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare(
            'UPDATE audit_session_items
             SET status = "REMOVED", removed_at = datetime("now")
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }
}