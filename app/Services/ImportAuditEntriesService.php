<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditEntryRepository;
use App\Repositories\ImportBatchRepository;
use App\Repositories\KyndrylAuditorRepository;
use App\Support\CsvReader;
use RuntimeException;

final class ImportAuditEntriesService
{
    public function __construct(
    private CsvReader $reader,
    private AuditEntryRepository $auditRepo,
    private ImportBatchRepository $batchRepo,
    private KyndrylAuditorRepository $kyndrylRepo,
    private CreateAuditEntryService $createService // ← NOVO
) {}

    /* ===================== HELPERS ===================== */

    private function yesNoToInt(string $value): int
    {
        return strcasecmp(trim($value), 'Sim') === 0 ? 1 : 0;
    }

    private function monthToAuditMonth(string $month): string
    {
        $map = [
            'Janeiro'=>'01','Fevereiro'=>'02','Março'=>'03','Abril'=>'04',
            'Maio'=>'05','Junho'=>'06','Julho'=>'07','Agosto'=>'08',
            'Setembro'=>'09','Outubro'=>'10','Novembro'=>'11','Dezembro'=>'12',
        ];

        $month = trim($month);
        if (!isset($map[$month])) {
            throw new RuntimeException("Mês inválido: {$month}");
        }

        $year = date('Y');
        return "{$year}-{$map[$month]}";
    }

    /* ===================== IMPORT ===================== */

    public function importCsv(
    string $filePath,
    array $sessionUser,
    string $fileName
): array
 {

    $rows = $this->reader->read($filePath);
    if (count($rows) < 2) {
        throw new RuntimeException('Arquivo CSV vazio.');
    }

    $auditorMap   = $this->kyndrylRepo->mapNameToUserId();
    $reasonMap    = $this->auditRepo->mapNoncomplianceReasonLabelToId();

    /**
     * AGRUPAMENTO POR TICKET
     */
    $tickets = [];

    foreach ($rows as $i => $row) {
        if ($i === 0) continue; // header

        $ticket = strtoupper(trim($row[0] ?? ''));
        if ($ticket === '') continue;

        if (!isset($tickets[$ticket])) {
            $auditorName = trim($row[2]);

            if (empty($auditorMap[$auditorName])) {
                throw new RuntimeException("Auditor não encontrado: {$auditorName}");
            }

            $tickets[$ticket] = [
                'user_id'                 => $auditorMap[$auditorName], // ✅ auditor da planilha
                'ticket_number'           => $ticket,
                'ticket_type'             => trim($row[1]),
                'kyndryl_auditor'         => $auditorName,
                'petrobras_inspector'     => trim($row[3]),
                'audited_supplier'        => trim($row[4]),
                'location'                => trim($row[5]),
                'audit_month'             => trim($row[6]),
                'sla_met'                 => trim($row[7]),
                'priority'                => (int) filter_var($row[8], FILTER_SANITIZE_NUMBER_INT),
                'category'                => trim($row[9]),
                'resolver_group'          => trim($row[10]),
                'is_compliant'            => trim($row[11]),
                'nc_labels'               => [],
            ];
        }

        // ✅ Coluna M – Não conformidades (descrições)
        if (!empty($row[12])) {
            $tickets[$ticket]['nc_labels'][] = trim($row[12]);
        }
    }

    /**
     * CRIA O BATCH (SEGURANÇA)
     */
    $batchId = $this->batchRepo->create([
        'imported_by_user_id' => $sessionUser['id'],
        'imported_by_name'    => $sessionUser['name'],
        'file_name'           => $fileName,
    ]);

    /**
     * INSERÇÃO REAL (DELEGADA AO CORE)
     */
    foreach ($tickets as $data) {

        $data['noncompliance_reason_ids'] = null;

        if (strcasecmp($data['is_compliant'], 'Não') === 0) {
            $ids = [];

            foreach (array_unique($data['nc_labels']) as $label) {
                if (!isset($reasonMap[$label])) {
                    throw new RuntimeException("Não conformidade não cadastrada: {$label}");
                }
                $ids[] = $reasonMap[$label];
            }

            $data['noncompliance_reason_ids'] = implode(';', $ids);
        }

        // ✅ IMPORTANTE: NÃO SALVA DIRETO
        // ✅ PASSA PELO MESMO CORE DO FORM
        $this->createService->handle($data);

        // ✅ vincula ao batch
        $this->auditRepo->attachImportBatch($data['ticket_number'], $batchId);
    }
}

}