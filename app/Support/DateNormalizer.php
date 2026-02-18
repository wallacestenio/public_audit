<?php
declare(strict_types=1);

namespace App\Support;

final class DateNormalizer
{
    private static function asciiLower(string $s): string
    {
        $from = 'ÀÁÂÃÄÅàáâãäåÈÉÊËèéêëÌÍÎÏìíîïÒÓÔÕÖØòóôõöøÙÚÛÜùúûüÇçÑñÝýŸÿŠšŽž';
        $to   = 'AAAAAAaaaaaaEEEEeeeeIIIIiiiiOOOOOOooooooUUUUuuuuCcNnYyYySsZz';
        $s = strtr($s, $from, $to);
        $tmp = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE', $s);
        if ($tmp !== false) $s = $tmp;
        return strtolower($s);
    }

    public static function normalizeAuditMonth(?string $in): ?string
    {
        if ($in === null) return null;
        $s = trim((string)$in);
        if ($s === '') return null;

        $s = self::asciiLower($s);
        $s = str_replace([',','.'], ' ', $s);
        $s = preg_replace('/\s+de\s+/', ' ', $s);
        $s = preg_replace('/\s+do\s+/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        foreach (['segunda','terca','terça','quarta','quinta','sexta','sabado','sábado','domingo'] as $w) {
            if (strpos($s, $w) !== false) return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})$/', $s, $m)) {
            $yyyy = (int)$m[1]; $mm = (int)$m[2];
            return ($mm>=1 && $mm<=12) ? sprintf('%04d-%02d', $yyyy, $mm) : null;
        }
        if (preg_match('/^(\d{4})\s+(\d{1,2})$/', $s, $m)) {
            $yyyy = (int)$m[1]; $mm = (int)$m[2];
            return ($mm>=1 && $mm<=12) ? sprintf('%04d-%02d', $yyyy, $mm) : null;
        }
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $s, $m)) {
            $mm = (int)$m[1]; $yyyy = (int)$m[2];
            return ($mm>=1 && $mm<=12) ? sprintf('%04d-%02d', $yyyy, $mm) : null;
        }
        if (preg_match('/^([a-z]+)[\s\-\/]+(\d{4})$/', $s, $m)) {
            $map = [
                'jan'=>'01','janeiro'=>'01','fev'=>'02','fevereiro'=>'02',
                'mar'=>'03','marco'=>'03','abr'=>'04','abril'=>'04',
                'mai'=>'05','maio'=>'05','jun'=>'06','junho'=>'06',
                'jul'=>'07','julho'=>'07','ago'=>'08','agosto'=>'08',
                'set'=>'09','setembro'=>'09','out'=>'10','outubro'=>'10',
                'nov'=>'11','novembro'=>'11','dez'=>'12','dezembro'=>'12'
            ];
            $nome = $m[1]; $yyyy = (int)$m[2];
            if (isset($map[$nome])) return sprintf('%04d-%s', $yyyy, $map[$nome]);
        }
        $only = [
            'jan'=>'01','janeiro'=>'01','fev'=>'02','fevereiro'=>'02','mar'=>'03','marco'=>'03',
            'abr'=>'04','abril'=>'04','mai'=>'05','maio'=>'05','jun'=>'06','junho'=>'06',
            'jul'=>'07','julho'=>'07','ago'=>'08','agosto'=>'08','set'=>'09','setembro'=>'09',
            'out'=>'10','outubro'=>'10','nov'=>'11','novembro'=>'11','dez'=>'12','dezembro'=>'12'
        ];
        if (isset($only[$s])) {
            $yyyy = (int)date('Y');
            return sprintf('%04d-%s', $yyyy, $only[$s]);
        }
        if (preg_match('/^(\d{1,2})\/(\d{2})$/', $s, $m)) {
            $mm = (int)$m[1]; $yy = (int)$m[2];
            $yyyy = $yy >= 70 ? 1900 + $yy : 2000 + $yy;
            return ($mm>=1 && $mm<=12) ? sprintf('%04d-%02d', $yyyy, $mm) : null;
        }
        if (preg_match('/\b(19|20)\d{2}\b/', $s, $ym) === 1) {
            $yyyy = (int)$ym[0];
            foreach ($only as $nome => $mm) {
                if (strpos($s, $nome) !== false) {
                    return sprintf('%04d-%s', $yyyy, $mm);
                }
            }
        }
        return null;
    }
}