<?php
namespace Src;

use PDO;
use RuntimeException;

class Database
{
    private PDO $pdo;

    public function __construct(private string $sqlitePath, private string $schemaSqlPath)
    {
        $dir = dirname($this->sqlitePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException("Cannot create dir: $dir");
            }
        }

        $this->pdo = new PDO('sqlite:' . $this->sqlitePath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');

        // Se o DB está vazio, aplica o schema
        if (!$this->hasAnyTable() && file_exists($this->schemaSqlPath)) {
            $sql = file_get_contents($this->schemaSqlPath);
            $this->pdo->exec($sql);
        }
    }

    private function hasAnyTable(): bool
    {
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' LIMIT 1;");
        return (bool) $stmt->fetchColumn();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}