<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class KyndrylAuditorRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Retorna um mapa:
     *  'Nome do Auditor' => user_id
     */
    public function mapNameToUserId(): array
    {
        $sql = "
            SELECT id, kyndryl_auditor
            FROM kyndryl_auditors
            WHERE kyndryl_auditor IS NOT NULL
        ";

        $stmt = $this->pdo->query($sql);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = trim($row['kyndryl_auditor']);
            if ($name !== '') {
                $map[$name] = (int)$row['id'];
            }
        }

        return $map;
    }

    /**
     * Retorna um mapa:
     *  'Nome do Fiscal Petrobras' => inspector_id
     */
    public function mapInspectorNameToId(): array
    {
        $sql = "
            SELECT id, petrobras_inspector
            FROM petrobras_inspectors
            WHERE petrobras_inspector IS NOT NULL
        ";

        $stmt = $this->pdo->query($sql);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = trim($row['petrobras_inspector']);
            if ($name !== '') {
                $map[$name] = (int)$row['id'];
            }
        }

        return $map;
    }

    /**
     * Busca fiscal e localidade pelo ID do auditor (uso geral no sistema)
     */
    public function getInspectorAndLocationByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                pi.id  AS inspector_id,
                pi.petrobras_inspector,
                l.id   AS location_id,
                l.location
             FROM kyndryl_auditors ka
             LEFT JOIN petrobras_inspectors pi ON pi.id = ka.inspector_id
             LEFT JOIN locations l             ON l.id = ka.location_id
             WHERE ka.id = :id
             LIMIT 1"
        );

        $stmt->execute([':id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}