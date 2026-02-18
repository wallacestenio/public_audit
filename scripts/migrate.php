<?php
declare(strict_types=1);

// ==== CONFIGURE AQUI SE PRECISAR ====
$dbPath = 'C:\SQLITE\sqlite-tools-win-x64\Csqlite_test\tickets-php\database\tickets.db';
$migrationsDir = __DIR__ . '/../database/migrations';
// ====================================

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Conecta PDO (SQLite por padrão; depois troque DSN e credenciais p/ outros SGBDs)
try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME); // 'sqlite', 'mysql', 'pgsql', 'sqlsrv'
} catch (Throwable $e) {
    fwrite(STDERR, "Failed to connect: {$e->getMessage()}\n");
    exit(1);
}

// Garante tabela de controle
$nowExpr = match($driver) {
    'sqlite' => "(datetime('now'))",
    'pgsql'  => "CURRENT_TIMESTAMP",
    'mysql'  => "CURRENT_TIMESTAMP",
    'sqlsrv' => "CURRENT_TIMESTAMP",
    default  => "CURRENT_TIMESTAMP",
};
$pdo->exec("
CREATE TABLE IF NOT EXISTS schema_migrations (
  version    TEXT PRIMARY KEY,
  applied_at TEXT NOT NULL DEFAULT {$nowExpr}
);
");

// Utilidades
function getAppliedVersions(PDO $pdo): array {
    $stmt = $pdo->query("SELECT version FROM schema_migrations ORDER BY version");
    return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
}
function setApplied(PDO $pdo, string $version): void {
    $stmt = $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (:v)");
    $stmt->execute([':v'=>$version]);
}
function unsetApplied(PDO $pdo, string $version): void {
    $stmt = $pdo->prepare("DELETE FROM schema_migrations WHERE version = :v");
    $stmt->execute([':v'=>$version]);
}
function loadMigrations(string $dir): array {
    $files = glob($dir . '/*.php');
    sort($files);
    return $files ?: [];
}
function versionFromFile(string $file): string {
    return basename($file, '.php'); // ex.: 20260217_120000_drop_audit_date_from_audit_entries
}
function classNameFromVersion(string $version): string {
    // vira Migration_20260217_120000_drop_audit_date_from_audit_entries (sem traços)
    return 'Migration_' . preg_replace('/[^0-9A-Za-z_]/','_', $version);
}

// Comando
$cmd = $argv[1] ?? 'status';
$target = $argv[2] ?? null; // p/ down(uma versão) ou to(versão)

$files = loadMigrations($migrationsDir);
$applied = getAppliedVersions($pdo);

switch ($cmd) {

    case 'status':
        echo "Driver: {$driver}\n";
        echo "Migrations dir: {$migrationsDir}\n\n";
        $allVers = array_map('versionFromFile', $files);
        echo "Applied:\n";
        foreach ($applied as $v) echo "  [x] {$v}\n";
        echo "\nPending:\n";
        foreach ($allVers as $v) if (!in_array($v, $applied, true)) echo "  [ ] {$v}\n";
        echo "\n";
        break;

    case 'up':
        $ran = 0;
        foreach ($files as $file) {
            $version = versionFromFile($file);
            if (in_array($version, $applied, true)) continue;

            require_once $file;
            $class = classNameFromVersion($version);
            if (!class_exists($class)) {
                fwrite(STDERR, "Class {$class} not found in {$file}\n");
                exit(1);
            }
            $migration = new $class();

            echo "Applying {$version} ... ";
            try {
                // cuidado com DDL + transações (nem todo SGBD suporta totalmente)
                $migration->up($pdo, $driver);
                setApplied($pdo, $version);
                echo "OK\n";
                $ran++;
            } catch (Throwable $e) {
                echo "FAIL: ".$e->getMessage()."\n";
                exit(1);
            }
        }
        echo $ran ? "Applied {$ran} migration(s).\n" : "Nothing to do.\n";
        break;

    case 'down':
        // down de UMA versão específica ou a última aplicada
        $version = $target;
        if (!$version) {
            $version = end($applied) ?: null;
            if (!$version) { echo "No applied migrations.\n"; exit(0); }
        }
        $file = $migrationsDir . '/' . $version . '.php';
        if (!is_file($file)) {
            fwrite(STDERR, "Migration file not found: {$file}\n");
            exit(1);
        }
        require_once $file;
        $class = classNameFromVersion($version);
        if (!class_exists($class)) {
            fwrite(STDERR, "Class {$class} not found in {$file}\n");
            exit(1);
        }
        $migration = new $class();
        echo "Reverting {$version} ... ";
        try {
            $migration->down($pdo, $driver);
            unsetApplied($pdo, $version);
            echo "OK\n";
        } catch (Throwable $e) {
            echo "FAIL: ".$e->getMessage()."\n";
            exit(1);
        }
        break;

    case 'redo':
        // down + up da última
        $last = end($applied) ?: null;
        if (!$last) { echo "No applied migrations.\n"; exit(0); }
        // down
        $argv[1] = 'down'; $argv[2] = $last;
        passthru(PHP_BINARY . ' ' . __FILE__ . ' down ' . escapeshellarg($last), $rc);
        if ($rc !== 0) exit($rc);
        // up
        $argv[1] = 'up'; unset($argv[2]);
        passthru(PHP_BINARY . ' ' . __FILE__ . ' up', $rc2);
        exit($rc2);

    default:
        echo "Usage:\n";
        echo "  php scripts/migrate.php status\n";
        echo "  php scripts/migrate.php up\n";
        echo "  php scripts/migrate.php down            # reverte a última\n";
        echo "  php scripts/migrate.php down VERSION    # reverte uma versão específica\n";
        echo "  php scripts/migrate.php redo            # reverte e reaplica a última\n";
        exit(0);
}