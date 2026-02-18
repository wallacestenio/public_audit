<?php
namespace App\Models;

class Presets extends BaseModel
{
    public function autocomplete(string $table, string $field, string $q, int $limit = 10): array
    {
        $sql = "SELECT id, $field AS label FROM $table WHERE $field LIKE :q ORDER BY $field LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':q', $q . '%');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}