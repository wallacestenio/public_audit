<?php
declare(strict_types=1);

namespace App\Support;

final class CsvReader
{
    public function read(string $filePath, string $delimiter = ';'): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException('Arquivo CSV não encontrado.');
        }

        $rows = [];

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Não foi possível abrir o arquivo CSV.');
        }

        while (($data = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            // ignora linhas vazias
            if ($data === [null] || $data === []) {
                continue;
            }
            $rows[] = $data;
        }

        fclose($handle);
        return $rows;
    }
}