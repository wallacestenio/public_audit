<?php

class Migration_20260503_002_create_audit_batch_items
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_batch_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id INTEGER NOT NULL,
                item_index INTEGER NOT NULL,

                raw_text TEXT NOT NULL,
                parsed_ticket TEXT,

                apkia_result TEXT,
                attention_level TEXT,      -- HIGH | MEDIUM | LOW
                has_critical INTEGER DEFAULT 0,
                sla_met INTEGER,

                status TEXT NOT NULL DEFAULT 'pending', -- pending | reviewing | saved | skipped
                error_message TEXT,

                created_at DATETIME NOT NULL,
                reviewed_at DATETIME,

                FOREIGN KEY (session_id)
                    REFERENCES audit_batch_sessions(id)
                    ON DELETE CASCADE
            )
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("
            DROP TABLE IF EXISTS audit_batch_items
        ");
    }
}