<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\AuditSession;
use App\Models\AuditSessionItem;
use App\Services\AuditSession\SessionImportService;

class AuditSessionController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Importa chamados para uma sessão
     */
    public function import(): void
    {
        $sessionId = (int) ($_POST['session_id'] ?? 0);

        $session = AuditSession::findById($this->pdo, $sessionId);
        if (!$session) {
            http_response_code(404);
            echo 'Sessão não encontrada';
            return;
        }

        $importService = new SessionImportService();

        if (!empty($_FILES['file']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

            $items = $importService->import(
                $ext,
                $_FILES['file']['tmp_name']
            );
        } else {
            $items = $importService->import(
                'text',
                $_POST['raw_text'] ?? ''
            );
        }

        foreach ($items as $item) {
            AuditSessionItem::insert($this->pdo, [
                'session_id'     => $sessionId,
                'ticket_number'  => $item['ticket_number'],
                'raw_text'       => $item['raw_text'],
                'sn_category'    => $item['sn_category'],
                'sn_service'     => $item['sn_service'],
                'sn_item'        => $item['sn_item'],
                'resolver_group' => $item['resolver_group'],
                'priority'       => $item['priority'],
                'import_source'  => $item['source'],
                'status'         => 'PENDING'
            ]);
        }

        header('Location: /audit-session/view?id=' . $sessionId);
    }

    /**
     * Remove chamado da sessão
     */
    public function removeItem(): void
    {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        AuditSessionItem::remove($this->pdo, $itemId);
        header('Location: ' . $_SERVER['HTTP_REFERER']);
    }

    /**
     * Lista chamados pendentes da sessão
     */
    public function pending(): void
    {
        $sessionId = (int) ($_GET['session_id'] ?? 0);

        $items = AuditSessionItem::listBySession(
            $this->pdo,
            $sessionId,
            'PENDING'
        );

        header('Content-Type: application/json');
        echo json_encode($items);
    }

    /**
 * Exibe a tela da sessão de auditoria
 */
public function view(): void
{
    $sessionId = (int) ($_GET['id'] ?? 0);

    $session = \App\Models\AuditSession::findById($this->pdo, $sessionId);
    if (!$session) {
        http_response_code(404);
        echo 'Sessão não encontrada';
        return;
    }

    $stmt = $this->pdo->prepare(
        'SELECT * FROM audit_session_items
         WHERE session_id = :sid
         ORDER BY created_at DESC'
    );
    $stmt->execute(['sid' => $sessionId]);
    $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    require __DIR__ . '/../Views/audit_session/view.php';
}
}
