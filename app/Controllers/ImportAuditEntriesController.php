<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ImportAuditEntriesService;

final class ImportAuditEntriesController
{
    public function __construct(
        private ImportAuditEntriesService $service
    ) {}

    public function showForm(): void
    {
        require __DIR__ . '/../Views/import_form.php';
    }

    public function import(): void
    {
        if (empty($_FILES['file']['tmp_name'])) {
            $_SESSION['flash_error'] = 'Nenhum arquivo enviado.';
            header('Location: /import');
            exit;
        }

        try {
            $this->service->importCsv(
    $_FILES['file']['tmp_name'],
    $_SESSION['user'],
    $_FILES['file']['name']
);

            $_SESSION['flash_success'] = 'Importação realizada com sucesso.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /import');
        exit;
    }
}