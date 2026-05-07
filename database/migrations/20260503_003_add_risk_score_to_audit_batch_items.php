<?php

class Migration_20260503_003_add_risk_score_to_audit_batch_items
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            ALTER TABLE audit_batch_items
            ADD COLUMN risk_score INTEGER DEFAULT 0
        ");
    }

    public function down(PDO $pdo): void
    {
        // SQLite não suporta DROP COLUMN diretamente
    }
}