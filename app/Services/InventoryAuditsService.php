<?php
declare(strict_types=1);

namespace App\Services;

use PDO;               // ✅ IMPORTANTE
use DomainException;   // ✅ IMPORTANTE

use App\Services\PeriodNormalizer;

class InventoryAuditsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

     /**
     * Cria uma Auditoria de Estoque (via FORM ou IMPORT)
     */
    public function create(array $data): void
    {
        // ==========================
        // Validações mínimas
        // ==========================
        if (empty($data['audit_month'])) {
            throw new DomainException('Mês da auditoria é obrigatório.');
        }

        if (empty($data['item_id'])) {
            throw new DomainException('Item auditado é obrigatório.');
        }

        if (empty($data['location_id'])) {
            throw new DomainException('Localidade é obrigatória.');
        }

        if (empty($data['auditor_user_id'])) {
            throw new DomainException('Auditor é obrigatório.');
        }
$auditMonth = PeriodNormalizer::normalizeMonthYear(
    (string) $data['audit_month']
);

        if ($auditMonth === null) {
            throw new DomainException('Formato inválido para o mês da auditoria.');
        }

        // ==========================
        // Normalização dos números
        // ==========================
        $bdgc = $this->toNullableInt($data['bdgc_quantity'] ?? null);
        $found = $this->toNullableInt($data['found_quantity'] ?? null);
        $divergence = $this->toNullableInt($data['divergence_quantity'] ?? null);

        // ==========================
        // Inserção no banco
        // ==========================
        $sql = "
            INSERT INTO inventory_audits (
                audit_month,
                item_id,
                location_id,
                auditor_user_id,
                bdgc_quantity,
                found_quantity,
                divergence_quantity,
                divergence_notes,
                ra_ro,
                created_via,
                import_batch_id
            ) VALUES (
                :audit_month,
                :item_id,
                :location_id,
                :auditor_user_id,
                :bdgc_quantity,
                :found_quantity,
                :divergence_quantity,
                :divergence_notes,
                :ra_ro,
                :created_via,
                :import_batch_id
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':audit_month'         => $auditMonth,
            ':item_id'             => (int)$data['item_id'],
            ':location_id'         => (int)$data['location_id'],
            ':auditor_user_id'     => (int)$data['auditor_user_id'],
            ':bdgc_quantity'       => $bdgc,
            ':found_quantity'      => $found,
            ':divergence_quantity' => $divergence,
            ':divergence_notes'    => $data['divergence_notes'] ?? null,
            ':ra_ro'               => $data['ra_ro'] ?? null,

            // 🔒 controle de origem
            ':created_via'         => 'manual',
            ':import_batch_id'     => null,
        ]);
    }

    // ==================================================
    // Helpers privados
    // ==================================================

    /**
     * Normaliza entradas como:
     *  - "abril 2026"
     *  - "04/2026"
     *  - "2026-04"
     * para: MM-YYYY
     */
    private function normalizeAuditMonth(string $input): ?string
    {
        $input = trim(mb_strtolower($input));

        // 2026-04
        if (preg_match('/^(\d{4})-(\d{2})$/', $input, $m)) {
            return $m[2] . '-' . $m[1];
        }

        // 04/2026
        if (preg_match('/^(\d{2})\/(\d{4})$/', $input, $m)) {
            return $m[1] . '-' . $m[2];
        }

        // abril 2026
        $months = [
            'janeiro' => '01', 'fevereiro' => '02', 'março' => '03',
            'abril' => '04', 'maio' => '05', 'junho' => '06',
            'julho' => '07', 'agosto' => '08', 'setembro' => '09',
            'outubro' => '10', 'novembro' => '11', 'dezembro' => '12',
        ];

        foreach ($months as $name => $num) {
            if (preg_match('/^' . $name . '\s+(\d{4})$/', $input, $m)) {
                return $num . '-' . $m[1];
            }
        }

        return null;
    }

    private function toNullableInt($value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return (int)$value;
    }
}