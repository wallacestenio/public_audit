<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Support\Logger;

/**
 * Modelo da tabela audit_entries
 * Campos esperados no payload:
 *  ticket_number, ticket_type, kyndryl_auditor, petrobras_inspector,
 *  audited_supplier, location, audit_date, audit_month, priority,
 *  requester_name, category, resolver_group, sla_met, is_compliant
 */
final class AuditEntry extends BaseModel
{
    /**
     * Insere um registro na audit_entries.
     * - Aceita payload com nomes EXATOS das colunas.
     * - Preenche audit_month a partir do audit_date (YYYY-MM) se vier nulo.
     * - Faz bind de NULL quando o valor está vazio.
     * - Loga o payload e o SQL (em storage/debug.log) para depuração.
     */

    public function insertWithReasons(array $data, array $reasonTokens): int
{
    $allowed = [
      'ticket_number','ticket_type','kyndryl_auditor','petrobras_inspector','audited_supplier',
      'location','audit_month','priority','requester_name','category','resolver_group',
      'sla_met','is_compliant'
    ];
    $data = array_intersect_key($data, array_flip($allowed));

    $cols = array_keys($data);
    $phs  = array_map(fn($c)=>':'.$c, $cols);
    $sql  = "INSERT INTO audit_entries (".implode(',', $cols).") VALUES (".implode(',', $phs).")";

    $this->pdo->beginTransaction();
    try {
        $st = $this->pdo->prepare($sql);
        foreach ($data as $c => $v) {
            $st->bindValue(':'.$c, $v === '' ? null : $v);
        }
        $st->execute();

        // Chave da linha (PK): se sua PK é ticket_number, recupere do $data. Se for id autoinc, use lastInsertId
        $entryPK = $data['ticket_number'] ?? (string)$this->pdo->lastInsertId();

        // Ponte (se houver tokens)
        if (!empty($reasonTokens) && $this->tableExists('audit_entry_noncompliance_reasons')) {
            $usesId   = $this->columnExists('audit_entry_noncompliance_reasons','noncompliance_reason_id');
            $usesCode = $this->columnExists('audit_entry_noncompliance_reasons','noncompliance_reason_code');

            if ($usesId) {
                $ids = $this->resolveReasonIds($reasonTokens);
                $ins = $this->pdo->prepare(
                  // ajuste a FK: se usou ticket_number como PK, troque a coluna da ponte também
                  "INSERT OR IGNORE INTO audit_entry_noncompliance_reasons
                   (audit_entry_id, noncompliance_reason_id) VALUES (:eid, :rid)"
                );
                foreach ($ids as $rid) $ins->execute([':eid'=>$entryPK, ':rid'=>$rid]);
            } elseif ($usesCode) {
                $ins = $this->pdo->prepare(
                  "INSERT OR IGNORE INTO audit_entry_noncompliance_reasons
                   (audit_entry_id, noncompliance_reason_code) VALUES (:eid, :code)"
                );
                foreach ($reasonTokens as $code) {
                    $code = trim((string)$code);
                    if ($code !== '') $ins->execute([':eid'=>$entryPK, ':code'=>$code]);
                }
            }
        }

        $this->pdo->commit();
        return is_numeric($entryPK) ? (int)$entryPK : 1; // retorno simbólico quando PK é textual
    } catch (\Throwable $e) {
        $this->pdo->rollBack();
        throw $e;
    }
}

// Helpers resolveReasonIds(), tableExists(), columnExists() iguais aos que você já tem
    public function existsTicketNumber(string $ticket): bool
    {
    $t = strtoupper(trim(preg_replace('/\s+/', ' ', $ticket)));
    $sql = "SELECT 1 FROM audit_entries WHERE TRIM(UPPER(ticket_number)) = :t LIMIT 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':t', $t, \PDO::PARAM_STR);
    $stmt->execute();
    return (bool)$stmt->fetchColumn();
    }

    public function insertSimple(array $data, array $reasonTokens = []): int
    {
        $logger = new Logger();

        // ✅ Fallback: se não vier audit_month mas vier audit_date (YYYY-MM-DD), derive o mês
        if (
            (!isset($data['audit_month']) || $data['audit_month'] === null || $data['audit_month'] === '')
            && !empty($data['audit_date'])
        ) {
            $ad = trim((string)$data['audit_date']);
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ad, $m)) {
                $data['audit_month'] = sprintf('%s-%s', $m[1], $m[2]); // YYYY-MM
            }
        }

        // ✅ Só mantenha as colunas que existem na tabela
        $allowed = [
  'ticket_number','ticket_type','kyndryl_auditor','petrobras_inspector','audited_supplier',
  'location','audit_month','priority','requester_name','category','resolver_group','sla_met','is_compliant'
];
$data = array_intersect_key($data, array_flip($allowed));

        // 🔎 Log do payload
        $logger->write('debug.log', date('c')." [AuditEntry.insertSimple] DATA:\n".print_r($data,true)."\n");

        // Garantia: não inserir vazio
        $cols = array_keys($data);
        if (empty($cols)) {
            $logger->write('debug.log', date('c')." [AuditEntry.insertSimple] ERRO: payload vazio\n");
            throw new \InvalidArgumentException('Payload vazio para insert.');
        }

        // Monta SQL dinâmico
        $phs  = array_map(fn($c)=>':'.$c, $cols);
        $sql  = "INSERT INTO audit_entries (".implode(',', $cols).")
                 VALUES (".implode(',', $phs).")";
        $logger->write('debug.log', date('c')." [AuditEntry.insertSimple] SQL: {$sql}\n");

        // Executa
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare($sql);
            foreach ($data as $c => $v) {
                if ($v === null || $v === '') {
                    $st->bindValue(':'.$c, null, PDO::PARAM_NULL);
                } else {
                    $st->bindValue(':'.$c, $v);
                }
            }
            $st->execute();

            $id = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();

            $logger->write('debug.log', date('c')." [AuditEntry.insertSimple] OK id={$id}\n");

            return $id;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $logger->write('debug.log', date('c')." [AuditEntry.insertSimple] FAIL: ".$e->getMessage()."\n");
            throw $e;
        }
    }

    /** Lista para exportação (sem joins) */
    public function listForExport(array $filters = []): array
{
    // Descobre colunas existentes na tabela (após remoções de created_at/updated_at)
    $colsStmt = $this->pdo->query("PRAGMA table_info(audit_entries)");
    $colsInfo = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $colNames = array_map(fn($c) => (string)$c['name'], $colsInfo);

    if (empty($colNames)) {
        return [];
    }

    // Monta SELECT explícito com as colunas existentes
    $selectCols = implode(',', array_map(fn($c) => $c, $colNames));

    $sql    = "SELECT {$selectCols} FROM audit_entries";
    $where  = [];
    $params = [];

    // Filtro opcional por mês (compatível com o botão "Exportar CSV (mês)")
    if (!empty($filters['audit_month'])) {
        $where[] = "audit_month = :m";
        $params[':m'] = $filters['audit_month'];
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    // Ordenação: se tiver 'created_at' usamos, senão tentamos 'id', senão 'ticket_number', senão 'rowid'
    if (in_array('created_at', $colNames, true)) {
        $sql .= ' ORDER BY created_at DESC';
    } elseif (in_array('id', $colNames, true)) {
        $sql .= ' ORDER BY id DESC';
    } elseif (in_array('ticket_number', $colNames, true)) {
        $sql .= ' ORDER BY ticket_number DESC';
    } else {
        $sql .= ' ORDER BY rowid DESC';
    }

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

    /** Ponte de justificativas (se aplicável; aqui deixo compatível com seus nomes) */
   public function listReasonsBridge(): array
{
    // Se não existir a tabela-ponte, retorna vazio
    if (!$this->tableExists('audit_entry_noncompliance_reasons')) {
        return [];
    }

    $usesId   = $this->columnExists('audit_entry_noncompliance_reasons','noncompliance_reason_id');
    $usesCode = $this->columnExists('audit_entry_noncompliance_reasons','noncompliance_reason_code');

    // Descobre o nome da coluna de "nome" na tabela de catálogo
    $reasonNameCol = $this->detectReasonNameColumn(); // retorna 'noncompliance_reason' (ou outro)
    if ($usesId) {
        // JOIN na tabela de catálogo, usando o nome detectado
        $sql = "SELECT a.audit_entry_id,
                       a.noncompliance_reason_id,
                       n.{$reasonNameCol} AS noncompliance_reason
                FROM audit_entry_noncompliance_reasons a
                JOIN noncompliance_reasons n ON n.id = a.noncompliance_reason_id
                ORDER BY a.audit_entry_id";
    } elseif ($usesCode) {
        $sql = "SELECT a.audit_entry_id,
                       a.noncompliance_reason_code AS noncompliance_reason
                FROM audit_entry_noncompliance_reasons a
                ORDER BY a.audit_entry_id";
    } else {
        // nenhuma das colunas esperadas existe — retorna vazio
        return [];
    }

    $stmt = $this->pdo->query($sql);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] : [];
}

/** Helpers já existentes (ajuste/adicione se necessário) */
private function tableExists(string $table): bool
{
    $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:t LIMIT 1");
    $stmt->execute([':t'=>$table]);
    return (bool)$stmt->fetchColumn();
}

private function columnExists(string $table, string $column): bool
{
    $stmt = $this->pdo->query("PRAGMA table_info($table)");
    $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($cols as $c) {
        if (strcasecmp((string)$c['name'], $column) === 0) return true;
    }
    return false;
}

/**
 * Detecta qual coluna “nome” existe em noncompliance_reasons.
 * Tenta em ordem: noncompliance_reason, reason, name, label.
 */
private function detectReasonNameColumn(): string
{
    $candidates = ['noncompliance_reason','reason','name','label'];
    $stmt = $this->pdo->query("PRAGMA table_info(noncompliance_reasons)");
    $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $names = array_map(fn($r) => (string)$r['name'], $cols);
    foreach ($candidates as $c) {
        foreach ($names as $n) {
            if (strcasecmp($n, $c) === 0) return $n; // retorna com o “case” real
        }
    }
    // fallback: se nada encontrado, retorna a mais provável
    return 'noncompliance_reason';
    }
}