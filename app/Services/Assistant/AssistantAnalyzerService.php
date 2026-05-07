<?php

namespace App\Services\Assistant;

use App\Services\Assistant\PromptTemplates\AuditAnalysisPrompt;
use App\Services\Assistant\ContextBuilder;

final class AssistantAnalyzerService
{
    protected ContextBuilder $contextBuilder;
    protected AuditAnalysisPrompt $prompt;

    public function __construct(
        ContextBuilder $contextBuilder,
        AuditAnalysisPrompt $prompt
    ) {
        $this->contextBuilder = $contextBuilder;
        $this->prompt = $prompt;
    }

    /**
     * Executa a análise principal
     */
    public function analyze(array $data, string $auditType): array
    {
        $context = $this->contextBuilder->build($data, $auditType);

        $prompt = $this->prompt->generate($context);

        return $this->fakeAiResponse($prompt, $context);
    }

    /**
     * Mock de resposta da IA (substituir depois)
     */
    protected function fakeAiResponse(string $prompt, array $context): array
    {
        return [
            'title'  => 'Análise do Chamado',
            'points' => [
                'O texto do chamado é compatível com o tipo de auditoria informado.',
                'Nenhuma incoerência crítica foi identificada no contexto.',
            ],
            'note' => 'Análise simulada gerada com base no contexto e no texto fornecido.'
        ];
    }
}