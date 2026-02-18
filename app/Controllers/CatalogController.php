<?php
namespace App\Controllers;

use App\Core\Response;
use App\Repositories\CatalogRepository;

class CatalogController
{
    public function __construct(private CatalogRepository $repo) {}

    public function autocomplete(): void
    {
        $resource = $_GET['resource'] ?? '';
        $q = trim($_GET['q'] ?? '');
        Response::json($this->repo->autocomplete($resource, $q));
    }
}