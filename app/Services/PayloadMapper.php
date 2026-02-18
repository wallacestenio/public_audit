<?php
declare(strict_types=1);

namespace App\Services;

final class PayloadMapper
{
    private array $cols = [
        'ticket_number','ticket_type','kyndryl_auditor','petrobras_inspector',
        'audited_supplier','location','audit_date','audit_month','priority',
        'requester_name','category','resolver_group','sla_met','is_compliant'
    ];

    public function mapAuditEntry(array $payload): array
    {
        $data = [];
        foreach ($this->cols as $col) {
            if (in_array($col, ['sla_met','is_compliant'], true)) {
                $data[$col] = isset($payload[$col]) ? ( (int)!!$payload[$col] ? '1' : '0' ) : '0';
                continue;
            }

            // hidden first, fallback label
            $raw = $payload[$col] ?? ($payload[$col.'_label'] ?? null);
            $val = $this->firstValid($raw);

            if ($col === 'audit_month') {
    $preferYear = isset($payload['audit_year']) && ctype_digit((string)$payload['audit_year'])
        ? (int)$payload['audit_year']
        : (int)date('Y');
    $val = $this->normalizeAuditMonth($val, $preferYear);
    }

            $data[$col] = ($val === '' ? null : $val);
        }
        return $data;
    }

    public function mapReasons(array $payload): array
    {
        $raw = $payload['noncompliance_reasons'] ?? '';
        if (is_array($raw)) $arr = $raw;
        else {
            $s = trim((string)$raw);
            $arr = $s === '' ? [] : preg_split('/[;,]/', $s, -1, PREG_SPLIT_NO_EMPTY);
        }
        return array_values(array_unique(array_map(fn($x)=>trim((string)$x), $arr)));
    }

    private function firstValid($raw): ?string
    {
        if ($raw === null) return null;
        if (is_array($raw)) {
            foreach ($raw as $v) { $v = trim((string)$v); if ($v!=='') return $v; }
            return null;
        }
        $s = trim((string)$raw);
        return $s === '' ? null : $s;
    }

    private function normalizeAuditMonth(?string $v, int $preferYear): ?string
    {
    if ($v === null) return null;
    $s = mb_strtolower(trim($v), 'UTF-8');

    // bloqueia dias da semana
    $weekdays = ['segunda','terça','terca','quarta','quinta','sexta','sábado','sabado','domingo'];
    foreach ($weekdays as $w) {
        if (mb_strpos($s, $w, 0, 'UTF-8') !== false) {
            return null;
        }
    }

    // remove acentos
    if (class_exists('\Normalizer')) {
        $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
        $s = preg_replace('/\pM/u', '', $s);
    } else {
        $s = iconv('UTF-8','ASCII//TRANSLIT',$s);
        $s = strtolower($s);
    }

    // yyyy-mm
    if (preg_match('/^(\d{4})-(\d{2})$/', $s, $m)) {
        $yyyy = (int)$m[1]; $mm = (int)$m[2];
        return ($mm>=1 && $mm<=12) ? sprintf('%04d-%02d', $yyyy, $mm) : null;
    }
    // mm/yyyy
    if (preg_match('/^(\d{1,2})\/(\d{4})$/', $s, $m)) {
        $mm = (int)$m[1]; $yyyy = (int)$m[2];
        return ($mm>=1 && $mm<=12) ? sprintf('%04d-%02d', $yyyy, $mm) : null;
    }

    // nomes PT-BR
    $map = [
        'jan'=>'01','janeiro'=>'01','fev'=>'02','fevereiro'=>'02','mar'=>'03','marco'=>'03',
        'abr'=>'04','abril'=>'04','mai'=>'05','maio'=>'05','jun'=>'06','junho'=>'06',
        'jul'=>'07','julho'=>'07','ago'=>'08','agosto'=>'08','set'=>'09','setembro'=>'09',
        'out'=>'10','outubro'=>'10','nov'=>'11','novembro'=>'11','dez'=>'12','dezembro'=>'12'
    ];
    if (preg_match('/^([a-z]+)[\s\-\/]?(\d{4})$/', $s, $m)) {
        $nome = $m[1]; $yyyy = (int)$m[2];
        $mm = $map[$nome] ?? null;
        return $mm ? sprintf('%04d-%s', $yyyy, $mm) : null;
    }
    if (isset($map[$s])) {
        $mm = $map[$s];
        $yyyy = $preferYear;
        return sprintf('%04d-%s', $yyyy, $mm);
    }

    // "02/26" -> 2026-02
    if (preg_match('/^(\d{1,2})\/(\d{2})$/', $s, $m)) {
        $mm = (int)$m[1]; $yy = (int)$m[2];
        $yyyy = $yy >= 70 ? 1900 + $yy : 2000 + $yy;
        return ($mm>=1 && $mm<=12) ? sprintf('%04d-%02d', $yyyy, $mm) : null;
    }

    return null;
    }
}