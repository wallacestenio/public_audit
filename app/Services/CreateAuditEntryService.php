<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditEntryRepository;
use App\Support\DateNormalizer;
use App\Models\SchemaInspector;

/**
 * Service de criação/validação de audit_entries.
 * Compatível com a DI atual do index.php (repo, mapper, schema), ainda que mapper/schema não sejam usados aqui.
 */
final class CreateAuditEntryService
{
    public function __construct(
        private AuditEntryRepository $repo,
        private ?PayloadMapper $mapper = null,           // opcional: manter compatibilidade com index.php
        private ?SchemaInspector $schema = null           // opcional
    ) {}

    public function handle(array $p): int
    {
        // 1) Campos obrigatórios — todos (sem audit_date)
        $required = [
            'ticket_number','ticket_type','kyndryl_auditor','petrobras_inspector',
            'audited_supplier','location','audit_month','priority',
            'requester_name','category','resolver_group','sla_met','is_compliant'
        ];
        foreach ($required as $f) {
            if (!array_key_exists($f, $p)) {
                throw new \InvalidArgumentException("Preencha o campo obrigatório: {$f}");
            }
            // cuidado com '0' — precisa considerar como válido
            $val = is_string($p[$f]) ? trim($p[$f]) : $p[$f];
            if ($val === '' || $val === null) {
                throw new \InvalidArgumentException("Preencha o campo obrigatório: {$f}");
            }
        }

        // 2) Normalizações
        // ticket_number: TRIM + colapsa espaços internos + UPPER
        
$ticket = strtoupper(trim(preg_replace('/\s+/', ' ', (string)$p['ticket_number'])));
if (!preg_match('/^(INC|RITM|SCTASK)\d{6,}$/', $ticket)) {
    throw new \InvalidArgumentException(
        'Número de Ticket inválido. Deve iniciar com INC, RITM ou SCTASK seguido de dígitos. Ex.: INC1234567'
    );
}


// (Opcional) Ajusta o tipo de ticket a partir do prefixo, para garantir consistência:
$prefix = substr($ticket, 0, 6) === 'SCTASK' ? 'SCTASK' : substr($ticket, 0, 3);
$ticketTypeByPrefix = [
    'INC'    => 'INCIDENTE',
    'RITM'   => 'REQUISIÇÃO',
    'SCTASK' => 'TASK',
];
$enforcedType = $ticketTypeByPrefix[$prefix] ?? null;



        // audit_month: converter livre -> YYYY-MM (obrigatório)
        $month = DateNormalizer::normalizeAuditMonth($p['audit_month'] ?? null);
        if ($month === null) {
            throw new \InvalidArgumentException('Mês da Auditoria inválido. Ex.: "fevereiro 2026", "02/2026", "2026-02".');
        }

        // Booleans como '0'/'1' (TEXT no schema)
        $isCompliant = ((int)!!($p['is_compliant'])) ? '1' : '0';
        $slaMet      = ((int)!!($p['sla_met']))       ? '1' : '0';

        // 3) Tags (quando Não conforme)
        $raw = $p['noncompliance_reasons'] ?? '';
        $tokens = is_array($raw)
            ? $raw
            : ((trim((string)$raw) === '') ? [] : preg_split('/[;,]/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY));
        $tokens = array_values(array_unique(array_map(fn($s) => trim((string)$s), $tokens)));

        if ($isCompliant === '0' && empty($tokens)) {
            throw new \InvalidArgumentException('Selecione ao menos uma justificativa (tag) quando Não conforme.');
        }
        if ($isCompliant === '1') {
            $tokens = []; // se conforme, não grava tags
        }

        // 4) Monta payload exatamente com os nomes da tabela
        $data = [
            'ticket_number'       => $ticket,
            'ticket_type' => $enforcedType ?? self::s($p['ticket_type'] ?? null),
            'kyndryl_auditor'     => self::s($p['kyndryl_auditor'] ?? null),
            'petrobras_inspector' => self::s($p['petrobras_inspector'] ?? null),
            'audited_supplier'    => self::s($p['audited_supplier'] ?? null),
            'location'            => self::s($p['location'] ?? null),
            'audit_month'         => $month,
            'priority'            => self::s($p['priority'] ?? null),
            'requester_name'      => (string)($p['requester_name'] ?? ''),
            'category'            => self::s($p['category'] ?? null),
            'resolver_group'      => self::s($p['resolver_group'] ?? null),
            'sla_met'             => $slaMet,
            'is_compliant'        => $isCompliant,
        ];

        // 5) (Importante) NÃO faça pré-cheque de duplicidade aqui.
        // Deixe o banco decidir (constraint UNIQUE/PK) e o Controller traduz a exceção.
        // if ($this->repo->existsTicketNumber($ticket)) { throw new \InvalidArgumentException("{$ticket} já está salvo."); }

        // 6) Insere + ponte de justificativas (tokens)
        return $this->repo->create($data, $tokens);
    }

    private static function s(mixed $v): ?string
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        return ($s === '') ? null : $s;
    }
}