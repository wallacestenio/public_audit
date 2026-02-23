<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CatalogRepository;

final class CatalogController
{
    public function __construct(private CatalogRepository $repo) {}

    /**
     * GET /api/catalog?resource=<slug>&q=<texto>
     */
    public function autocomplete(): void
    {
        $this->sendJsonHeaders();

        try {
            $resource = (string)($_GET['resource'] ?? '');
            $q        = $this->sanitizeQ($_GET['q'] ?? '');

            if ($resource === '') {
                $this->badRequest(['error' => 'Missing resource parameter']);
                return;
            }

            switch ($resource) {
                case 'noncompliance-reasons':
                    $data = $this->mapReasons($this->repo->listNoncomplianceReasons($q));
                    break;

                case 'kyndryl-auditors':
                    $data = $this->mapNameList($this->repo->listKyndrylAuditors($q), 'name');
                    break;

                case 'petrobras-inspectors':
                    $data = $this->mapNameList($this->repo->listPetrobrasInspectors($q), 'name');
                    break;

                case 'audited-suppliers':
                    $data = $this->mapNameList($this->repo->listAuditedSuppliers($q), 'name');
                    break;

                case 'locations':
                    $data = $this->mapNameList($this->repo->listLocations($q), 'name');
                    break;

                case 'categories':
                    $data = $this->mapNameList($this->repo->listCategories($q), 'name');
                    break;

                case 'resolver-groups':
                    $data = $this->mapNameList($this->repo->listResolverGroups($q), 'name');
                    break;

                default:
                    $data = [];
                    break;
            }

            $this->ok($data);

        } catch (\Throwable $e) {
            $this->serverError(['error' => 'internal']);
        }
    }

    /* helpers */
    private function sanitizeQ(mixed $q): string
    {
        $s = (string)($q ?? '');
        $s = trim(preg_replace('/\s+/', ' ', $s));
        if (strlen($s) > 120) $s = substr($s, 0, 120);
        return $s;
    }

    private function mapReasons(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $id    = isset($r['id']) ? (int)$r['id'] : 0;
            $label = (string)($r['label'] ?? '');
            $group = (string)($r['group'] ?? 'Outros');
            if ($id > 0 && $label !== '') {
                $out[] = ['id' => $id, 'label' => $label, 'group' => $group ?: 'Outros'];
            }
        }
        return $out;
    }

    private function mapNameList(array $rows, string $nameField = 'name'): array
    {
        $out = [];
        foreach ($rows as $r) {
            $id   = isset($r['id']) ? (int)$r['id'] : 0;
            $name = (string)($r[$nameField] ?? $r['label'] ?? $r['name'] ?? '');
            if ($id > 0 && $name !== '') {
                $out[] = ['id' => $id, 'name' => $name, 'label' => $name];
            }
        }
        return $out;
    }

    private function sendJsonHeaders(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Type: application/json; charset=utf-8');
    }

    private function ok(array $payload): void
    {
        http_response_code(200);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private function badRequest(array $payload): void
    {
        http_response_code(400);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private function serverError(array $payload): void
    {
        http_response_code(500);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}