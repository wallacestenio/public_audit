<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditEntry;

final class AuditEntryRepository
{
    public function __construct(
        private AuditEntry $model
    ) {}

    /**
     * Cria a entrada e vincula as razões (IDs) na ponte.
     *
     * @param array $data
     * @param int[] $reasonIds
     * @return int
     */
    public function create(array $data, array $reasonIds = []): int
    {
        return $this->model->insertWithReasons($data, $reasonIds);
    }

    


public function rawPdo(): \PDO
{
    return $this->model->getPdo();
}


}