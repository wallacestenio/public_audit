<?php

namespace App\Core;

use App\Services\Assistant\AssistantAnalyzerService;
use App\Services\Assistant\ContextBuilder;
use App\Services\Assistant\PromptTemplates\AuditAnalysisPrompt;
use App\Repositories\ExecutionPlanRepository;
use PDO;

class ServiceFactory
{
    public static function makeAssistantAnalyzer(): AssistantAnalyzerService
    {
        // conexão PDO (ajuste conforme seu projeto)
        $pdo = new PDO('sqlite:database.sqlite');

        $repository = new ExecutionPlanRepository();
        $contextBuilder = new ContextBuilder($repository, $pdo);
        $prompt = new AuditAnalysisPrompt();

        return new AssistantAnalyzerService($contextBuilder, $prompt);
    }
}