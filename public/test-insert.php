<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

/**
 * AJUSTE AQUI o caminho ABSOLUTO do seu banco SQLite.
 * Use SEMPRE caminho completo no Windows para evitar abrir outro arquivo.
 */
$dbPath = 'C:\SQLITE\sqlite-tools-win-x64\Csqlite_test\tickets-php\database\tickets.db';

/* ===========================
   Conexão e diagnóstico inicial
   =========================== */
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO INICIAL ===\n";
echo "DB alvo (config): {$dbPath}\n";
echo "file_exists? " . (file_exists($dbPath) ? 'SIM' : 'NÃO') . "\n";
$dir = dirname($dbPath);
echo "Pasta DB: {$dir} | gravável? " . (is_writable($dir) ? 'SIM' : 'NÃO') . "\n\n";

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');
} catch (Throwable $e) {
    http_response_code(500);
    exit("Falha ao conectar no SQLite: " . $e->getMessage() . "\n");
}

$dblist = $pdo->query("PRAGMA database_list;")->fetchAll(PDO::FETCH_ASSOC);
echo "PRAGMA database_list:\n";
foreach ($dblist as $r) {
    echo "- name={$r['name']} file={$r['file']}\n";
}
echo "\n";

/* ===========================
   Mostrar tabelas e schema
   =========================== */
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;")->fetchAll(PDO::FETCH_COLUMN);
echo "Tabelas:\n";
foreach ($tables as $t) echo "- {$t}\n";
echo "\n";

function printTableInfo(PDO $pdo, string $table): void {
    echo "PRAGMA table_info({$table}):\n";
    $cols = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
    if (!$cols) { echo "(sem colunas ou tabela inexistente)\n\n"; return; }
    foreach ($cols as $c) echo "- {$c['cid']}: {$c['name']} ({$c['type']})".($c['notnull']?' NOT NULL':'')."\n";
    echo "\n";
}

printTableInfo($pdo, 'audit_entries');

/* ===========================
   Normalizadores
   =========================== */
function ascii_lower(string $s): string {
    $from = 'ÀÁÂÃÄÅàáâãäåÈÉÊËèéêëÌÍÎÏìíîïÒÓÔÕÖØòóôõöøÙÚÛÜùúûüÇçÑñÝýŸÿŠšŽž';
    $to   = 'AAAAAAaaaaaaEEEEeeeeIIIIiiiiOOOOOOooooooUUUUuuuuCcNnYyYySsZz';
    $s = strtr($s, $from, $to);
    $tmp = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
    if ($tmp !== false) $s = $tmp;
    return strtolower($s);
}
function normalize_audit_month(?string $in): ?string {
    if ($in === null) return null;
    $s = trim((string)$in);
    if ($s === '') return null;

    $s = ascii_lower($s);
    $s = str_replace([',','.'], ' ', $s);
    $s = preg_replace('/\s+de\s+/', ' ', $s);
    $s = preg_replace('/\s+do\s+/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);

    foreach (['segunda','terca','terça','quarta','quinta','sexta','sabado','sábado','domingo'] as $w) {
        if (strpos($s, $w) !== false) return null;
    }

    if (preg_match('/^(\d{4})-(\d{1,2})$/', $s, $m)) {
        $yyyy = (int)$m[1]; $mm = (int)$m[2];
        return ($mm>=1 && $mm<=12) ? sprintf('%04d-%02d', $yyyy, $mm) : null;
    }
    if (preg_match('/^(\d{4})\s+(\d{1,2})$/', $s, $m)) {
        $yyyy = (int)$m[1]; $mm = (int)$m[2];
        return ($mm>=1 && $mm<=12) ? sprintf('%04d-%02d', $yyyy, $mm) : null;
    }
    if (preg_match('/^(\d{1,2})\/(\d{4})$/', $s, $m)) {
        $mm = (int)$m[1]; $yyyy = (int)$m[2];
        return ($mm>=1 && $mm<=12) ? sprintf('%04d-%02d', $yyyy, $mm) : null;
    }
    if (preg_match('/^([a-z]+)[\s\-\/]+(\d{4})$/', $s, $m)) {
        $map = [
            'jan'=>'01','janeiro'=>'01','fev'=>'02','fevereiro'=>'02',
            'mar'=>'03','marco'=>'03','abr'=>'04','abril'=>'04',
            'mai'=>'05','maio'=>'05','jun'=>'06','junho'=>'06',
            'jul'=>'07','julho'=>'07','ago'=>'08','agosto'=>'08',
            'set'=>'09','setembro'=>'09','out'=>'10','outubro'=>'10',
            'nov'=>'11','novembro'=>'11','dez'=>'12','dezembro'=>'12'
        ];
        $nome = $m[1]; $yyyy = (int)$m[2];
        if (isset($map[$nome])) return sprintf('%04d-%s', $yyyy, $map[$nome]);
    }
    $only = [
        'jan'=>'01','janeiro'=>'01','fev'=>'02','fevereiro'=>'02','mar'=>'03','marco'=>'03',
        'abr'=>'04','abril'=>'04','mai'=>'05','maio'=>'05','jun'=>'06','junho'=>'06',
        'jul'=>'07','julho'=>'07','ago'=>'08','agosto'=>'08','set'=>'09','setembro'=>'09',
        'out'=>'10','outubro'=>'10','nov'=>'11','novembro'=>'11','dez'=>'12','dezembro'=>'12'
    ];
    if (isset($only[$s])) {
        $yyyy = (int)date('Y');
        return sprintf('%04d-%s', $yyyy, $only[$s]);
    }
    if (preg_match('/^(\d{1,2})\/(\d{2})$/', $s, $m)) {
        $mm = (int)$m[1]; $yy = (int)$m[2];
        $yyyy = $yy >= 70 ? 1900 + $yy : 2000 + $yy;
        return ($mm>=1 && $mm<=12) ? sprintf('%04d-%02d', $yyyy, $mm) : null;
    }
    if (preg_match('/\b(19|20)\d{2}\b/', $s, $ym) === 1) {
        $yyyy = (int)$ym[0];
        foreach ($only as $nome => $mm) {
            if (strpos($s, $nome) !== false) {
                return sprintf('%04d-%s', $yyyy, $mm);
            }
        }
    }
    return null;
}
function to_bool_text($v): string { return ((int)!!$v) ? '1' : '0'; }

/* ===========================
   Ler POST, mostrar e preparar dados
   =========================== */
$P = $_POST;
echo "POST recebido:\n";
echo print_r($P, true) . "\n";

if (empty($P['ticket_number']) || empty($P['requester_name'])) {
    http_response_code(422);
    exit("ticket_number e requester_name são obrigatórios.\n");
}

$auditMonthRaw = $P['audit_month'] ?? ($P['audit_month_label'] ?? null);
$auditMonth    = normalize_audit_month($auditMonthRaw);
echo "audit_month normalizado => " . var_export($auditMonth, true) . "\n\n";

/* ===========================
   BEFORE: contagem/última linha
   =========================== */
$beforeCount = (int)$pdo->query("SELECT COUNT(*) FROM audit_entries")->fetchColumn();
echo "Linhas em audit_entries (ANTES): {$beforeCount}\n";
$beforeLast = $pdo->query("SELECT id, ticket_number, audit_month, created_at FROM audit_entries ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "Última (antes): " . ($beforeLast ? json_encode($beforeLast, JSON_UNESCAPED_UNICODE) : '(nenhuma)') . "\n\n";

/* ===========================
   Montar payload e INSERT
   =========================== */
$data = [
    'ticket_number'       => (string)($P['ticket_number'] ?? ''),
    'ticket_type'         => isset($P['ticket_type']) ? trim((string)$P['ticket_type']) : null,
    'kyndryl_auditor'     => isset($P['kyndryl_auditor']) ? trim((string)$P['kyndryl_auditor']) : null,
    'petrobras_inspector' => isset($P['petrobras_inspector']) ? trim((string)$P['petrobras_inspector']) : null,
    'audited_supplier'    => isset($P['audited_supplier']) ? trim((string)$P['audited_supplier']) : null,
    'location'            => isset($P['location']) ? trim((string)$P['location']) : null,
    'audit_date'          => isset($P['audit_date']) ? trim((string)$P['audit_date']) : null,
    'audit_month'         => $auditMonth,
    'priority'            => isset($P['priority']) ? trim((string)$P['priority']) : null,
    'requester_name'      => (string)($P['requester_name'] ?? ''),
    'category'            => isset($P['category']) ? trim((string)$P['category']) : null,
    'resolver_group'      => isset($P['resolver_group']) ? trim((string)$P['resolver_group']) : null,
    'sla_met'             => to_bool_text($P['sla_met'] ?? '1'),
    'is_compliant'        => to_bool_text($P['is_compliant'] ?? '1'),
];

echo "Payload para INSERT:\n" . print_r($data, true) . "\n";

$cols = array_keys($data);
$phs  = array_map(fn($c)=>':'.$c, $cols);
$sql  = "INSERT INTO audit_entries (".implode(',', $cols).")
         VALUES (".implode(',', $phs).")";

try {
    $st = $pdo->prepare($sql);
    foreach ($data as $c => $v) $st->bindValue(':'.$c, $v);
    $st->execute();
    $id = (int)$pdo->lastInsertId();
    echo "OK! Inserido id={$id}\n\n";
} catch (Throwable $e) {
    http_response_code(500);
    exit("Erro ao inserir: " . $e->getMessage() . "\n");
}

/* ===========================
   AFTER: contagem/última linha
   =========================== */
$afterCount = (int)$pdo->query("SELECT COUNT(*) FROM audit_entries")->fetchColumn();
$afterLast  = $pdo->query("SELECT id, ticket_number, audit_month, created_at FROM audit_entries ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

echo "Linhas em audit_entries (DEPOIS): {$afterCount}\n";
echo "Última (depois): " . ($afterLast ? json_encode($afterLast, JSON_UNESCAPED_UNICODE) : '(nenhuma)') . "\n";

/* ===========================
   Fim
   =========================== */