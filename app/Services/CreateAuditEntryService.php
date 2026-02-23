<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditEntryRepository;

final class CreateAuditEntryService
{
    public function __construct(
        private AuditEntryRepository $repo
    ) {}

    /**
     * Normaliza entradas de mês para YYYY-MM
     * Aceita: YYYY-MM, MM/YYYY, "fev 2026"/"fevereiro 2026", "fev"/"fevereiro" (usando ano atual),
     * e variações "2-2026", "2 2026", "2.2026".
     */
    private function normalizeAuditMonth(?string $input): ?string
    {
        if ($input === null) return null;
        $s = trim(strtolower($input));

        $map = [
            'jan'=>'01','janeiro'=>'01',
            'fev'=>'02','fevereiro'=>'02',
            'mar'=>'03','março'=>'03','marco'=>'03',
            'abr'=>'04','abril'=>'04',
            'mai'=>'05','maio'=>'05',
            'jun'=>'06','junho'=>'06',
            'jul'=>'07','julho'=>'07',
            'ago'=>'08','agosto'=>'08',
            'set'=>'09','setembro'=>'09',
            'out'=>'10','outubro'=>'10',
            'nov'=>'11','novembro'=>'11',
            'dez'=>'12','dezembro'=>'12',
        ];

        // 1) YYYY-MM
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $s)) return $s;

        // 2) MM/YYYY
        if (preg_match('/^(0?[1-9]|1[0-2])\s*\/\s*(\d{4})$/', $s, $m)) {
            $mm = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            return "{$m[2]}-{$mm}";
        }

        // 3) "fev 2026" / "fevereiro 2026"
        if (preg_match('/^([a-zçõ]+)\s+(\d{4})$/u', $s, $m)) {
            $mon = $map[$m[1]] ?? null;
            if ($mon) return "{$m[2]}-{$mon}";
        }

        // 4) Apenas mês por nome -> usa ano atual
        if (isset($map[$s])) {
            $year = (new \DateTime('now'))->format('Y');
            return "{$year}-{$map[$s]}";
        }

        // 5) "2-2026" / "2 2026" / "2.2026"
        if (preg_match('/^([1-9]|1[0-2])\s*(?:-|\.| )\s*(\d{4})$/', $s, $m)) {
            $mm = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            return "{$m[2]}-{$mm}";
        }

        return null;
    }

    /**
     * Converte string com IDs (separadores ; , | ou espaços) em array<int> único e sanitizado.
     * Ex.: "10;5;1", "10, 5, 1", "10 5 1", "10|5|1"
     */
    private function parseReasonIds(string $idsStr): array
    {
        $parts = preg_split('/[;,|\s]+/', $idsStr, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_filter(
            array_map(
                static fn($s) => (int)preg_replace('/\D+/', '', $s),
                $parts
            ),
            static fn($n) => $n > 0
        )));
    }

    /**
     * Cria um registro em audit_entries com validações e regras de negócio aplicadas.
     *
     * @return int ID inserido em audit_entries
     * @throws \InvalidArgumentException em caso de dados inválidos
     * @throws \PDOException em falhas de acesso ao banco
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

            // Radios SEM default silencioso: se ausente -> -1 -> invalida
            'sla_met'             => array_key_exists('sla_met', $post) ? (int)$post['sla_met'] : -1,
            'is_compliant'        => array_key_exists('is_compliant', $post) ? (int)$post['is_compliant'] : -1,
        ];

        // 2) Reason IDs: aceita string, array e fallbacks
        $idsStr = null;
        if (isset($post['noncompliance_reason_ids']) && is_string($post['noncompliance_reason_ids'])) {
            $idsStr = $post['noncompliance_reason_ids'];
        } elseif (isset($post['noncompliance_reason_ids']) && is_array($post['noncompliance_reason_ids'])) {
            $idsStr = implode(';', $post['noncompliance_reason_ids']);
        } elseif (isset($post['nc_ids'])) {
            $idsStr = (string)$post['nc_ids'];
        } elseif (isset($post['noncompliance_reasons_ids'])) {
            $idsStr = (string)$post['noncompliance_reasons_ids'];
        }
        $reasonIds = $this->parseReasonIds((string)$idsStr);

        // 3) ticket_type: normaliza / valida (com e sem acento)
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

        // 4) Validações
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
            throw new \InvalidArgumentException('Selecione "SLA Atingido?" (Sim ou Não).');
        }
        if (!in_array($data['is_compliant'], [0,1], true)) {
            throw new \InvalidArgumentException('Selecione "Chamado Conforme?" (Sim ou Não).');
        }

        // 4.1) Duplicidade: amigável (antes do UNIQUE)
        if ($this->repo->existsByTicketNumber($data['ticket_number'])) {
            throw new \InvalidArgumentException("O ticket {$data['ticket_number']} já existe. Altere o número antes de enviar.");
        }

        // 5) Regras de negócio
        if ((int)$data['is_compliant'] === 0) {
            if (empty($reasonIds)) {
                throw new \InvalidArgumentException('Selecione ao menos uma justificativa.');
            }
        } else {
            $reasonIds = [];
        }

        // 6) Strings derivadas -> IDs sempre; labels opcional
        $data['noncompliance_reason_ids'] = !empty($reasonIds) ? implode(';', $reasonIds) : null;

        $data['noncompliance_reasons'] = null;
        if (!empty($reasonIds)) {
            try {
                $pdo = $this->repo->rawPdo();
                $placeholders = implode(',', array_fill(0, count($reasonIds), '?'));
                $stmt = $pdo->prepare(
                    "SELECT noncompliance_reason FROM noncompliance_reasons WHERE id IN ($placeholders)"
                );
                $stmt->execute($reasonIds);
                $labels = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                $data['noncompliance_reasons'] = !empty($labels) ? implode(';', $labels) : null;
            } catch (\Throwable $e) {
                // Falha ao buscar labels: segue só com IDs
                $data['noncompliance_reasons'] = null;
            }
        }

        // 7) Log opcional
        if (class_exists(\App\Support\Logger::class)) {
            (new \App\Support\Logger())->write(
                'debug.log',
                date('c')
                . ' SERVICE create data=' . json_encode($data, JSON_UNESCAPED_UNICODE)
                . ' reasonIds=' . json_encode($reasonIds)
                . PHP_EOL
            );
        }

        // 8) Cria: ⚠️ PASSAR $reasonIds (nada de [])
        return $this->repo->create($data, $reasonIds);
    }
}