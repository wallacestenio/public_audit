<?php
declare(strict_types=1);

class Migration_20260217_160000_unique_ticket_number
{
    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = OFF;');

            // Recria com UNIQUE(ticket_number) e NOT NULL
            $pdo->exec("
CREATE TABLE audit_entries_new (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_number   TEXT NOT NULL UNIQUE,
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
  (id, ticket_number, ticket_type, kyndryl_auditor, petrobras_inspector, audited_supplier,
   location, audit_month, priority, requester_name, category, resolver_group, sla_met, is_compliant,
   created_at, updated_at)
SELECT
  id, ticket_number, ticket_type, kyndryl_auditor, petrobras_inspector, audited_supplier,
  location, audit_month, priority, requester_name, category, resolver_group, sla_met, is_compliant,
  created_at, updated_at
FROM audit_entries;
            ");

            $pdo->exec("DROP TABLE audit_entries;");
            $pdo->exec("ALTER TABLE audit_entries_new RENAME TO audit_entries;");

            $pdo->exec('PRAGMA foreign_keys = ON;');
        } elseif ($driver === 'pgsql') {
            $pdo->exec("ALTER TABLE audit_entries ALTER COLUMN ticket_number SET NOT NULL;");
            $pdo->exec("ALTER TABLE audit_entries ADD CONSTRAINT uq_audit_entries_ticket_number UNIQUE (ticket_number);");
        } elseif ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE audit_entries MODIFY ticket_number VARCHAR(255) NOT NULL;");
            $pdo->exec("ALTER TABLE audit_entries ADD UNIQUE KEY uq_audit_entries_ticket_number (ticket_number);");
        } elseif ($driver === 'sqlsrv') {
            $pdo->exec("ALTER TABLE audit_entries ALTER COLUMN ticket_number NVARCHAR(255) NOT NULL;");
            $pdo->exec("CREATE UNIQUE INDEX uq_audit_entries_ticket_number ON audit_entries(ticket_number);");
        } else {
            throw new \RuntimeException("Unsupported driver: {$driver}");
        }
    }

    public function down(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = OFF;');
            // remove UNIQUE recriando sem a constraint
            $pdo->exec("
CREATE TABLE audit_entries_old (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_number   TEXT,
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
INSERT INTO audit_entries_old
  (id, ticket_number, ticket_type, kyndryl_auditor, petrobras_inspector, audited_supplier,
   location, audit_month, priority, requester_name, category, resolver_group, sla_met, is_compliant,
   created_at, updated_at)
SELECT
  id, ticket_number, ticket_type, kyndryl_auditor, petrobras_inspector, audited_supplier,
  location, audit_month, priority, requester_name, category, resolver_group, sla_met, is_compliant,
  created_at, updated_at
FROM audit_entries;
            ");
            $pdo->exec("DROP TABLE audit_entries;");
            $pdo->exec("ALTER TABLE audit_entries_old RENAME TO audit_entries;");
            $pdo->exec('PRAGMA foreign_keys = ON;');
        } elseif ($driver === 'pgsql') {
            $pdo->exec("ALTER TABLE audit_entries DROP CONSTRAINT IF EXISTS uq_audit_entries_ticket_number;");
            $pdo->exec("ALTER TABLE audit_entries ALTER COLUMN ticket_number DROP NOT NULL;");
        } elseif ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE audit_entries DROP INDEX uq_audit_entries_ticket_number;");
            // MySQL não armazena NOT NULL drop via 'MODIFY'
            $pdo->exec("ALTER TABLE audit_entries MODIFY ticket_number VARCHAR(255) NULL;");
        } elseif ($driver === 'sqlsrv') {
            $pdo->exec("DROP INDEX uq_audit_entries_ticket_number ON audit_entries;");
            $pdo->exec("ALTER TABLE audit_entries ALTER COLUMN ticket_number NVARCHAR(255) NULL;");
        }
    }
}