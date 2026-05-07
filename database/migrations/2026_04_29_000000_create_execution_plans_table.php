<?php
declare(strict_types=1);

class Migration_2026_04_29_000000_create_execution_plans_table
{
    public function up(PDO $pdo, string $driver): void
    {
        if ($driver !== 'sqlite') {
            throw new RuntimeException('Migration suportada apenas para SQLite.');
        }

        $pdo->exec("
            CREATE TABLE execution_plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                version TEXT NOT NULL,
                audit_type TEXT NOT NULL,
                status TEXT NOT NULL,
                normative_summary TEXT NOT NULL,
                pdf_path TEXT NULL,
                pdf_hash TEXT NULL,
                pdf_uploaded_at TEXT NULL,
                hash_fingerprint TEXT NULL,
                created_by INTEGER NOT NULL,
                activated_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
        ");
    }

    public function down(PDO $pdo, string $driver): void
    {
        $pdo->exec("DROP TABLE IF EXISTS execution_plans;");
    }
}