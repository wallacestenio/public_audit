<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    /**
     * Emite um CSV (UTF-8 BOM) com cabeçalho + linhas.
     * $rows deve ser array de arrays (cada linha = array indexado na ordem do $header).
     */
    public static function csv(string $filename, array $header, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');

        $out = fopen('php://output', 'w');
        // BOM para Excel
        fwrite($out, "\xEF\xBB\xBF");

        // Sempre passe o 5º parâmetro ($escape) para evitar deprecation
        fputcsv(resource $stream, array $fields, string $separator = ",", string $enclosure = "\"", string $escape = "\\", string $eol = "\n"): int|false // <-- 5º parâmetro $escape
foreach ($rows as $r) {
    fputcsv(resource $stream, array $fields, string $separator = ",", string $enclosure = "\"", string $escape = "\\", string $eol = "\n"): int|false
}
        fclose($out);
    }
}