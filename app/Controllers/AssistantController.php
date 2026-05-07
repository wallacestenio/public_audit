<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\APKIA\APKIAService;
use App\Services\Assistant\ContextBuilder;

final class AssistantController
{
 public function analyze(): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'analysis' => [
            'title' => 'Teste OK',
            'points' => ['Resposta JSON limpa'],
            'note' => 'Backend funcionando'
        ]
    ]);
    exit;
}
}