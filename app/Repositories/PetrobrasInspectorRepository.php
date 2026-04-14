<?php
namespace App\Repositories;

use PDO;

final class PetrobrasInspectorRepository
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        return $this->pdo
            ->query("SELECT id, petrobras_inspector FROM petrobras_inspectors ORDER BY petrobras_inspector")
            ->fetchAll(PDO::FETCH_ASSOC);
    }
}