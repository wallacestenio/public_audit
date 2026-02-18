<?php
namespace App\Repositories;

use App\Models\Presets;
use Illuminate\Database\Capsule\Manager as DB; // (se não tiver, ignore)

final class CatalogRepository
{
    public function __construct(private Presets $presets) {}

    public function autocomplete(string $resource, string $q): array
{
    $q = trim($q);
    if ($resource === 'noncompliance-reasons') {
        // Verifica se existe a coluna reason_group
        $hasGroup = false;
        try {
            $cols = $this->presets->pdo()->query("PRAGMA table_info(noncompliance_reasons)")->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($cols as $c) if (strcasecmp((string)$c['name'], 'reason_group') === 0) { $hasGroup = true; break; }
        } catch (\Throwable $e) {}

        $sql = "SELECT id, noncompliance_reasons AS noncompliance_reason". // se sua coluna for "noncompliance_reason", ajuste aqui
               ($hasGroup ? ", reason_group" : "") .
               " FROM noncompliance_reasons
                 WHERE noncompliance_reasons LIKE :q
                 ORDER BY ".($hasGroup ? "reason_group, noncompliance_reasons" : "noncompliance_reasons")."
                 LIMIT 50";
        $stmt = $this->presets->pdo()->prepare($sql);
        $stmt->execute([':q' => '%'.$q.'%']);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Normaliza payload para o front
        return array_map(function($r) use($hasGroup){
            return [
                'id'    => (int)$r['id'],
                'label' => (string)$r['noncompliance_reasons'],   // ajuste se sua coluna for 'noncompliance_reason'
                'group' => $hasGroup ? (string)($r['reason_group'] ?? 'Outros') : 'Outros',
            ];
        }, $rows);
    }

    // ...demais resources
    return $this->presets->autocomplete($resource, $q); // seu comportamento anterior
}
}