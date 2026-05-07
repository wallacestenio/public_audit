<?php
declare(strict_types=1);

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

final class XlsxReader
{
    /**
     * Lê um arquivo XLSX e retorna as linhas como array simples.
     *
     * Retorno:
     * [
     *   [col0, col1, col2, ...], // header
     *   [col0, col1, col2, ...], // linha 1
     *   ...
     * ]
     */
    public function read(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('Arquivo XLSX não encontrado.');
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $e) {
            throw new RuntimeException('Erro ao abrir arquivo XLSX: ' . $e->getMessage());
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows  = [];

        foreach ($sheet->getRowIterator() as $row) {

            $rowData = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $rowData[] = trim((string) $cell->getValue());
            }

            // ignora linhas totalmente vazias
            if ($this->isEmptyRow($rowData)) {
                continue;
            }

            $rows[] = $rowData;
        }

        if (count($rows) === 0) {
            throw new RuntimeException('Planilha XLSX vazia.');
        }

        return $rows;
    }

    /**
     * Verifica se a linha está completamente vazia
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== '') {
                return false;
            }
        }
        return true;
    }
}