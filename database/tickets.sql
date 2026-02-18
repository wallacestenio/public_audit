PRAGMA foreign_keys = ON;

-- PRESETS
CREATE TABLE IF NOT EXISTS ticket_types (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_type TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT
);
CREATE TABLE IF NOT EXISTS kyndryl_auditors (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kyndryl_auditor TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT
);
CREATE TABLE IF NOT EXISTS petrobras_inspectors (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  petrobras_inspector TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT
);
CREATE TABLE IF NOT EXISTS audited_suppliers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  audited_supplier TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT
);
CREATE TABLE IF NOT EXISTS locations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  location TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT
);
CREATE TABLE IF NOT EXISTS priorities (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  priority TEXT NOT NULL UNIQUE, -- '1','2','3','4'
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT
);
CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  category TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT
);
CREATE TABLE IF NOT EXISTS resolver_groups (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  resolver_group TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT
);
CREATE TABLE IF NOT EXISTS noncompliance_reasons (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  noncompliance_reason TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT
);

-- FATO
CREATE TABLE IF NOT EXISTS audit_entries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_number TEXT NOT NULL,
  ticket_type_id INTEGER,
  kyndryl_auditor_id INTEGER,
  petrobras_inspector_id INTEGER,
  audited_supplier_id INTEGER,
  location_id INTEGER,
  audit_date TEXT,                 -- ISO YYYY-MM-DD
  priority_id INTEGER,
  requester_name TEXT NOT NULL,
  category_id INTEGER,
  resolver_group_id INTEGER,
  sla_met INTEGER NOT NULL CHECK (sla_met IN (0,1)),
  is_compliant INTEGER NOT NULL CHECK (is_compliant IN (0,1)),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT,
  FOREIGN KEY (ticket_type_id) REFERENCES ticket_types(id),
  FOREIGN KEY (kyndryl_auditor_id) REFERENCES kyndryl_auditors(id),
  FOREIGN KEY (petrobras_inspector_id) REFERENCES petrobras_inspectors(id),
  FOREIGN KEY (audited_supplier_id) REFERENCES audited_suppliers(id),
  FOREIGN KEY (location_id) REFERENCES locations(id),
  FOREIGN KEY (priority_id) REFERENCES priorities(id),
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (resolver_group_id) REFERENCES resolver_groups(id)
);

-- PONTE (multi-tags justificativa)
CREATE TABLE IF NOT EXISTS audit_entry_noncompliance_reasons (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  audit_entry_id INTEGER NOT NULL,
  noncompliance_reason_id INTEGER NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE (audit_entry_id, noncompliance_reason_id),
  FOREIGN KEY (audit_entry_id) REFERENCES audit_entries(id) ON DELETE CASCADE,
  FOREIGN KEY (noncompliance_reason_id) REFERENCES noncompliance_reasons(id)
);

-- ÍNDICES úteis
CREATE INDEX IF NOT EXISTS ix_entries_ticket_number  ON audit_entries (ticket_number);
CREATE INDEX IF NOT EXISTS ix_entries_audit_date     ON audit_entries (audit_date);
CREATE INDEX IF NOT EXISTS ix_entries_is_compliant   ON audit_entries (is_compliant);
CREATE INDEX IF NOT EXISTS ix_entries_priority       ON audit_entries (priority_id);
CREATE INDEX IF NOT EXISTS ix_entries_category       ON audit_entries (category_id);
CREATE INDEX IF NOT EXISTS ix_entries_location       ON audit_entries (location_id);
CREATE INDEX IF NOT EXISTS ix_entries_created_at     ON audit_entries (created_at);

-- SEEDS
INSERT OR IGNORE INTO ticket_types (ticket_type) VALUES ('RITM'),('INC'),('TASK'),('SCTASK');
INSERT OR IGNORE INTO priorities (priority) VALUES ('1'),('2'),('3'),('4');