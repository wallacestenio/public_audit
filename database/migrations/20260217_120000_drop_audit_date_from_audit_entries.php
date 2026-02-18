<?php
declare(strict_types=1);

class Migration_20260217_120000_drop_audit_date_from_audit_entries
{
    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            // Recria a tabela sem audit_date
            $pdo->exec('PRAGMA foreign_keys = OFF;');

            $pdo->exec("
CREATE TABLE IF NOT EXISTS audit_entries_new (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_number   TEXT NOT NULL,
  ticket_type     TEXT,
  kyndryl_auditor TEXT,
  petrobras_inspector TEXT,
  audited_supplier TEXT,
  location        TEXT,
  audit_month     TEXT,
  priority        TEXT,
  requester_name  TEXT NOT NULL,
  category        TEXT,
  resolver_group  TEXT,
  sla_met         TEXT NOT NULL CHECK (sla_met IN (0,1)),
  is_compliant    TEXT NOT NULL CHECK (is_compliant IN (0,1)),
  created_at      TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at      TEXT
);
            ");

            $pdo->exec("
INSERT INTO audit_entries_new
  (id, ticket_number, ticket_type, kyndryl_auditor, petrobras_inspector, audited_supplier, location,
   audit_month, priority, requester_name, category, resolver_group, sla_met, is_compliant, created_at, updated_at)
SELECT
  id, ticket_number, ticket_type, kyndryl_auditor, petrobras_inspector, audited_supplier, location,
  audit_month, priority, requester_name, category, resolver_group, sla_met, is_compliant, created_at, updated_at
FROM audit_entries;
            ");

            $pdo->exec("DROP TABLE audit_entries;");
            $pdo->exec("ALTER TABLE audit_entries_new RENAME TO audit_entries;");

            $pdo->exec('PRAGMA foreign_keys = ON;');

        } elseif ($driver === 'pgsql') {
            $pdo->exec("ALTER TABLE audit_entries DROP COLUMN IF EXISTS audit_date;");

        } elseif ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE audit_entries DROP COLUMN audit_date;");

        } elseif ($driver === 'sqlsrv') {
            $pdo->exec("ALTER TABLE audit_entries DROP COLUMN audit_date;");

        } else {
            throw new RuntimeException("Unsupported driver: {$driver}");
        }
    }

    public function down(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = OFF;');

            $pdo->exec("
CREATE TABLE IF NOT EXISTS audit_entries_new (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_number   TEXT NOT NULL,
  ticket_type     TEXT,
  kyndryl_auditor TEXT,
  petrobras_inspector TEXT,
  audited_supplier TEXT,
  location        TEXT,
  audit_date      TEXT,
  audit_month     TEXT,
  priority        TEXT,
  requester_name  TEXT NOT NULL,
  category        TEXT,
  resolver_group  TEXT,
  sla_met         TEXT NOT NULL CHECK (sla_met IN (0,1)),
  is_compliant    TEXT NOT NULL CHECK (is_compliant IN (0,1)),
  created_at      TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at      TEXT
);
            ");

            $pdo->exec("
INSERT INTO audit_entries_new
  (id, ticket_number, ticket_type, kyndryl_auditor, petrobras_inspector, audited_supplier, location,
   audit_date, audit_month, priority, requester_name, category, resolver_group, sla_met, is_compliant, created_at, updated_at)
SELECT
  id, ticket_number, ticket_type, kyndryl_auditor, petrobras_inspector, audited_supplier, location,
  NULL AS audit_date, audit_month, priority, requester_name, category, resolver_group, sla_met, is_compliant, created_at, updated_at
FROM audit_entries;
            ");

            $pdo->exec("DROP TABLE audit_entries;");
            $pdo->exec("ALTER TABLE audit_entries_new RENAME TO audit_entries;");

            $pdo->exec('PRAGMA foreign_keys = ON;');

        } elseif ($driver === 'pgsql') {
            $pdo->exec("ALTER TABLE audit_entries ADD COLUMN audit_date DATE NULL;");

        } elseif ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE audit_entries ADD COLUMN audit_date DATE NULL;");

        } elseif ($driver === 'sqlsrv') {
            $pdo->exec("ALTER TABLE audit_entries ADD audit_date DATE NULL;");

        } else {
            throw new RuntimeException("Unsupported driver: {$driver}");
        }
    }
}