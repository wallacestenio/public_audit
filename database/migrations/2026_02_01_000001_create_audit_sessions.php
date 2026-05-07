<?php
declare(strict_types=1);

use PDO;

class Migration_2026_02_01_000001_create_audit_sessions
{
    public function up(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS audit_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                name TEXT,
                audit_type TEXT,
                audit_month TEXT,

                execution_plan_id INTEGER,
                status TEXT DEFAULT "OPEN",

                created_by TEXT,
                created_at TEXT
            )'
        );
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS audit_sessions');
    }
}