<?php
declare(strict_types=1);

namespace App\Controllers;


use PDO;
use Throwable;

use App\Models\AuditEntry;
use RuntimeException;
use PDOException;

class AuditController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }



public function form(): void
{
    // ============================
    // 1. Usuário logado (sessão)
    // ============================
    $userId = $_SESSION['user']['id'] ?? null;

    $auditor   = null;
    $inspector = null;
    $location  = null;

    // ============================
    // 2. Buscar contexto humano
    // ============================
    if ($userId) {
        $stmt = $this->pdo->prepare("
            SELECT
                k.kyndryl_auditor,
                p.petrobras_inspector,
                l.location
            FROM kyndryl_auditors AS k
            LEFT JOIN petrobras_inspectors AS p
                ON p.id = k.inspector_id
            LEFT JOIN locations AS l
                ON l.id = k.location_id
            WHERE k.user_id = :uid
            LIMIT 1
        ");

        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $auditor   = ['name' => $row['kyndryl_auditor']];
            $inspector = ['name' => $row['petrobras_inspector']];
            $location  = ['name' => $row['location']];
        }
    }

    // ============================
    // 3. Dados auxiliares
    // ============================
    $categories = $this->loadCategories();
    $suppliers  = $this->loadSuppliers();

    $stmt = $this->pdo->query("
  SELECT id, noncompliance_reason
  FROM noncompliance_reasons
  ORDER BY noncompliance_reason
");

$noncomplianceReasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================
    // 4. Renderização da view
    // ============================
    require __DIR__ . '/../Views/audit/audit-form.php';
    require __DIR__ . '/../Views/layout.php'; // ✅ novo layout
    
}

public function list(): void
{
    $stmt = $this->pdo->query(
        'SELECT
            id,
            ticket_number,
            audit_month,
            category,
            is_compliant
         FROM audit_entries
         ORDER BY id DESC'
    );

    $entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    require __DIR__ . '/../Views/audit/list.php';
}


private function loadCategories(): array
{
    $columns = $this->pdo
        ->query("PRAGMA table_info(categories)")
        ->fetchAll(PDO::FETCH_ASSOC);

    // Assume a primeira coluna TEXT como nome
    $nameColumn = null;

    foreach ($columns as $col) {
        if ($col['type'] === 'TEXT') {
            $nameColumn = $col['name'];
            break;
        }
    }

    if (!$nameColumn) {
        return [];
    }

    $stmt = $this->pdo->query(
        "SELECT {$nameColumn} AS name FROM categories ORDER BY {$nameColumn}"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function view(): void
{
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo 'ID inválido.';
        return;
    }

    $stmt = $this->pdo->prepare(
        'SELECT *
         FROM audit_entries
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([':id' => $id]);
    $entry = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$entry) {
        http_response_code(404);
        echo 'Auditoria não encontrada.';
        return;
    }

    // view dedicada
    require __DIR__ . '/../Views/audit/view.php';

    
 $baseDir = dirname(__DIR__) . '/Views';
    $layout  = $baseDir . '/layout_menu.php'; // ✅

}

/**
 * Exibe tela de confirmação antes de salvar (APKIA)
 */
public function confirm(): void
{


$reasonIds = $data['noncompliance_reason_ids'] ?? [];

$precisaMotivo =
    (int)($data['sla_met'] ?? 1) === 0 ||
    (int)($data['is_compliant'] ?? 1) === 0;

if ($precisaMotivo && empty($reasonIds)) {

    // 👉 NÃO redireciona
    // 👉 NÃO quebra fluxo

    header('Location: /audit/form?erro=missing_nc');
    exit;
}

    if (empty($_SESSION['user']['id'])) {
        echo 'Usuário não autenticado.';
        return;
    }

    $data = $_POST;
    $userId = (int) $_SESSION['user']['id'];

    if (empty($data['ticket_number'])) {
        echo 'Dados inválidos.';
        return;
    }

    $auditEntry = new AuditEntry($this->pdo);

    // ✅ Contexto humano (igual ao save)
    $context = $auditEntry->getInspectorAndLocationByAuditor($userId);

    // ✅ Resolver fornecedor (ID → nome)
    $auditedSupplierName = null;
    if (!empty($data['audited_supplier'])) {
        $stmt = $this->pdo->prepare(
            'SELECT audited_supplier FROM audited_suppliers WHERE id = :id'
        );
        $stmt->execute([':id' => (int) $data['audited_supplier']]);
        $auditedSupplierName = $stmt->fetchColumn() ?: null;
    }

    // ✅ Resolver não conformidades
    $reasonIds = $data['noncompliance_reason_ids'] ?? [];
    $reasonIdsCsv = is_array($reasonIds)
        ? implode(',', array_map('intval', $reasonIds))
        : null;

    $reasonLabels = null;
    if (!empty($reasonIdsCsv)) {
        $stmt = $this->pdo->prepare(
            'SELECT GROUP_CONCAT(noncompliance_reason, "; ")
             FROM noncompliance_reasons
             WHERE id IN (' . $reasonIdsCsv . ')'
        );
        $stmt->execute();
        $reasonLabels = $stmt->fetchColumn() ?: null;
    }

    $reasonIds = $data['noncompliance_reason_ids'] ?? [];

$precisaMotivo =
    (int)($data['sla_met'] ?? 1) === 0 ||
    (int)($data['is_compliant'] ?? 1) === 0;

if ($precisaMotivo && empty($reasonIds)) {
    http_response_code(422);

    echo '<div id="erro-nc" style="color:red; display:none; margin-top:8px;">';
    echo 'Selecione ao menos um motivo de não conformidade antes de continuar.';
    echo '</div>';

    return;
}

    // ✅ Monta estrutura IDÊNTICA à audit_entries
    $entry = [
        'ticket_number'            => $data['ticket_number'],
        'ticket_type'              => $data['ticket_type'],
        'audit_month'              => $data['audit_month'],
        'priority'                 => $data['priority'],
        'kyndryl_auditor'           => $context['kyndryl_auditor'] ?? null,
        'petrobras_inspector'       => $context['petrobras_inspector'] ?? null,
        'location'                 => $context['location'] ?? null,
        'audited_supplier'          => $auditedSupplierName,
        'category'                 => $data['category'],
        'resolver_group'           => $data['resolver_group'],
        'sla_met'                  => (int) $data['sla_met'],
        'is_compliant'             => (int) $data['is_compliant'],
        'noncompliance_reason_ids' => $reasonIdsCsv,
        'noncompliance_reasons'    => $reasonLabels,
    ];

    require __DIR__ . '/../Views/audit/confirm.php';
    require __DIR__ . '/../Views/layout.php';
    
}

    /**
     * POST /audit/save
     * Salva auditoria de chamados (decisão do auditor)
     */
    
public function save(): void
{
    try {
        if (empty($_SESSION['user']['id'])) {
            throw new RuntimeException('Usuário não autenticado.');
        }

        $userId = (int) $_SESSION['user']['id'];

        $auditEntry = new AuditEntry($this->pdo);

        $context = $auditEntry->getInspectorAndLocationByAuditor($userId);

// Fornecedor (ID → NOME)
$auditedSupplierName = $_POST['audited_supplier'] ?? null;

// Não conformidades (já resolvidas na confirmação)
$reasonIdsCsv = $_POST['noncompliance_reason_ids'] ?? null;
$reasonLabels = $_POST['noncompliance_reasons'] ?? null;

$entryId = $auditEntry->insertSingle([
    'user_id'                  => $userId,
    'ticket_number'            => $_POST['ticket_number'],
    'ticket_type'              => $_POST['ticket_type'],
    'kyndryl_auditor'           => $context['kyndryl_auditor'] ?? null,
    'petrobras_inspector'       => $context['petrobras_inspector'] ?? null,
    'audited_supplier'          => $auditedSupplierName,
    'location'                 => $context['location'] ?? null,
    'audit_month'              => $_POST['audit_month'],
    'priority'                 => $_POST['priority'],
    'category'                 => $_POST['category'],
    'resolver_group'           => $_POST['resolver_group'],
    'sla_met'                  => $_POST['sla_met'],
    'is_compliant'             => $_POST['is_compliant'],
    'noncompliance_reason_ids' => $reasonIdsCsv,
    'noncompliance_reasons'    => $reasonLabels,
]);


        header('Location: /audit/view?id=' . $entryId);
        
        exit;

    } catch (RuntimeException $e) {
        http_response_code(400);
        echo $e->getMessage();

    } catch (PDOException $e) {
        http_response_code(409);
        echo 'Erro ao salvar a auditoria (possível ticket duplicado).';

    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Erro interno ao salvar a auditoria.';
    }
}





private function loadSuppliers(): array
{
    $stmt = $this->pdo->query(
        "SELECT
            id,
            audited_supplier AS name
         FROM audited_suppliers
         ORDER BY audited_supplier"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

                
private function error(string $message): void
    {
        http_response_code(422);
        echo '<div class="alert alert-danger">';
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '</div>';
        exit;
       }
}