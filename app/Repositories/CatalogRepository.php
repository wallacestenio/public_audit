<?php
namespace App\Repositories;

final class CatalogRepository
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Retorna lista para o front: [{id, label, group}, ...]
     * - label = noncompliance_reason
     * - group = coluna "group" (aspas por ser palavra reservada em SQL)
     */
    public function listNoncomplianceReasons(string $q = ''): array
    {
        // IMPORTANTE: dentro da string PHP (aspas simples), escape de aspas simples é \'
        $sql = 'SELECT id,
                       noncompliance_reason AS label,
                       COALESCE("group", \'Outros\') AS "group"
                FROM noncompliance_reasons';
        $params = [];

        if ($q !== '') {
            $sql .= ' WHERE noncompliance_reasons.noncompliance_reason LIKE :q OR "group" LIKE :q';
            $params[':q'] = "%{$q}%";
        }

        $sql .= ' ORDER BY "group" ASC, label ASC';

        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
