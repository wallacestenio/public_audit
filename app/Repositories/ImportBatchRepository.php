<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ImportBatchRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO import_batches
            (imported_by_user_id, imported_by_name, source_auditor_name, source_auditor_id, file_name)
            VALUES
            (:imported_by_user_id, :imported_by_name, :source_auditor_name, :source_auditor_id, :file_name)"
        );

        $stmt->execute([
            ':imported_by_user_id' => $data['imported_by_user_id'],
            ':imported_by_name'    => $data['imported_by_name'],
            ':source_auditor_name' => $data['source_auditor_name'],
            ':source_auditor_id'   => $data['source_auditor_id'],
            ':file_name'           => $data['file_name'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function finalize(int $batchId, int $total, int $success, int $errors): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE import_batches
             SET total_rows = :t,
                 success_rows = :s,
                 error_rows = :e
             WHERE id = :id"
        );

        $stmt->execute([
            ':t'  => $total,
            ':s'  => $success,
            ':e'  => $errors,
            ':id' => $batchId,
        ]);
    }
}