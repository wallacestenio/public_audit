<?php
namespace App\Controllers;

use App\Core\Response;
use App\Repositories\CatalogRepository;

class CatalogController
{
    public function __construct(private CatalogRepository $repo) {}

   
// App/Controllers/CatalogController.php (trecho — garantir retorno padrão)
public function autocomplete(): void
{
    header('Content-Type: application/json; charset=UTF-8');

    $resource = (string)($_GET['resource'] ?? '');
    $q        = trim((string)($_GET['q'] ?? ''));

    try {
        switch ($resource) {
            case 'noncompliance-reasons': {
                // Mantém o comportamento/payload atuais (compatibilidade)
                $rows = $this->repo->listNoncomplianceReasons($q);
                echo json_encode($rows, JSON_UNESCAPED_UNICODE);
                return;
            }

            case 'kyndryl-auditors': {
                if (method_exists($this->repo, 'kyndrylAuditors')) {
                    $rows = $this->repo->kyndrylAuditors($q);
                    $std  = array_map(static fn($r) => [
                        'id'   => isset($r['id']) ? (int)$r['id'] : (int)($r['value'] ?? 0),
                        'name' => (string)($r['name'] ?? $r['kyndryl_auditor'] ?? $r['label'] ?? ''),
                    ], is_array($rows) ? $rows : []);
                    echo json_encode($std, JSON_UNESCAPED_UNICODE);
                    return;
                }
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                return;
            }

            case 'petrobras-inspectors': {
                if (method_exists($this->repo, 'petrobrasInspectors')) {
                    $rows = $this->repo->petrobrasInspectors($q);
                    $std  = array_map(static fn($r) => [
                        'id'   => isset($r['id']) ? (int)$r['id'] : (int)($r['value'] ?? 0),
                        'name' => (string)($r['name'] ?? $r['petrobras_inspector'] ?? $r['label'] ?? ''),
                    ], is_array($rows) ? $rows : []);
                    echo json_encode($std, JSON_UNESCAPED_UNICODE);
                    return;
                }
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                return;
            }

            case 'audited-suppliers': {
                if (method_exists($this->repo, 'auditedSuppliers')) {
                    $rows = $this->repo->auditedSuppliers($q);
                    $std  = array_map(static fn($r) => [
                        'id'   => isset($r['id']) ? (int)$r['id'] : (int)($r['value'] ?? 0),
                        'name' => (string)($r['name'] ?? $r['audited_supplier'] ?? $r['label'] ?? ''),
                    ], is_array($rows) ? $rows : []);
                    echo json_encode($std, JSON_UNESCAPED_UNICODE);
                    return;
                }
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                return;
            }

            case 'locations': {
                if (method_exists($this->repo, 'locations')) {
                    $rows = $this->repo->locations($q);
                    $std  = array_map(static fn($r) => [
                        'id'   => isset($r['id']) ? (int)$r['id'] : 0,
                        'name' => (string)($r['name'] ?? $r['location'] ?? $r['label'] ?? ''),
                    ], is_array($rows) ? $rows : []);
                    echo json_encode($std, JSON_UNESCAPED_UNICODE);
                    return;
                }
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                return;
            }

            case 'categories': {
                if (method_exists($this->repo, 'categories')) {
                    $rows = $this->repo->categories($q);
                    $std  = array_map(static fn($r) => [
                        'id'   => isset($r['id']) ? (int)$r['id'] : 0,
                        'name' => (string)($r['name'] ?? $r['category'] ?? $r['label'] ?? ''),
                    ], is_array($rows) ? $rows : []);
                    echo json_encode($std, JSON_UNESCAPED_UNICODE);
                    return;
                }
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                return;
            }

            case 'resolver-groups': {
                if (method_exists($this->repo, 'resolverGroups')) {
                    $rows = $this->repo->resolverGroups($q);
                    $std  = array_map(static fn($r) => [
                        'id'   => isset($r['id']) ? (int)$r['id'] : 0,
                        'name' => (string)($r['name'] ?? $r['resolver_group'] ?? $r['label'] ?? ''),
                    ], is_array($rows) ? $rows : []);
                    echo json_encode($std, JSON_UNESCAPED_UNICODE);
                    return;
                }
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                return;
            }

            default: {
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                return;
            }
        }
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro no catálogo'], JSON_UNESCAPED_UNICODE);
        }
    }
}