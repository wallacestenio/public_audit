<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\APKIA\APKIAService;
use PDO;
use Throwable;

class APKIAController
{
    private PDO $pdo;
    private APKIAService $apkia;

    public function __construct(PDO $pdo)
    {
        $this->pdo   = $pdo;
        $this->apkia = new APKIAService($pdo);
    }

    public function process(): void
{
    $rawText = trim($_POST['raw_text'] ?? '');

    if ($rawText === '') {
        echo 'Texto obrigatório';
        return;
    }

    $apkiaResult = $this->apkia->analyze($rawText);

    $payload = urlencode(json_encode($apkiaResult));

    header('Location: /audit/form?from=apkia&data=' . $payload);
    exit;
}

    /**
     * POST /apkia/analyze
     */
    public function analyze(): void
    {
        try {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);

            if (!is_array($data) || empty($data['text'])) {
                $this->json([
                    'success' => false,
                    'message' => 'Texto para análise não informado.'
                ], 400);
                return;
            }

            $text = trim((string) $data['text']);

            if (mb_strlen($text) < 30) {
                $this->json([
                    'success' => false,
                    'message' => 'Texto insuficiente para análise.'
                ], 422);
                return;
            }

            $analysis = $this->apkia->analyzeText($text);

            $this->json([
                'success'        => true,
                'summary'        => $analysis['summary']        ?? [],
                'ticket_number'  => $analysis['ticket_number']  ?? null,
                'ticket_type'    => $analysis['ticket_type']    ?? null,
                'resolver_group' => $analysis['resolver_group'] ?? null,
                'assigned_to'    => $analysis['assigned_to']    ?? null,
                'sla_met'        => $analysis['sla_met']        ?? null,
                'is_compliant'   => $analysis['is_compliant']   ?? null,
            ]);

        } catch (Throwable $e) {
            error_log('[APKIA] ' . $e->getMessage());

            $this->json([
                'success' => false,
                'message' => 'Erro interno ao processar análise.'
            ], 500);
        }
        
        
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

}