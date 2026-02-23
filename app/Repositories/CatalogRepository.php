<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CatalogRepository
{
    public function __construct(private PDO $pdo) {}

    private array $tableColumnsCache = [];

    /** Descobre colunas de uma tabela (cache) */
    private function columnsOf(string $table): array
    {
        if (isset($this->tableColumnsCache[$table])) {
            return $this->tableColumnsCache[$table];
        }
        $cols = [];
        try {
            $stmt = $this->pdo->query('PRAGMA table_info('.$table.');');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = $row['name'] ?? null;
                if ($name !== null) $cols[$name] = true;
            }
        } catch (\Throwable $e) {
            // ignorar
        }
        return $this->tableColumnsCache[$table] = $cols;
    }

    /** "AND LOWER(col) LIKE LOWER(?)" por palavra */
    private function buildAndLikes(string $column, string $q, array &$params): string
    {
        $q = trim($q);
        if ($q === '') return '1=1';

        $words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $likes = [];
        foreach ($words as $w) {
            $likes[] = "LOWER($column) LIKE LOWER(?)";
            $params[] = '%' . str_replace(['%','_'], ['\\%','\\_'], $w) . '%';
        }
        return $likes ? implode(' AND ', $likes) : '1=1';
    }

    /** Executa SQL (com &_debug=1 retorna erro no payload) */
    private function run(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            if (class_exists(\App\Support\Logger::class)) {
                (new \App\Support\Logger())->write(
                    'debug.log',
                    date('c') . ' REPO.run SQLERR: ' . $e->getMessage()
                    . ' | SQL=' . $sql
                    . ' | params=' . json_encode($params, JSON_UNESCAPED_UNICODE) . PHP_EOL
                );
            }
            if (!empty($_GET['_debug'])) {
                return [['_error' => $e->getMessage(), '_sql' => $sql]];
            }
            return [];
        }
    }

    /* ================= Noncompliance Reasons ================= */
    public function listNoncomplianceReasons(string $q = ''): array
    {
        $table = 'noncompliance_reasons';
        $cols  = $this->columnsOf($table);

        $textExpr = null;
        if (isset($cols['label']) && isset($cols['noncompliance_reason'])) {
            $textExpr = 'COALESCE(label, noncompliance_reason)';
        } elseif (isset($cols['label'])) {
            $textExpr = 'label';
        } elseif (isset($cols['noncompliance_reason'])) {
            $textExpr = 'noncompliance_reason';
        } else {
            return [];
        }

        $groupExpr = isset($cols['group']) ? '"group"' : "'Outros'";

        $params = [];
        $where  = $this->buildAndLikes($textExpr, $q, $params);

        $sql = "
            SELECT
                id,
                {$textExpr} AS label,
                {$groupExpr} AS \"group\"
            FROM {$table}
            WHERE {$where}
            ORDER BY label ASC
            LIMIT 20
        ";

        return $this->run($sql, $params);
    }

    /* ================= Kyndryl Auditors ================= */
    public function listKyndrylAuditors(string $q = ''): array
    {
        $params = [];
        $where  = $this->buildAndLikes('kyndryl_auditor', $q, $params);
        $sql = "
            SELECT id, kyndryl_auditor AS name
            FROM kyndryl_auditors
            WHERE {$where}
            ORDER BY kyndryl_auditor ASC
            LIMIT 20
        ";
        return $this->run($sql, $params);
    }

    /* ================= Petrobras Inspectors ================= */
    public function listPetrobrasInspectors(string $q = ''): array
    {
        $params = [];
        $where  = $this->buildAndLikes('petrobras_inspector', $q, $params);
        $sql = "
            SELECT id, petrobras_inspector AS name
            FROM petrobras_inspectors
            WHERE {$where}
            ORDER BY petrobras_inspector ASC
            LIMIT 20
        ";
        return $this->run($sql, $params);
    }

    /* ================= Audited Suppliers ================= */
    public function listAuditedSuppliers(string $q = ''): array
    {
        $params = [];
        $where  = $this->buildAndLikes('audited_supplier', $q, $params);
        $sql = "
            SELECT id, audited_supplier AS name
            FROM audited_suppliers
            WHERE {$where}
            ORDER BY audited_supplier ASC
            LIMIT 20
        ";
        return $this->run($sql, $params);
    }

    /* ================= Locations ================= */
    public function listLocations(string $q = ''): array
    {
        $params = [];
        $where  = $this->buildAndLikes('location', $q, $params);
        $sql = "
            SELECT id, location AS name
            FROM locations
            WHERE {$where}
            ORDER BY location ASC
            LIMIT 20
        ";
        return $this->run($sql, $params);
    }

    /* ================= Categories ================= */
    public function listCategories(string $q = ''): array
    {
        $params = [];
        $where  = $this->buildAndLikes('category', $q, $params);
        $sql = "
            SELECT id, category AS name
            FROM categories
            WHERE {$where}
            ORDER BY category ASC
            LIMIT 20
        ";
        return $this->run($sql, $params);
    }

    /* ================= Resolver Groups ================= */
    public function listResolverGroups(string $q = ''): array
    {
        $params = [];
        $where  = $this->buildAndLikes('resolver_group', $q, $params);
        $sql = "
            SELECT id, resolver_group AS name
            FROM resolver_groups
            WHERE {$where}
            ORDER BY resolver_group ASC
            LIMIT 20
        ";
        return $this->run($sql, $params);
    }
}