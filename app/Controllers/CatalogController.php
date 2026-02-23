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
    
        header('Content-Type: application/json; charset=utf-8');
        $resource = (string)($_GET['resource'] ?? '');
        $q        = trim((string)($_GET['q'] ?? ''));

        if ($resource !== 'noncompliance-reasons') {
            echo json_encode([], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = $this->repo->listNoncomplianceReasons($q);
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
     }

}