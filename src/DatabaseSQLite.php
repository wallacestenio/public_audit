<?php
declare(strict_types=1);

namespace Src;

use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct(string $sqlitePath, string $schemaSqlPath)
    {
        $needInit = !is_file($sqlitePath);
        $this->pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Sempre ligar FK
        $this->pdo->exec('PRAGMA foreign_keys = ON;');

        if ($needInit && is_file($schemaSqlPath)) {
            $sql = file_get_contents($schemaSqlPath) ?: '';
            if ($sql !== '') $this->pdo->exec($sql);
        }
    }

    public function pdo(): PDO { return $this->pdo; }
}