<?php
declare(strict_types=1);

class Migration_20260429_100000_create_inventory_tables
{
    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = OFF;');

            // ===============================
            // Tabela: inventory_items
            // ===============================
            $pdo->exec("
                CREATE TABLE inventory_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL UNIQUE,
                    active INTEGER NOT NULL DEFAULT 1
                );
            ");

            // Seed inicial (Itens Auditados - Estoque)
            $pdo->exec("
                INSERT INTO inventory_items (name) VALUES
                ('CARTÃO SIM'),
                ('DESKTOP'),
                ('MTR'),
                ('NOTEBOOK'),
                ('PDA'),
                ('PERIFÉRICOS'),
                ('RADIO'),
                ('SMARTPHONE'),
                ('TABLET'),
                ('TV & PROJETOR');
            ");

            // ===============================
            // Tabela: inventory_audits
            // ===============================
            $pdo->exec("
                CREATE TABLE inventory_audits (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,

                    audit_month TEXT NOT NULL,
                    item_id INTEGER NOT NULL,
                    location_id INTEGER NOT NULL,
                    auditor_user_id INTEGER NOT NULL,

                    bdgc_quantity INTEGER,
                    found_quantity INTEGER,
                    divergence_quantity INTEGER,

                    divergence_notes TEXT,
                    ra_ro TEXT,

                    created_via TEXT NOT NULL CHECK (created_via IN ('manual', 'import')),
                    import_batch_id INTEGER,

                    created_at TEXT NOT NULL DEFAULT (datetime('now'))
                );
            ");

            $pdo->exec('PRAGMA foreign_keys = ON;');
        } else {
            throw new RuntimeException("Driver não suportado para esta migration: {$driver}");
        }
    }

    public function down(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = OFF;');

            $pdo->exec("DROP TABLE IF EXISTS inventory_audits;");
            $pdo->exec("DROP TABLE IF EXISTS inventory_items;");

            $pdo->exec('PRAGMA foreign_keys = ON;');
        } else {
            throw new RuntimeException("Driver não suportado para rollback: {$driver}");
        }
    }
}
