<?php
declare(strict_types=1);

use PDO;

class Migration_2026_02_01_000002_create_audit_session_items
{
    public function up(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS audit_session_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,

                session_id INTEGER NOT NULL,

                ticket_number TEXT,
                raw_text TEXT,

                sn_category TEXT,
                sn_service TEXT,
                sn_item TEXT,

                resolver_group TEXT,
                priority INTEGER,

                import_source TEXT,
                status TEXT DEFAULT "PENDING",

                created_at TEXT,
                removed_at TEXT,

                FOREIGN KEY (session_id) REFERENCES audit_sessions(id)
            )'
        );
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS audit_session_items');
    }
}