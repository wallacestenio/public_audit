<?php
declare(strict_types=1);

class Migration_20260217_190000_add_justification_to_audit_entries
{
    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            // SQLite até permite ALTER TABLE ADD COLUMN simples
            $pdo->exec("ALTER TABLE audit_entries ADD COLUMN justification TEXT;");

        } elseif ($driver === 'pgsql') {
            $pdo->exec("ALTER TABLE audit_entries ADD COLUMN justification text NULL;");

        } elseif ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE audit_entries ADD COLUMN justification TEXT NULL;");

        } elseif ($driver === 'sqlsrv') {
            $pdo->exec("ALTER TABLE audit_entries ADD justification NVARCHAR(MAX) NULL;");
        } else {
            throw new \RuntimeException("Unsupported driver: {$driver}");
        }
    }

    public function down(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            // Para remover coluna no SQLite precisaríamos recriar a tabela;
            // como é "down", deixo sem-op por simplicidade (ou implemento recriação se desejar).
            // No seu fluxo, podemos manter noop:
            return;

        } elseif ($driver === 'pgsql') {
            $pdo->exec("ALTER TABLE audit_entries DROP COLUMN IF EXISTS justification;");

        } elseif ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE audit_entries DROP COLUMN justification;");

        } elseif ($driver === 'sqlsrv') {
            $pdo->exec("ALTER TABLE audit_entries DROP COLUMN justification;");
        }
    }
}