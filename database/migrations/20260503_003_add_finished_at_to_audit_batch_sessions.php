<?php
declare(strict_types=1);

class Migration_20260503_003_add_finished_at_to_audit_batch_sessions
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            ALTER TABLE audit_batch_sessions
            ADD COLUMN finished_at TEXT
        ");
    }

    public function down(PDO $pdo): void
    {
        // SQLite não suporta DROP COLUMN diretamente.
        // Rollback estrutural não é trivial.
    }
}