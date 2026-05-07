<?php
declare(strict_types=1);

namespace App\Services\APKIA;

use App\Models\AuditBatchItem;
use Exception;

class APKIABatchService
{
    private APKIAService $apkiaService;
    private AuditBatchItem $itemModel;

    public function __construct(
        APKIAService $apkiaService,
        AuditBatchItem $itemModel
    ) {
        $this->apkiaService = $apkiaService;
        $this->itemModel = $itemModel;
    }

    /**
     * Processa todos os itens de uma sessão de auditoria em lote
     */
    public function processSession(int $sessionId): void
{
    $items = $this->itemModel->getPendingItemsBySession($sessionId);

    foreach ($items as $item) {
        try {
            
$analysis = $this->apkiaService->analyzeText([
    'sn_category' => null,          // não vem do form
    'sn_service'  => null,
    'sn_item'     => null,
    'raw_text'    => $rawText
]);


            $signals = $this->extractSignals($analysis);

            $attentionLevel = $signals['attention_level'] ?? 'LOW';
            $riskScore = (int) ($analysis['risk_score'] ?? 0);

            $this->itemModel->updateAnalysis(
                itemId: (int) $item['id'],
                apkiaResultJson: json_encode($analysis, JSON_THROW_ON_ERROR),
                attentionLevel: $attentionLevel,
                hasCritical: $signals['has_critical'] ?? false,
                slaMet: $signals['sla_met'] ?? null,
                riskScore: $riskScore
            );

        } catch (Exception $e) {
            $this->itemModel->updateAnalysis(
                itemId: (int) $item['id'],
                apkiaResultJson: json_encode(['error' => $e->getMessage()]),
                attentionLevel: 'HIGH',
                hasCritical: true,
                slaMet: null,
                riskScore: 5
            );
        }
    }
}

    /**
     * Extrai sinais objetivos do retorno do APKIA
     */
    private function extractSignals(array $analysis): array
    {
        // defaults conservadores
        $attentionLevel = 'LOW';
        $hasCritical = false;
        $slaMet = null;

        // SLA explícito
        if (isset($analysis['sla_met'])) {
            $slaMet = (int) $analysis['sla_met'];
            if ($slaMet === 0) {
                $attentionLevel = 'HIGH';
            }
        }

        // violações críticas explícitas
        if (!empty($analysis['violations'])) {
            $attentionLevel = 'HIGH';
            $hasCritical = true;
        }

        // avisos sem crítico
        if (!empty($analysis['warnings']) && $attentionLevel !== 'HIGH') {
            $attentionLevel = 'MEDIUM';
        }

        return [
            'attention_level' => $attentionLevel,
            'has_critical'    => $hasCritical,
            'sla_met'         => $slaMet,
        ];
    }
}
