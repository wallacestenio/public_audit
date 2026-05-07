<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditEntryRepository;
use App\Repositories\ImportBatchRepository;
use App\Repositories\KyndrylAuditorRepository;
use App\Support\XlsxReader;
use RuntimeException;

final class ImportAuditEntriesXlsxService
{
    public function __construct(
        private XlsxReader $reader,
        private AuditEntryRepository $auditRepo,
        private ImportBatchRepository $batchRepo,
        private KyndrylAuditorRepository $kyndrylRepo,
        private CreateAuditEntryService $createService
    ) {}

    /**
     * Importa planilha XLSX de auditoria
     */
    public function importXlsx(
        string $filePath,
        array $sessionUser,
        string $fileName
    ): array {

        $rows = $this->reader->read($filePath);
        if (count($rows) < 2) {
            throw new RuntimeException('Arquivo XLSX vazio.');
        }

        $auditorMap = $this->kyndrylRepo->mapNameToUserId();
        $reasonMap  = $this->auditRepo->mapNoncomplianceReasonLabelToId();

        /**
         * AGRUPAMENTO POR TICKET
         */
        /**
 * AGRUPAMENTO POR TICKET (CONSOLIDA STATUS E JUSTIFICATIVAS)
 */
$tickets = [];

foreach ($rows as $i => $row) {
    if ($i === 0) {
        continue; // header
    }

    $ticket = strtoupper(trim($row[0] ?? ''));
    if ($ticket === '') {
        continue;
    }

    // 🔹 Inicializa o ticket apenas uma vez
    if (!isset($tickets[$ticket])) {

        $auditorName = trim($row[2] ?? '');

        if ($auditorName === '' || !isset($auditorMap[$auditorName])) {
            throw new RuntimeException(
                "Auditor não encontrado na planilha: {$auditorName}"
            );
        }

        $tickets[$ticket] = [
            'user_id'             => $auditorMap[$auditorName],
            'ticket_number'       => $ticket,
            'ticket_type'         => trim($row[1] ?? ''),
            'kyndryl_auditor'     => $auditorName,
            'petrobras_inspector' => trim($row[3] ?? ''),
            'audited_supplier'    => trim($row[4] ?? ''),
            'location'            => trim($row[5] ?? ''),
            'audit_month'         => trim($row[6] ?? ''),
            'sla_met'             => trim($row[7] ?? ''),
            'priority'            => (int) filter_var(
                $row[8] ?? '',
                FILTER_SANITIZE_NUMBER_INT
            ),
            'category'            => trim($row[9] ?? ''),
            'resolver_group'      => trim($row[10] ?? ''),
            'is_compliant'        => 'Sim', // ✅ padrão inicial
            'nc_labels'           => [],
        ];
    }

    // 🔹 Lê o status REAL desta linha
    $status = trim($row[11] ?? '');

    // 🔹 Se QUALQUER linha for "Não", o ticket inteiro vira "Não"
    if (strcasecmp($status, 'Não') === 0) {

        $tickets[$ticket]['is_compliant'] = 'Não';

        // 🔹 Só coleta justificativa das linhas "Não"
        if (!empty($row[12])) {
            $tickets[$ticket]['nc_labels'][] = trim($row[12]);
        }
    }
}

        /**
         * CRIA O BATCH (SEGURANÇA / AUDITORIA)
         */
        $batchId = $this->batchRepo->create([
            'imported_by_user_id' => $sessionUser['id'],
            'imported_by_name'    => $sessionUser['name'],
            'file_name'           => $fileName,
        ]);

        /**
         * INSERÇÃO REAL (PASSA PELO CORE DO SISTEMA)
         */
        foreach ($tickets as $data) {

    // ✅ CONVERSÃO CORRETA (CRÍTICA)
    
// ✅ CONVERSÃO FINAL E CORRETA
$data['is_compliant'] =
    strcasecmp($data['is_compliant'], 'Sim') === 0 ? 1 : 0;

$data['noncompliance_reason_ids'] = null;

if ($data['is_compliant'] === 0) {

    if (empty($data['nc_labels'])) {
        throw new RuntimeException(
            'Não conforme sem justificativa no ticket: ' . $data['ticket_number']
        );
    }

    $ids = [];
    foreach (array_unique($data['nc_labels']) as $label) {
        $label = trim(preg_replace('/\s+/', ' ', $label));

        if (!isset($reasonMap[$label])) {
            throw new RuntimeException(
                "Justificativa não encontrada: {$label}"
            );
        }

        $ids[] = $reasonMap[$label];
    }

    $data['noncompliance_reason_ids'] = implode(';', $ids);
}

// ✅ AGORA O HANDLE FUNCIONA COMO FOI DESENHADO
$this->createService->handle($data);


    $this->auditRepo->attachImportBatch(
        $data['ticket_number'],
        $batchId
    );
}

        return [
            'rows_read'        => count($rows) - 1,
            'tickets_imported' => count($tickets),
            'file_name'        => $fileName,
        ];
    }
}