<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\InventoryAuditsService;
use App\Repositories\CatalogRepository;
use App\Repositories\LocationRepository;
use App\Support\Redirect;
use App\Support\Csrf;
use DomainException;
use Throwable;



class InventoryAuditsController
{
    private InventoryAuditsService $service;
    private CatalogRepository $catalogRepo;
    private LocationRepository $locationRepo;

    public function __construct(
        InventoryAuditsService $service,
        CatalogRepository $catalogRepo,
        LocationRepository $locationRepo
    ) {
        $this->service      = $service;
        $this->catalogRepo  = $catalogRepo;
        $this->locationRepo = $locationRepo;
    }


    /* ===================== ROTA DE LISTAGEM (REDIRECIONA PARA O FORMULÁRIO) ===================== */
    
    public function index(): void
{
    // Por enquanto, só redireciona para o formulário
    header('Location: /inventory-audits/create');
    exit;
}


    /**
     * GET /inventory-audits/create
     * Exibe o formulário de Auditoria de Estoque
     */

public function create(): void
{
    $inventoryItems = $this->catalogRepo->listInventoryItems();
    $locations      = $this->locationRepo->all();

    // 🔑 contrato do layout.php
    $title = 'Auditoria de Estoque';
    $view  = 'inventory_audits/create';

    require __DIR__ . '/../Views/layout.php';
}



    /**
     * POST /inventory-audits
     * Salva a auditoria de estoque
     */
 public function store(): void
{
    try {
        Csrf::validate($_POST['csrf_token'] ?? null);

        $this->service->create([
            'audit_month'         => $_POST['audit_month']         ?? null,
            'item_id'             => $_POST['item_id']             ?? null,
            'location_id'         => $_POST['location_id']         ?? null,
            'auditor_user_id'     => $_POST['auditor_user_id']     ?? null,
            'bdgc_quantity'       => $_POST['bdgc_quantity']       ?? null,
            'found_quantity'      => $_POST['found_quantity']      ?? null,
            'divergence_quantity' => $_POST['divergence_quantity'] ?? null,
            'divergence_notes'    => $_POST['divergence_notes']    ?? null,
            'ra_ro'               => $_POST['ra_ro']               ?? null,
        ]);

        Redirect::to('/inventory-audits/create', [
            'success' => 'Auditoria de estoque registrada com sucesso.'
        ]);

    } catch (DomainException $e) {
        Redirect::back([
            'error' => $e->getMessage(),
            'old'   => $_POST
        ]);

    } catch (Throwable $e) {
        Redirect::back([
            'error' => 'Erro inesperado ao salvar auditoria.',
            'old'   => $_POST
        ]);
    }
}
}