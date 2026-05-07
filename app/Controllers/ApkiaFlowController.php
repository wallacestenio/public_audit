<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Services\APKIA\APKIAService;

class ApkiaFlowController
{
    private APKIAService $apkia;

    public function __construct(PDO $pdo)
    {
        $this->apkia = new APKIAService($pdo);
    }

    /**
     * GET /apkia
     * Tela simples para colar texto do ServiceNow
     */
    public function form(): void
    {
        require __DIR__ . '/../Views/apkia/form.php';
        require __DIR__ . '/../Views/layout.php'; // ✅ novo layout

        
        
    }

    /**
     * POST /apkia/process
     * Fluxo: texto → APKIA → audit-form
     */
    public function process(): void
    {
        $rawText = trim($_POST['raw_text'] ?? '');

        if ($rawText === '') {
            echo 'Texto do chamado é obrigatório.';
            return;
        }

        // ✅ Usa o APKIAService REAL
        $analysis = $this->apkia->analyzeText($rawText);

        if (!is_array($analysis)) {
            echo 'Falha na análise do chamado.';
            return;
        }

        // ✅ Redireciona para o form de auditoria
        $payload = urlencode(json_encode($analysis, JSON_UNESCAPED_UNICODE));

        header('Location: /audit/form?from=apkia&data=' . $payload);
        exit;
    }
}