<?php
namespace App\Repositories;

use PDO;

final class LocationRepository
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        return $this->pdo
            ->query("SELECT id, location FROM locations ORDER BY location")
            ->fetchAll(PDO::FETCH_ASSOC);
    }
        
public function listForSelect(): array
{
    $stmt = $this->pdo->query("
        SELECT id, name
        FROM locations
        ORDER BY name
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


}