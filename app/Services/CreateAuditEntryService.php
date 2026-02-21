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

        // Mapa de meses PT-BR (curto e longo) -> número
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

        // 1) Já está em YYYY-MM
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $s)) return $s;

        // 2) MM/YYYY
        if (preg_match('/^(0?[1-9]|1[0-2])\s*\/\s*(\d{4})$/', $s, $m)) {
            $mm = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            return "{$m[2]}-{$mm}";
        }

        // 3) “fev 2026” / “fevereiro 2026”
        if (preg_match('/^([a-zçõ]+)\s+(\d{4})$/u', $s, $m)) {
            $mon = $map[$m[1]] ?? null;
            if ($mon) return "{$m[2]}-{$mon}";
        }

        // 4) Apenas mês por nome -> usa ano atual (se não quiser, troque para "return null")
        if (isset($map[$s])) {
            $year = (new \DateTime('now'))->format('Y');
            return "{$year}-{$map[$s]}";
        }

        // 5) Variações tipo "2-2026" / "2 2026"
        if (preg_match('/^([1-9]|1[0-2])\s*(?:-|\.| )\s*(\d{4})$/', $s, $m)) {
            $mm = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            return "{$m[2]}-{$mm}";
        }

        return null;
    }

    /** Parse de IDs de justificativa (semicolon/comma) -> array<int> */
    
private function parseReasonIds(string $idsStr): array
{
    $parts = preg_split('/[;,|\s]+/', $idsStr, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return array_values(array_unique(array_filter(
        array_map(static fn($s) => (int)preg_replace('/\D+/', '', $s), $parts),
        static fn($n) => $n > 0
    )));
}


    /**
     * @return int ID inserido em audit_entries
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    
    

public function handle(array $post): int
{
    // 1) Monta payload base
    $data = [
        'ticket_number'       => trim((string)($post['ticket_number'] ?? '')),
        'ticket_type'         => trim((string)($post['ticket_type'] ?? '')),
        'kyndryl_auditor'     => trim((string)($post['kyndryl_auditor'] ?? '')),
        'petrobras_inspector' => trim((string)($post['petrobras_inspector'] ?? '')),
        'audited_supplier'    => trim((string)($post['audited_supplier'] ?? '')),
        'location'            => trim((string)($post['location'] ?? '')),
        'audit_month'         => $this->normalizeAuditMonth((string)($post['audit_month'] ?? '')),
        'priority'            => (int)($post['priority'] ?? 0),
        'requester_name'      => trim((string)($post['requester_name'] ?? '')),
        'category'            => trim((string)($post['category'] ?? '')),
        'resolver_group'      => trim((string)($post['resolver_group'] ?? '')),
        'sla_met'             => (int)($post['sla_met'] ?? 1),
        'is_compliant'        => (int)($post['is_compliant'] ?? 1),
    ];

    // 2) Parse ÚNICO e robusto dos reasonIds (aceita string/array/fallbacks)
    $reasonIds = [];
    $idsStr = null;

    if (isset($post['noncompliance_reason_ids']) && is_string($post['noncompliance_reason_ids'])) {
        $idsStr = $post['noncompliance_reason_ids'];
    } elseif (isset($post['noncompliance_reason_ids']) && is_array($post['noncompliance_reason_ids'])) {
        $idsStr = implode(';', $post['noncompliance_reason_ids']);
    } elseif (isset($post['nc_ids'])) { // fallback eventual
        $idsStr = (string)$post['nc_ids'];
    } elseif (isset($post['noncompliance_reasons_ids'])) { // typo comum
        $idsStr = (string)$post['noncompliance_reasons_ids'];
    }

    $reasonIds = $this->parseReasonIds((string)$idsStr);

    // 3) Normaliza e valida ticket_type (mantém sua regra)
    $rawType = strtoupper(trim((string)$data['ticket_type']));
    $rawType = strtr($rawType, [
        'Ç'=>'C','Ã'=>'A','Õ'=>'O','Ê'=>'E','É'=>'E','Í'=>'I','Á'=>'A','À'=>'A','Ú'=>'U','Ó'=>'O',
    ]);
    if ($rawType === 'REQUISICAO') { $rawType = 'REQUISIÇÃO'; }

    $allowedUpper = ['INCIDENTE','REQUISIÇÃO','TASK'];
    if (!in_array($rawType, $allowedUpper, true)) {
        throw new \InvalidArgumentException('Tipo do Ticket inválido. Use: Incidente, Requisição ou Task.');
    }
    $mapTitle = [
        'INCIDENTE'  => 'Incidente',
        'REQUISIÇÃO' => 'Requisição',
        'TASK'       => 'Task',
    ];
    $data['ticket_type'] = $mapTitle[$rawType];

    // 4) Validações base (mantidas)
    if ($data['ticket_number'] === '' || !preg_match('/^(INC|RITM|SCTASK)\d{6,}$/', $data['ticket_number'])) {
        throw new \InvalidArgumentException('Informe um Número de Ticket válido (INC/RITM/SCTASK + dígitos).');
    }
    if ($data['audit_month'] === null || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $data['audit_month'])) {
        throw new \InvalidArgumentException('Informe o Mês da Auditoria em um formato válido (ex.: 2026-02, 02/2026, fev 2026).');
    }
    if (!in_array($data['priority'], [1,2,3,4], true)) {
        throw new \InvalidArgumentException('Selecione a Prioridade entre 1 e 4.');
    }
    if (!in_array($data['sla_met'], [0,1], true)) {
        throw new \InvalidArgumentException('Valor inválido para "SLA Atingido?".');
    }
    if (!in_array($data['is_compliant'], [0,1], true)) {
        throw new \InvalidArgumentException('Valor inválido para "Chamado Conforme?".');
    }

    // 5) Regra de negócio: Não conforme → exige ao menos 1 justificativa
    if ((int)$data['is_compliant'] === 0 && empty($reasonIds)) {
        throw new \InvalidArgumentException('Selecione ao menos uma justificativa.');
    }

    // 6) Strings para salvar (IDs sempre; labels opcional)
    $data['noncompliance_reason_ids'] = !empty($reasonIds) ? implode(';', $reasonIds) : null;

    // Labels → opcional; se falhar por qualquer motivo, segue só com IDs (não quebra)
    $data['noncompliance_reasons'] = null;
    if (!empty($reasonIds)) {
        try {
            if (method_exists($this->repo, 'rawPdo')) {
                $pdo = $this->repo->rawPdo();
                $placeholders = implode(',', array_fill(0, count($reasonIds), '?'));
                $stmt = $pdo->prepare("SELECT noncompliance_reason FROM noncompliance_reasons WHERE id IN ($placeholders)");
                $stmt->execute($reasonIds);
                $labels = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                $data['noncompliance_reasons'] = !empty($labels) ? implode(';', $labels) : null;
            }
        } catch (\Throwable $e) {
            // silenciosamente ignora; segue só com IDs
            $data['noncompliance_reasons'] = null;
        }
    }

    // 7) Log de sanidade (opcional)
    (new \App\Support\Logger())->write(
        'debug.log',
        date('c') . ' SERVICE data=' . json_encode($data, JSON_UNESCAPED_UNICODE)
        . ' reasonIds=' . json_encode($reasonIds) . PHP_EOL
    );

    // 8) Delegar ao repositório (preserva sua assinatura atual)
    return $this->repo->create($data, []);
}








// ✅ retorno garantido SEMPRE
//return $this->repo->create($data);
 


  
}

