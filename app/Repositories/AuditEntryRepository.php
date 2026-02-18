<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditEntry;

final class AuditEntryRepository
{
    public function __construct(private AuditEntry $model) {}

    
public function create(array $data, array $reasons): int
{
    return $this->model->insertWithReasons($data, $reasons);
}


    public function exportRows(): array
    {
        return $this->model->listForExport();
    }

    public function reasonsBridge(): array
    {
        return $this->model->listReasonsBridge();
    }

    public function existsTicketNumber(string $ticket): bool
{
    return $this->model->existsTicketNumber($ticket);
}
}