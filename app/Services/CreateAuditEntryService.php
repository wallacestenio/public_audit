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

    
// --- SEMPRE inicialize o array:
// --- GARANTIR reasonIds como array<int> independente do formato vindo do POST
$reasonIds = [];

// 1) Aceita "noncompliance_reason_ids" como string "10;5;1" (padrão)
$idsStr = null;
if (isset($post['noncompliance_reason_ids']) && is_string($post['noncompliance_reason_ids'])) {
    $idsStr = $post['noncompliance_reason_ids'];
}

// 2) Aceita "noncompliance_reason_ids[]" como array (caso o browser envie assim)
if ($idsStr === null && isset($post['noncompliance_reason_ids']) && is_array($post['noncompliance_reason_ids'])) {
    $idsStr = implode(';', $post['noncompliance_reason_ids']);
}

// 3) Fallbacks (se alguém mudou o nome do campo no HTML sem querer)
if ($idsStr === null && isset($post['nc_ids'])) { // não recomendado, mas cobre acidente
    $idsStr = (string)$post['nc_ids'];
}
if ($idsStr === null && isset($post['noncompliance_reasons_ids'])) { // typo comum
    $idsStr = (string)$post['noncompliance_reasons_ids'];
}

// 4) Parse robusto (aceita ; , | espaço e limpa sujeira)
$reasonIds = $this->parseReasonIds((string)$idsStr);

// 5) Regra: Não conforme exige ao menos 1 justificativa
if ((int)$data['is_compliant'] === 0 && empty($reasonIds)) {
    throw new \InvalidArgumentException('Selecione ao menos uma justificativa.');
}


if ((int)$data['is_compliant'] === 0 && empty($reasonIds)) {
    throw new \InvalidArgumentException('Selecione ao menos uma justificativa.');
}

// Pega do POST, parseia para array<int> (aceita ; , | espaço)
$idsStr = (string)($post['noncompliance_reason_ids'] ?? '');
$reasonIds = $this->parseReasonIds($idsStr); // garante array<int>


    
// Depois de parsear $reasonIds (array<int>):
$data['noncompliance_reason_ids'] = implode(';', $reasonIds);

// Opcional: também salvar os labels (recomendado para CSV autoexplicativo)
if (!empty($reasonIds)) {
    $placeholders = implode(',', array_fill(0, count($reasonIds), '?'));
    $pdo = $this->repo->rawPdo(); // crie um getter simples no repo para expor o PDO
    $stmt = $pdo->prepare("SELECT noncompliance_reason FROM noncompliance_reasons WHERE id IN ($placeholders)");
    $stmt->execute($reasonIds);
    $labels = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    $data['noncompliance_reasons'] = implode(';', $labels);
} else {
    $data['noncompliance_reasons'] = null;
}

    
    // --- Normaliza ticket_type ---
// 1) converte para maiúsculas e trata variações sem acento
$rawType = strtoupper(trim((string)$data['ticket_type']));
$rawType = strtr($rawType, [
    'Ç' => 'C',
    'Ã' => 'A',
    'Õ' => 'O',
    'Ê' => 'E',
    'É' => 'E',
    'Í' => 'I',
    'Á' => 'A',
    'À' => 'A',
    'Ú' => 'U',
    'Ó' => 'O',
]);
// aceita com e sem acento
if ($rawType === 'REQUISICAO') { $rawType = 'REQUISIÇÃO'; }

// 2) valida contra os 3 tipos suportados
$allowedUpper = ['INCIDENTE','REQUISIÇÃO','TASK'];
if (!in_array($rawType, $allowedUpper, true)) {
    throw new \InvalidArgumentException('Tipo do Ticket inválido. Use: Incidente, Requisição ou Task.');
}

// 3) mapeia para Title Case (o formato que o CHECK do banco costuma exigir)
$mapTitle = [
    'INCIDENTE'  => 'Incidente',
    'REQUISIÇÃO' => 'Requisição',
    'TASK'       => 'Task',
];
$data['ticket_type'] = $mapTitle[$rawType];
    
// Normaliza ticket_type para Title Case esperado pelo banco
$mapType = [
    'INCIDENTE'   => 'Incidente',
    'REQUISIÇÃO'  => 'Requisição',
    'TASK'        => 'Task',
];
$data['ticket_type'] = $mapType[strtoupper($data['ticket_type'])] ?? $data['ticket_type'];


    if ($data['ticket_number'] === '' || !preg_match('/^(INC|RITM|SCTASK)\d{6,}$/', $data['ticket_number'])) {
        throw new \InvalidArgumentException('Informe um Número de Ticket válido (INC/RITM/SCTASK + dígitos).');
    }
    if ($data['audit_month'] === null || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $data['audit_month'])) {
        throw new \InvalidArgumentException('Informe o Mês da Auditoria em um formato válido (ex.: 2026-02, 02/2026, fev 2026).');
    }

    $idsStr    = (string)($post['noncompliance_reason_ids'] ?? '');
    $reasonIds = $this->parseReasonIds($idsStr);

    
    
if (!in_array($data['priority'], [1,2,3,4], true)) {
    throw new \InvalidArgumentException('Selecione a Prioridade entre 1 e 4.');
}

// ticket_type coerente (se quiser reforçar)
/*if (!in_array($data['ticket_type'], ['INCIDENTE','REQUISIÇÃO','TASK'], true)) {
    throw new \InvalidArgumentException('Tipo do Ticket inválido. Use: Incidente, Requisição ou Task.');
}*/

// flags binárias (sanidade)
if (!in_array($data['sla_met'], [0,1], true)) {
    throw new \InvalidArgumentException('Valor inválido para "SLA Atingido?".');
}
if (!in_array($data['is_compliant'], [0,1], true)) {
    throw new \InvalidArgumentException('Valor inválido para "Chamado Conforme?".');
}


// Sempre array -> string "10;5;1;3" (ou null se vazio)
$data['noncompliance_reason_ids'] = !empty($reasonIds) ? implode(';', $reasonIds) : null;

// (Opcional, mas recomendado) também salvar os labels para facilitar CSV
$data['noncompliance_reasons'] = null;
/*if (!empty($reasonIds)) {
    try {
        // Exige que o repo expose o PDO (veja helper abaixo)
        $pdo = $this->repo->rawPdo();
        $placeholders = implode(',', array_fill(0, count($reasonIds), '?'));
        $stmt = $pdo->prepare("SELECT noncompliance_reason FROM noncompliance_reasons WHERE id IN ($placeholders)");
        $stmt->execute($reasonIds);
        $labels = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        // OBS: a ordem do SELECT pode não bater 1:1 com a ordem dos IDs;
        // se quiser manter ordem dos IDs, reordene as labels usando um map id->label.
        $data['noncompliance_reasons'] = !empty($labels) ? implode(';', $labels) : null;
    } catch (\Throwable $e) {
        // Se der qualquer erro ao buscar labels, segue só com IDs
        $data['noncompliance_reasons'] = null;
    }*/
    
        // LOG de sanidade: ver o que está indo pro repo (sem depender de $this->logger)

// strings para salvar
$data['noncompliance_reason_ids'] = !empty($reasonIds) ? implode(';', $reasonIds) : null;

// (opcional) labels: pode comentar se não usar agora
$data['noncompliance_reasons'] = null;
if (!empty($reasonIds)) {
    $pdo = $this->repo->rawPdo();
    $placeholders = implode(',', array_fill(0, count($reasonIds), '?'));
    $stmt = $pdo->prepare("SELECT noncompliance_reason FROM noncompliance_reasons WHERE id IN ($placeholders)");
    $stmt->execute($reasonIds);
    $labels = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    $data['noncompliance_reasons'] = !empty($labels) ? implode(';', $labels) : null;
}
// (opcional) log
(new \App\Support\Logger())->write('debug.log',
    date('c') . ' SERVICE data=' . json_encode($data, JSON_UNESCAPED_UNICODE)
    . ' reasonIds=' . json_encode($reasonIds) . PHP_EOL
);

  // 5) Delegar ao repositório
    return $this->repo->create($data, []); // ou só $data, se você já ajustou a assinatura

}








// ✅ retorno garantido SEMPRE
//return $this->repo->create($data);
 


  
}

