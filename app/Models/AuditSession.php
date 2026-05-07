<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class AuditSession
{
    protected static string $table = 'audit_sessions';

    public static function findById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM ' . self::$table . ' WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(PDO $pdo, array $data): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_sessions
             (name, audit_type, audit_month, execution_plan_id, status, created_at)
             VALUES
             (:name, :audit_type, :audit_month, :execution_plan_id, :status, :created_at)'
        );

        $stmt->execute([
            'name'              => $data['name'] ?? null,
            'audit_type'        => $data['audit_type'] ?? null,
            'audit_month'       => $data['audit_month'] ?? null,
            'execution_plan_id' => $data['execution_plan_id'] ?? null,
            'status'            => $data['status'] ?? 'OPEN',
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        return (int) $pdo->lastInsertId();
    }
}