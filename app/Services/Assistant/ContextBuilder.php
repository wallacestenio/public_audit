<?php
declare(strict_types=1);

namespace App\Services\Assistant;

use App\Repositories\ExecutionPlanRepository;
use PDO;

class ContextBuilder
{
    private ?PDO $pdo;
    private ExecutionPlanRepository $executionPlanRepository;

    public function __construct(
        ExecutionPlanRepository $executionPlanRepository,
        ?PDO $pdo = null
    ) {
        $this->executionPlanRepository = $executionPlanRepository;
        $this->pdo = $pdo;
    }

    public function build(array $formData, string $auditType): array
    {
        $executionPlan = $this->executionPlanRepository
            ->getActiveFor($auditType, $this->pdo);

        return [
            'audit_context' => $this->buildAuditContext($formData, $auditType),
            'auditor_declarations' => $this->extractDeclarations($formData),
            'internal_normative_context' => $this->buildNormativeContext($executionPlan)
        ];
    }

    private function buildAuditContext(array $data, string $auditType): array
    {
        return [
            'audit_type' => $auditType,
            'month' => $data['audit_month'] ?? null,
            'location' => $data['location_id'] ?? null,
        ];
    }

    private function buildNormativeContext(?array $executionPlan): ?array
    {
        if (!$executionPlan) {
            return null;
        }

        return [
            'execution_plan_name'    => $executionPlan['name'] ?? null,
            'execution_plan_version' => $executionPlan['version'] ?? null,
            'execution_plan_hash'    => $executionPlan['hash_fingerprint'] ?? null,
            'normative_summary'      => $executionPlan['normative_summary'] ?? null,
        ];
    }

    private function extractDeclarations(array $data): array
    {
        return [
            'item_id' => $data['item_id'] ?? null,
            'bdgc_quantity' => $data['bdgc_quantity'] ?? null,
            'found_quantity' => $data['found_quantity'] ?? null,
            'divergence_quantity' => $data['divergence_quantity'] ?? null
        ];
    }

    /* ===================== MÉTODO PRINCIPAL DE BUILD DO CONTEXTO PARA ANÁLISE ===================== */
    
    public function build(array $formData, string $auditType): array
{
    $executionPlan = $this->executionPlanRepository
        ->getActiveFor($auditType, $this->pdo);

    return [
        'audit_context' => [
            'audit_type' => $auditType,
            'month' => $formData['audit_month'] ?? null,
            'location' => [
                'id' => $formData['location_id'] ?? null,
                'label' => $formData['location_label'] ?? null
            ]
        ],
        'auditor_declarations' => [
            '_nature' => 'declarative',
            'item_id' => $formData['item_id'] ?? null,
            'bdgc_quantity' => $formData['bdgc_quantity'] ?? null,
            'found_quantity' => $formData['found_quantity'] ?? null,
            'divergence_quantity' => $formData['divergence_quantity'] ?? null
        ],
        'internal_normative_context' => $this->buildNormativeContext($executionPlan)
    ];
}
}