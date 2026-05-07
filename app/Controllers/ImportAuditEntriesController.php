<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ImportAuditEntriesService;
use App\Services\ImportAuditEntriesXlsxService;

final class ImportAuditEntriesController
{
    public function __construct(
        private ImportAuditEntriesService $csvService,
        private ImportAuditEntriesXlsxService $xlsxService
    ) {}

    /**
     * Exibe o formulário de importação
     */
    public function showForm(): void
    {
        require __DIR__ . '/../Views/import_form.php';
    }

    /**
     * Decide automaticamente CSV ou XLSX e executa o import correto
     */
    public function import(): void
    {
        if (empty($_FILES['file']['tmp_name']) || empty($_FILES['file']['name'])) {
            $_SESSION['flash_error'] = 'Nenhum arquivo enviado.';
            header('Location: /import');
            exit;
        }

        $tmpPath  = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        try {
            match ($ext) {
                'csv' => $this->csvService->importCsv(
                    $tmpPath,
                    $_SESSION['user'],
                    $fileName
                ),
                'xlsx' => $this->xlsxService->importXlsx(
                    $tmpPath,
                    $_SESSION['user'],
                    $fileName
                ),
                default => throw new \RuntimeException(
                    'Formato de arquivo não suportado. Envie um arquivo CSV ou XLSX.'
                ),
            };

            $_SESSION['flash_success'] = 'Importação realizada com sucesso.';

        } catch (\Throwable $e) {

            // mensagem clara para o usuário
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /import');
        exit;
    }
}