<?php
declare(strict_types=1);

namespace App\Services\AuditSession;

use RuntimeException;

final class SessionImportService
{
    /**
     * Ponto único de entrada para importação
     */
    public function import(
        string $type,
        mixed $input
    ): array {
        return match ($type) {
            'csv'   => $this->parseCsv($input),
            'xlsx'  => $this->parseXlsx($input),
            'text'  => $this->parseText($input),
            default => throw new RuntimeException('Tipo de importação não suportado')
        };
    }

    /* =========================================================
     * CSV
     * ========================================================= */

    private function parseCsv(string $filePath): array
    {
        $items = [];

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }

        // Detecta delimitador
        $firstLine = fgets($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',')
            ? ';'
            : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            return [];
        }

        $header = array_map(
            fn ($h) => strtolower(trim((string)$h)),
            $header
        );

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count(array_filter($row)) === 0) {
                continue;
            }

            $data = array_combine($header, $row) ?: [];

            $items[] = [
                'ticket_number' => $data['ticket'] ?? $data['ticket_number'] ?? null,
                'sn_category'   => $data['category'] ?? null,
                'sn_service'    => $data['service'] ?? null,
                'sn_item'       => $data['item'] ?? null,
                'resolver_group'=> $data['resolver_group'] ?? null,
                'priority'      => isset($data['priority']) ? (int) $data['priority'] : null,
                'raw_text'      => implode(' | ', array_filter($data)),
                'source'        => 'CSV'
            ];
        }

        fclose($handle);
        return $items;
    }

    /* =========================================================
     * XLSX
     * ========================================================= */

private function parseXlsx(string $filePath): array
{
    $items = [];

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);

    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {

        // Lê todas as linhas da aba
        $rows = $sheet->toArray(null, true, true, true);

        if (empty($rows) || count($rows) < 2) {
            continue; // ignora sheets vazios
        }

        // Reindexa linhas
        $rows = array_values($rows);

        foreach ($rows as $row) {

            if (!is_array($row)) {
                continue;
            }

            // Coluna A = primeira célula
            $firstCell = (string) ($row[array_key_first($row)] ?? '');
            $firstCell = html_entity_decode($firstCell, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $firstCell = preg_replace('/\x{00A0}/u', ' ', $firstCell); // NBSP
            $firstCell = strtoupper(trim($firstCell));

            if ($firstCell === '') {
                continue;
            }

            $ticket = null;

            if (preg_match('/(SCTASK|INC|RITM|TASK)\s*\d+/i', $firstCell, $m)) {
                $ticket = str_replace(' ', '', strtoupper($m[0]));
            }

            

            $items[] = [
    'ticket_number' => $ticket, // pode ser null
    'sn_category'   => null,
    'sn_service'    => null,
    'sn_item'       => null,
    'resolver_group'=> null,
    'priority'      => null,
    'raw_text'      => implode(' | ', array_filter($row)),
    'source'        => 'XLSX'
];
        }
    }

    return $items;
}


private function parseText(string $text): array
{
    $items = [];

    // Normaliza quebras de linha
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));

    // Cada linha = um chamado
    $lines = array_filter(
        array_map('trim', explode("\n", $text))
    );

    foreach ($lines as $line) {
        if ($line === '') {
            continue;
        }

        $ticket = null;

        if (preg_match('/\b(SCTASK|INC|RITM|TASK)\d{5,}\b/i', $line, $m)) {
            $ticket = strtoupper($m[0]);
        }

        $items[] = [
            'ticket_number' => $ticket,
            'sn_category'   => null,
            'sn_service'    => null,
            'sn_item'       => null,
            'resolver_group'=> null,
            'priority'      => null,
            'raw_text'      => $line,
            'source'        => 'TEXT'
        ];
    }

    return $items;
} 

    
}