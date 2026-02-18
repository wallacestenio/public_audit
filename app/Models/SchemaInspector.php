<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class SchemaInspector
{
    public function __construct(private PDO $pdo) {}

    public function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->pdo->query("PRAGMA table_info($table)");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($cols as $c) {
            if (strcasecmp($c['name'], $column) === 0) return true;
        }
        return false;
    }

    public function resolveColumn(string $table, string $base): ?string
    {
        $idCol   = $base . '_id';
        $codeCol = $base . '_code';
        if ($this->hasColumn($table, $idCol))   return $idCol;
        if ($this->hasColumn($table, $codeCol)) return $codeCol;
        if ($this->hasColumn($table, $base))    return $base;
        return null;
    }
}
