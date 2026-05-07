<?php
declare(strict_types=1);

class Migration_20260502_001_create_audit_batch_sessions
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_batch_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                created_by INTEGER NOT NULL,
                created_at DATETIME NOT NULL,
                source_type TEXT NOT NULL,
                source_name TEXT,
                status TEXT NOT NULL,
                total_items INTEGER DEFAULT 0,
                processed_items INTEGER DEFAULT 0,
                notes TEXT
            )
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("
            DROP TABLE IF EXISTS audit_batch_sessions
        ");
    }
}