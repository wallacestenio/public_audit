<?php
declare(strict_types=1);

namespace App\Services\APKIA;


    use PDO;
    use App\Repositories\ExecutionPlanRepository;
    use RuntimeException;

    class APKIAService
    {
        private PDO $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /* =========================================================================
        * CONTEXTO DO PLANO DE EXECUÇÃO (RÉGUA)
        * ========================================================================= */
        public function buildContextFromActivePlan(): array
        {
            $plan = ExecutionPlanRepository::getActive($this->pdo);

            if (!$plan) {
                throw new RuntimeException(
                    'Nenhum Plano de Execução ativo encontrado.'
                );
            }

            return [
                'plan' => [
                    'id'         => $plan['id'],
                    'name'       => $plan['name'],
                    'version'    => $plan['version'],
                    'audit_type' => $plan['audit_type'],
                    'status'     => $plan['status'],
                    'activated_at' => $plan['activated_at'],
                ],
                'normative_summary' => trim($plan['normative_summary'] ?? ''),
                'meta' => [
                    'context_built_at' => date('Y-m-d H:i:s'),
                ]
            ];
        }

        /* =========================================================================
        * MOCK COMPLETO DE ANÁLISE (EXTRAÇÃO + PE + SUGESTÕES)
        * ========================================================================= */
    
public function analyzeText(array|string $data): array
{
    if (is_string($data)) {
        $data = [
            'sn_category' => null,
            'sn_service'  => null,
            'sn_item'     => null,
            'raw_text'    => $data,
        ];
    }

    $rawText = $data['raw_text'] ?? '';


        // =====================================================
        // 1. CONTEXTO DO PLANO DE EXECUÇÃO
        // =====================================================
        $context = $this->buildContextFromActivePlan();

        

        // Estrutura base do resultado
        $result = [
            'suggestions' => [],
        ];

        // =====================================================
    // MARCAÇÃO DE CONTEXTO DA PE
    // =====================================================
    $result['pe_loaded'] = true;                 // a PE existe e foi carregada
    $result['pe_chapters_evaluated'] = [];       // capítulos da PE que foram avaliados


        // =====================================================
        // 2. NÚMERO + TIPO DO CHAMADO
        // =====================================================
        if (preg_match('/\b(SCTASK|TASK|INC|RITM)(\d{6,})\b/i', $rawText, $m)) {
            $prefix = strtoupper($m[1]);
            $result['ticket_number'] = $prefix . $m[2];

            if ($prefix === 'INC') {
                $result['ticket_type'] = 'INCIDENTE';
            } elseif ($prefix === 'RITM') {
                $result['ticket_type'] = 'REQUISIÇÃO';
            } else {
                $result['ticket_type'] = 'TASK';
            }
        }

        // =====================================================
        // 3. GRUPO DE ATRIBUIÇÃO
        // =====================================================
        if (preg_match('/\bN\d-[A-Z0-9\-]+\b/', $rawText, $m)) {
            $result['resolver_group'] = $m[0];
        }

// =====================================================// =================================================4. CATEGORIA (Categoria + Serviço + Item)
// =====================================================

// 4.1 Extrair partes do texto (ServiceNow)
// =====================================================
// 4. CATEGORIA (HÍBRIDO: estruturado + fallback controlado)
// =====================================================

// =====================================================
// 4. CATEGORIA (ROBUSTO: estruturado + texto com peso baixo)
// =====================================================

// =====================================================
// 4. CATEGORIA (ROBUSTO: estruturado + texto com peso baixo)
// =====================================================


// fallback do texto (peso menor)
$textTokens = $this->tokenize($rawText);

foreach ($textTokens as $t) {
    $allTokens[$t] = ($allTokens[$t] ?? 0) + 1;
}


/**
 * 2️⃣ Fonte fraca — SEMPRE usar o texto normalizado como fallback
 * (não decide sozinho, só ajuda)
 */
$normalizedText = $this->normalizeText($rawText);



// =====================================================
// 4. CATEGORIA (NOVA LÓGICA INTELIGENTE)
// =====================================================

$allTokens = [];

/**
 * 1️⃣ Fonte forte — categoria, serviço, item
 */
foreach (['sn_category', 'sn_service', 'sn_item'] as $key) {
    if (!empty($data[$key])) {
        $tokens = $this->tokenize($data[$key]);

        foreach ($tokens as $t) {
            $allTokens[$t] = ($allTokens[$t] ?? 0) + 5;
        }
    }
}

/**
 * 2️⃣ Fonte auxiliar — texto completo
 */
$textTokens = $this->tokenize($rawText);

foreach ($textTokens as $t) {
    $allTokens[$t] = ($allTokens[$t] ?? 0) + 1;
}

/**
 * Matching com banco
 */
$stmt = $this->pdo->query("SELECT category FROM categories");
$dbCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bestMatch = null;
$bestScore = 0;

foreach ($dbCategories as $cat) {

    $catTokens = $this->tokenize($cat['category']);

    $score = 0;

    foreach ($catTokens as $t) {
        if (isset($allTokens[$t])) {
            $score += $allTokens[$t];
        }
    }

    $coverage = count(array_intersect($catTokens, array_keys($allTokens)));
    $score += $coverage * 2;

    if ($score > $bestScore) {
        $bestScore = $score;
        $bestMatch = $cat['category'];
    }
}

/**
 * Decisão final
 */
if ($bestMatch && $bestScore >= 10) {
    $result['category'] = $bestMatch;

    $this->addSuggestion(
        $result['suggestions'],
        'INFO',
        'Categoria sugerida com base em múltiplas fontes (categoria, serviço e item).',
        'CAT-AUTO-ROBUST'
    );
} else {
    $this->addSuggestion(
        $result['suggestions'],
        'INFO',
        'Categoria não encontrada com confiança suficiente. Selecionar manualmente.',
        'CAT-MANUAL'
    );
}



// =====================================================
// Matching contra categorias oficiais
// =====================================================

$stmt = $this->pdo->query("SELECT category FROM categories");
$dbCategories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$bestMatch = null;
$bestScore = 0;

foreach ($dbCategories as $cat) {
    $catNorm   = $this->normalizeText($cat['category']);
    $catTokens = array_filter(explode(' ', $catNorm));

    $score = 0;

    

    if ($score > $bestScore) {
        $bestScore = $score;
        $bestMatch = $cat['category'];
    }
}

// DEBUG FINAL
error_log("APKIA DEBUG - BEST MATCH: {$bestMatch} | SCORE={$bestScore}");

// =====================================================
// Decisão final
// =====================================================

if ($bestMatch !== null && $bestScore >= 3) {
    $result['category'] = $bestMatch;

    $this->addSuggestion(
        $result['suggestions'],
        'INFO',
        'Categoria sugerida automaticamente com base na análise do chamado.',
        'CAT-AUTO-01'
    );
} else {
    $this->addSuggestion(
        $result['suggestions'],
        'INFO',
        'Não foi possível determinar a categoria com segurança. Selecione manualmente.',
        'CAT-MANUAL-01'
    );
}

$result['pe_chapters_evaluated']['3.1'] = true;

// =====================================================
// 5. PRIORIDADE (ROBUSTO + FALLBACK INTELIGENTE)
// =====================================================

// default (seguro)
$priority = 3;

// 🔹 1. tenta extrair do texto
if (preg_match('/\bPrioridade\s*[:\-]?\s*(\d)\b/i', $rawText, $m)) {
    $priority = (int)$m[1];
}

// 🔹 2. normaliza limites (AGORA INCLUI 5 ✅)
$priority = max(1, min($priority, 5));


// 🔹 3. fallback inteligente (quando não veio explícito)
if (!isset($m)) {

    $text = $this->normalizeText($rawText);

    if (str_contains($text, 'emergencia') || str_contains($text, 'critico')) {
        $priority = 1;
    }
    elseif (str_contains($text, 'inoperante') || str_contains($text, 'parado')) {
        $priority = 2;
    }
    elseif (str_contains($text, 'lento') || str_contains($text, 'instavel')) {
        $priority = 3;
    }
    elseif (str_contains($text, 'verificar') || str_contains($text, 'ajuste')) {
        $priority = 4;
    }
    else {
        // ✅ AQUI ENTRA A PRIORIDADE 5
        $priority = 5;
    }
}

// 🔹 4. salva no resultado
$result['priority'] = $priority;


if ($priority === 5 && !isset($m)) {
    $this->addSuggestion(
        $result['suggestions'],
        'INFO',
        'Prioridade baixa inferida automaticamente (sem indícios de impacto crítico).',
        'PRIORITY-AUTO-LOW'
    );
}



        


        
    // =====================================================
    // 6. DATA DE ENCERRAMENTO → YYYY-MM (PRIORITÁRIA)
    // =====================================================
    if (
    preg_match(
        '/Status.*Encerrad[oa].*?(\d{2})\/(\d{2})\/(\d{4})/is',
        $rawText,
        $m
    )
) {
    $result['audit_month'] = $m[3] . '-' . $m[2];
}

    // =====================================================
    // 6. DATA → YYYY-MM (FALLBACK)
    // =====================================================
    if (!isset($result['audit_month']) &&
        preg_match_all('/(\d{2})\/(\d{2})\/(\d{4})/', $rawText, $dates, PREG_SET_ORDER)
    ) {
        $latestTs = null;
        $latestYm = null;

        foreach ($dates as $d) {
            $ts = strtotime($d[3] . '-' . $d[2] . '-' . $d[1]);
            if ($ts && ($latestTs === null || $ts > $latestTs)) {
                $latestTs = $ts;
                $latestYm = $d[3] . '-' . $d[2];
            }
        }

        if ($latestYm) {
            $result['audit_month'] = $latestYm;
        }
    }

    // =====================================================
// 7. SLA / ANS (LÓGICA CANÔNICA - DEFINITIVA)
// =====================================================

// Por padrão: SLA atingido
$result['sla_met'] = 1;


// procura especificamente "Violado Verdadeiro"
if (preg_match('/Violado\s*[\r\n\t ]*\s*Verdadeiro/i', $rawText)) {

    $result['sla_met'] = 0;

    $this->addSuggestion(
        $result['suggestions'],
        'CRITICAL',
        'ANS de tarefas violado conforme tabela de ANS do ServiceNow.',
        'SLA-VIOL-01'
    );

    // Capítulo correto da PE
    $result['pe_chapters_evaluated']['4.2'] = true;
}
    
    


        // =====================================================
        // 8. REGRAS DA PE (FLUXO / PAUSA)
        // =====================================================
        if (
            stripos($rawText, 'Pausa em andamento') !== false &&
            stripos($rawText, 'Encerrado') !== false
        ) {
            $this->addSuggestion(
                $result['suggestions'],
                'CRITICAL',
                'Fluxo indica transição direta de "Pausa em andamento" para "Encerrado". Verificar aderência à PE.',
                'PE-FLOW-01'
            );
        }

        if (
            stripos($rawText, 'Pausa em andamento') !== false &&
            stripos($rawText, 'justific') === false
        ) {
            $this->addSuggestion(
                $result['suggestions'],
                'WARNING',
                'Chamado entrou em pausa sem justificativa textual clara.',
                'PE-PAUSE-01'
            );
        }

        // =====================================================
        // 9. ENRIQUECIMENTO COM PE + SCORE
        // =====================================================
        $result['suggestions'] =
            $this->enrichSuggestionsWithPE($result['suggestions']);

        $risk = $this->calculateRiskScore($result['suggestions']);
        $result['risk_score'] = $risk['score'];
        $result['risk_level'] = $risk['level'];

        $result['risk_by_pe_chapter'] =
            $this->calculateRiskByChapter($result['suggestions']);

            // =====================================================
    // SINAL EXPLÍCITO DE COMPREENSÃO DA PE
    // =====================================================
    $result['pe_understood'] = (
        $result['pe_loaded'] === true
        && count($result['pe_chapters_evaluated']) > 0
    );

        // =====================================================
        // 10. RETORNO FINAL
        // =====================================================
        return [
            'title'   => 'Análise segundo o Plano ' . $context['plan']['name'],
            'note'    =>
                '⚠️ Esta análise é apenas um apoio. O julgamento final permanece sob responsabilidade do auditor.',
            'summary' => array_map(
                fn ($s) => '[ ' . $s['severity'] . ' ] ' . $s['message'],
                $result['suggestions']
            ),
            ...$result,
            'audit_context' => [
                'plan_id'   => $context['plan']['id'],
                'plan_name' => $context['plan']['name'],
                'version'   => $context['plan']['version'],
                'audit_type'=> $context['plan']['audit_type'],
            ]
        ];
    }

        private function addSuggestion(
        array &$suggestions,
        string $severity,
        string $message,
        ?string $rule = null
    ): void {
        $suggestions[] = [
            'severity' => $severity,
            'message'  => $message,
            'rule'     => $rule,
        ];
    }

    private function calculateRiskScore(array $suggestions): array
    {
        $score = 0;

        foreach ($suggestions as $s) {
            $score += match ($s['severity']) {
                'CRITICAL' => 5,
                'WARNING'  => 3,
                'INFO'     => 1,
                default    => 0
            };
        }

        $level = match (true) {
            $score >= 7 => 'ALTO',
            $score >= 3 => 'MODERADO',
            default     => 'BAIXO'
        };

        return [
            'score' => $score,
            'level' => $level
        ];
    }

    private function getRuleMap(): array
    {
        return [
            'SLA-CRIT-01' => [
                'chapter' => '4.2',
                'title'   => 'Gestão de SLA / ANS',
                'desc'    => 'ANS não pode ser gerido artificialmente nem estourar limites acordados.'
            ],

            'PE-FLOW-01' => [
                'chapter' => '5.1',
                'title'   => 'Fluxo de Status',
                'desc'    => 'Chamados não podem ser encerrados diretamente a partir de pausa.'
            ],

            'PE-PAUSE-01' => [
                'chapter' => '5.3',
                'title'   => 'Uso de Pausa',
                'desc'    => 'Toda pausa deve conter justificativa registrada.'
            ],

            'CAT-VAL-01' => [
                'chapter' => '3.1',
                'title'   => 'Classificação do Chamado',
                'desc'    => 'Categoria deve estar aderente ao catálogo oficial.'
            ],
        ];
    }

    private function enrichSuggestionsWithPE(array $suggestions): array
    {
        $ruleMap = $this->getRuleMap();

        foreach ($suggestions as &$s) {
            if (!empty($s['rule']) && isset($ruleMap[$s['rule']])) {
                $s['pe'] = [
                    'chapter' => $ruleMap[$s['rule']]['chapter'],
                    'title'   => $ruleMap[$s['rule']]['title'],
                    'desc'    => $ruleMap[$s['rule']]['desc'],
                ];
            }
        }

        return $suggestions;
    }

    
 private function normalizeText(string $text): string
{
    if ($text === '') {
        return '';
    }

    // Garantir string
    $text = (string) $text;

    // Converter para UTF-8 válido (tolerante)
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    // Tentar transliteração, mas sem permitir erro fatal
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

    if ($converted !== false) {
        $text = $converted;
    }

    // Normalização segura
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? '';
    $text = preg_replace('/\s+/', ' ', $text) ?? '';

    return trim($text);
}


    private function calculateRiskByChapter(array $suggestions): array
    {
        $scores = [];

        foreach ($suggestions as $s) {
            if (!isset($s['pe']['chapter'])) {
                continue;
            }

            $chapter = $s['pe']['chapter'];

            $weight = match ($s['severity']) {
                'CRITICAL' => 5,
                'WARNING'  => 3,
                'INFO'     => 1,
                default    => 0
            };

            $scores[$chapter] = ($scores[$chapter] ?? 0) + $weight;
        }

        return $scores;
    }

    
private function tokenize(string $text): array
{
    $text = $this->normalizeText($text);
    return array_values(array_filter(explode(' ', $text)));
}

    }