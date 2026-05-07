<?php
declare(strict_types=1);

// ================================
// Resolve o root do projeto
// ================================
$projectRoot = dirname(__DIR__);

// Caminhos
$migrationsDir = $projectRoot . '/database/migrations';
$dbPath        = $projectRoot . '/database/tickets.db';

// ================================
// Conexão SQLite
// ================================
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$driver = 'sqlite';

// ================================
// Garante schema_migrations
// ================================
$pdo->exec("
CREATE TABLE IF NOT EXISTS schema_migrations (
  version TEXT PRIMARY KEY,
  applied_at TEXT NOT NULL DEFAULT (datetime('now'))
);
");

// ================================
// CLI
// ================================
$command = $argv[1] ?? null;

switch ($command) {
    case 'up':
        migrateUp($pdo, $driver, $migrationsDir);
        break;

    case 'status':
        migrateStatus($pdo, $migrationsDir);
        break;

    default:
        echo "Uso:\n";
        echo "  php database/migrate.php up\n";
        echo "  php database/migrate.php status\n";
        exit(1);
}

// ================================
// Funções
// ================================
function migrateUp(PDO $pdo, string $driver, string $dir): void
{
    $applied = $pdo->query("
        SELECT version FROM schema_migrations
    ")->fetchAll(PDO::FETCH_COLUMN);

    foreach (getMigrationFiles($dir) as $file => $version) {
        if (in_array($version, $applied, true)) {
            continue;
        }

        require_once $file;
        $class = 'Migration_' . $version;

        echo "Aplicando $version ...\n";

        $migration = new $class();
        $migration->up($pdo, $driver);

        $stmt = $pdo->prepare("
            INSERT INTO schema_migrations (version)
            VALUES (?)
        ");
        $stmt->execute([$version]);

        echo "✔ $version aplicada\n";
    }
}

function migrateStatus(PDO $pdo, string $dir): void
{
    $applied = $pdo->query("
        SELECT version FROM schema_migrations
    ")->fetchAll(PDO::FETCH_COLUMN);

    echo "Migrations:\n";

    foreach (getMigrationFiles($dir) as $file => $version) {
        $status = in_array($version, $applied, true)
            ? 'APLICADA'
            : 'PENDENTE';
        echo "- $version [$status]\n";
    }
}

function getMigrationFiles(string $dir): array
{
    $files = glob($dir . '/*.php');
    $map = [];

    foreach ($files as $file) {
        $name = basename($file, '.php');
        $map[$file] = $name;
    }

    ksort($map);
    return $map;
}