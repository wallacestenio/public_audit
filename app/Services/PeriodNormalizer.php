<?php
declare(strict_types=1);

namespace App\Services;

final class PeriodNormalizer
{
    public static function normalizeMonthYear(string $month): string
    {
        $month = mb_strtolower(trim($month));

        $monthMap = [
            'janeiro' => '01', 'jan' => '01', '01' => '01',
            'fevereiro' => '02', 'fev' => '02', '02' => '02',
            'março' => '03', 'marco' => '03', '03' => '03',
            'abril' => '04', 'abr' => '04', '04' => '04',
            'maio' => '05', 'mai' => '05', '05' => '05',
            'junho' => '06', 'jun' => '06', '06' => '06',
            'julho' => '07', 'jul' => '07', '07' => '07',
            'agosto' => '08', 'ago' => '08', '08' => '08',
            'setembro' => '09', 'set' => '09', '09' => '09',
            'outubro' => '10', 'out' => '10', '10' => '10',
            'novembro' => '11', 'nov' => '11', '11' => '11',
            'dezembro' => '12', 'dez' => '12', '12' => '12',
        ];

        if (!isset($monthMap[$month])) {
            throw new \DomainException('Mês informado inválido.');
        }

        $monthNum = $monthMap[$month];

        $now = new \DateTimeImmutable('now');
        $currentYear  = (int)$now->format('Y');
        $currentMonth = (int)$now->format('m');

        // ✅ REGRA CRÍTICA
        if ($currentMonth === 1 && $monthNum === '12') {
            $year = $currentYear - 1;
        } else {
            $year = $currentYear;
        }

        return sprintf('%04d-%s', $year, $monthNum);
    }
}