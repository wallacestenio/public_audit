<?php
declare(strict_types=1);

class Migration_20260429_110000_create_execution_plans_table
{
    public function up(PDO $pdo, string $driver): void
    {
        if (stripos($driver, 'sqlite') !== false) {

            $pdo->exec('PRAGMA foreign_keys = OFF;');

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS execution_plans (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,

                    name TEXT NOT NULL,
                    version TEXT NOT NULL,

                    audit_type TEXT NOT NULL
                        CHECK (audit_type IN ('estoque','chamados','ambos')),

                    status TEXT NOT NULL DEFAULT 'draft'
                        CHECK (status IN ('draft','active','archived')),

                    normative_summary TEXT NOT NULL,
                    hash_fingerprint TEXT,

                    created_by INTEGER,
                    activated_at TEXT,

                    created_at TEXT NOT NULL DEFAULT (datetime('now')),
                    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
                );
            ");

            $pdo->exec("
                CREATE INDEX IF NOT EXISTS idx_execution_plans_type_status
                ON execution_plans (audit_type, status);
            ");

            $pdo->exec('PRAGMA foreign_keys = ON;');

        } else {
            throw new RuntimeException(
                "Driver não suportado para execution_plans: {$driver}"
            );
        }
    }

    public function down(PDO $pdo, string $driver): void
    {
        if (stripos($driver, 'sqlite') !== false) {

            $pdo->exec('PRAGMA foreign_keys = OFF;');
            $pdo->exec("DROP TABLE IF EXISTS execution_plans;");
            $pdo->exec('PRAGMA foreign_keys = ON;');

        } else {
            throw new RuntimeException(
                "Rollback não suportado para execution_plans: {$driver}"
            );
        }
    }
}
`` --- IGNORE ---