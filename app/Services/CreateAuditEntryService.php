<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditEntryRepository;

final class CreateAuditEntryService
{
    public function __construct(
        private AuditEntryRepository $repo
    ) {}

    /** Normaliza entradas de mês para o formato YYYY-MM */
    private function normalizeAuditMonth(?string $input): ?string
    {
        if ($input === null) return null;
        $s = trim(strtolower($input));

        $map = [
            'jan' => '01','janeiro' => '01',
            'fev' => '02','fevereiro' => '02',
            'mar' => '03','março' => '03','marco' => '03',
            'abr' => '04','abril' => '04',
            'mai' => '05','maio' => '05',
            'jun' => '06','junho' => '06',
            'jul' => '07','julho' => '07',
            'ago' => '08','agosto' => '08',
            'set' => '09','setembro' => '09',
            'out' => '10','outubro' => '10',
            'nov' => '11','novembro' => '11',
            'dez' => '12','dezembro' => '12',
        ];

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $s)) return $s;

        if (preg_match('/^(0?[1-9]|1[0-2])\s*\/\s*(\d{4})$/', $s, $m)) {
            $mm = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            return "{$m[2]}-{$mm}";
        }

        if (preg_match('/^([a-zçõ]+)\s+(\d{4})$/u', $s, $m)) {
            $mon = $map[$m[1]] ?? null;
            if ($mon) return "{$m[2]}-{$mon}";
        }

        if (isset($map[$s])) {
            $year = (new \DateTime('now'))->format('Y');
            return "{$year}-{$map[$s]}";
        }

        if (preg_match('/^([1-9]|1[0-2])\s*(?:-|\.| )\s*(\d{4})$/', $s, $m)) {
            $mm = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            return "{$m[2]}-{$mm}";
        }

        return null;
    }

    /** Parse de IDs de justificativa (semicolon/comma/pipe/space) -> array<int> */
    private function parseReasonIds(string $idsStr): array
    {
        $parts = preg_split('/[;,|\s]+/', $idsStr, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_filter(
            array_map(static fn($s) => (int)preg_replace('/\D+/', '', $s), $parts),
            static fn($n) => $n > 0
        )));
    }

    /**
     * Monta e valida os dados, delega a criação ao repositório.
     * Retorna o ID inserido em audit_entries.
     */
    public function handle(array $post): int
{
    // ✅ user_id vindo da sessão (Controller)
    $userId = isset($post['user_id']) ? (int)$post['user_id'] : null;

    if (!$userId) {
        throw new \RuntimeException('Usuário da sessão não identificado.');
    }

    $data = [
        'user_id'             => $userId, // ✅ CAMPO CRÍTICO
        'ticket_number'       => trim((string)($post['ticket_number'] ?? '')),
        'ticket_type'         => trim((string)($post['ticket_type'] ?? '')),
        'kyndryl_auditor'     => trim((string)($post['kyndryl_auditor'] ?? '')),
        'petrobras_inspector' => trim((string)($post['petrobras_inspector'] ?? '')),
        'audited_supplier'    => trim((string)($post['audited_supplier'] ?? '')),
        'location'            => trim((string)($post['location'] ?? '')),
        'audit_month'         => $this->normalizeAuditMonth((string)($post['audit_month'] ?? '')),
        'priority'            => (int)($post['priority'] ?? 0),
        'requester_name'      => null, // não usado
        'category'            => trim((string)($post['category'] ?? '')),
        'resolver_group'      => trim((string)($post['resolver_group'] ?? '')),
        'sla_met'             => (int)($post['sla_met'] ?? 1),
        'is_compliant'        => (int)($post['is_compliant'] ?? 1),
        'noncompliance_reason_ids' => null,
        'noncompliance_reasons'    => null,
    ];

    /* ================= VALIDAÇÕES (mantidas) ================= */

    if ($data['ticket_number'] === '' || !preg_match('/^(INC|RITM|SCTASK)\d{6,}$/', $data['ticket_number'])) {
        throw new \InvalidArgumentException('Informe um Número de Ticket válido.');
    }

    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $data['audit_month'])) {
        throw new \InvalidArgumentException('Mês da Auditoria inválido.');
    }

    if (!in_array($data['priority'], [1,2,3,4,5], true)) {
        throw new \InvalidArgumentException('Prioridade inválida.');
    }

    if (!in_array($data['sla_met'], [0,1], true)) {
        throw new \InvalidArgumentException('Valor inválido para SLA.');
    }

    if (!in_array($data['is_compliant'], [0,1], true)) {
        throw new \InvalidArgumentException('Valor inválido para Conformidade.');
    }

    /* ================= JUSTIFICATIVAS ================= */

    $reasonIds = $this->parseReasonIds((string)($post['noncompliance_reason_ids'] ?? ''));

    if ($data['is_compliant'] === 0) {
        if (empty($reasonIds)) {
            throw new \InvalidArgumentException(
                'Chamado não conforme exige ao menos uma justificativa.'
            );
        }

        $data['noncompliance_reason_ids'] = implode(';', $reasonIds);

        $pdo = $this->repo->rawPdo();
        $placeholders = implode(',', array_fill(0, count($reasonIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT noncompliance_reason FROM noncompliance_reasons WHERE id IN ($placeholders)"
        );
        $stmt->execute($reasonIds);
        $labels = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $data['noncompliance_reasons'] = implode(';', $labels ?: []);
    }

    /* ================= NORMALIZA ticket_type ================= */

    $map = ['INCIDENTE'=>'Incidente','REQUISIÇÃO'=>'Requisição','TASK'=>'Task'];
    if (!isset($map[strtoupper($data['ticket_type'])])) {
        throw new \InvalidArgumentException('Tipo de Ticket inválido.');
    }
    $data['ticket_type'] = $map[strtoupper($data['ticket_type'])];

    /* ✅✅ CHAMADA CORRETA ✅✅ */
    return $this->repo->insertWithReasons($data, $reasonIds);
    }
}