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
        private KyndrylAuditorRepository $kyndrylRepo
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
    ): void {

        $rows = $this->reader->read($filePath);
        if (count($rows) < 2) {
            throw new RuntimeException('Arquivo CSV vazio.');
        }

        $auditorMap   = $this->kyndrylRepo->mapNameToUserId();
        $inspectorMap = $this->kyndrylRepo->mapInspectorNameToId();

        $tickets = [];

        foreach ($rows as $i => $row) {
            if ($i === 0) continue; // header

            $ticket = trim($row[0]); // A
            if ($ticket === '') continue;

            if (!isset($tickets[$ticket])) {
                $tickets[$ticket] = [
                    'ticket_number'       => $ticket,
                    'ticket_type'         => trim($row[1]),     // B
                    'kyndryl_auditor'     => trim($row[2]),     // C
                    'petrobras_inspector' => trim($row[3]),     // D
                    'audited_supplier'    => trim($row[4]),     // E
                    'location'            => trim($row[5]),     // F
                    'audit_month'         => $this->monthToAuditMonth($row[6]), // G
                    'sla_met'             => $this->yesNoToInt($row[7]), // H
                    'priority'            => (int)$row[8],      // I
                    'category'            => trim($row[9]),     // J
                    'resolver_group'      => trim($row[10]),    // K
                    'is_compliant'        => $this->yesNoToInt($row[11]), // L
                    'nc_labels'           => [],
                ];
            }

            // Coluna M – Noncompliance
            if (!empty($row[12])) {
                $tickets[$ticket]['nc_labels'][] = trim($row[12]);
            }
        }

        /* ---------- cria batch ---------- */
        $batchId = $this->batchRepo->create([
            'imported_by_user_id' => $sessionUser['id'],
            'imported_by_name'    => $sessionUser['name'],
            'source_auditor_name' => $tickets[array_key_first($tickets)]['kyndryl_auditor'],
            'source_auditor_id'   => $auditorMap[$tickets[array_key_first($tickets)]['kyndryl_auditor']] ?? null,
            'file_name'           => $fileName,
        ]);

        /* ---------- salva ---------- */
        foreach ($tickets as $data) {
            $this->auditRepo->create([
                'ticket_number'           => $data['ticket_number'],
                'ticket_type'             => $data['ticket_type'],
                'kyndryl_auditor'         => $data['kyndryl_auditor'],
                'petrobras_inspector'     => $data['petrobras_inspector'],
                'audited_supplier'        => $data['audited_supplier'],
                'location'                => $data['location'],
                'audit_month'             => $data['audit_month'],
                'sla_met'                 => $data['sla_met'],
                'priority'                => $data['priority'],
                'category'                => $data['category'],
                'resolver_group'          => $data['resolver_group'],
                'is_compliant'            => $data['is_compliant'],
                'noncompliance_reasons'   => implode(';', array_unique($data['nc_labels'])),
                'import_batch_id'         => $batchId,
            ]);
        }
    }
}