<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class KyndrylAuditorRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retorna o inspetor Petrobras e a localidade
     * associados ao auditor Kyndryl,
     * buscando PELO NOME do auditor (chave correta no seu modelo).
     */
    public function getInspectorAndLocationByAuditorName(string $auditorName): ?array
    {
        $sql = "
            SELECT
                ka.inspector_id,
                pi.petrobras_inspector,
                ka.location_id,
                l.location
            FROM kyndryl_auditors ka
            LEFT JOIN petrobras_inspectors pi
                   ON pi.id = ka.inspector_id
            LEFT JOIN locations l
                   ON l.id = ka.location_id
            WHERE ka.kyndryl_auditor = :name
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':name' => $auditorName]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}