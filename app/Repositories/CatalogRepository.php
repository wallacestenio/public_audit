<?php
namespace App\Repositories;

final class CatalogRepository
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Retorna lista para o front: [{id, label, group}, ...]
     * - label = noncompliance_reason
     * - group = coluna "group" (aspas por ser palavra reservada em SQL)
     */
    public function listNoncomplianceReasons(string $q = ''): array
    {
        // IMPORTANTE: dentro da string PHP (aspas simples), escape de aspas simples é \'
        $sql = 'SELECT id,
                       noncompliance_reason AS label,
                       COALESCE("group", \'Outros\') AS "group"
                FROM noncompliance_reasons';
        $params = [];

        if ($q !== '') {
            $sql .= ' WHERE noncompliance_reasons.noncompliance_reason LIKE :q OR "group" LIKE :q';
            $params[':q'] = "%{$q}%";
        }

        $sql .= ' ORDER BY "group" ASC, label ASC';

        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
    /** Retorna auditores (com filtro opcional por q). */
public function kyndrylAuditors(string $q = ''): array
{
    $sql = "SELECT id, kyndryl_auditor AS name
            FROM kyndryl_auditors";
    $params = [];
    if ($q !== '') {
        $sql .= " WHERE kyndryl_auditor LIKE :q";
        $params[':q'] = '%' . $q . '%';
    }
    $sql .= " ORDER BY kyndryl_auditor ASC LIMIT 50";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    return array_map(static fn($r) => [
        'id'   => (int)$r['id'],
        'name' => (string)$r['name'],
    ], $rows);
}

public function petrobrasInspectors(string $q = ''): array
{
    $sql = "SELECT id, petrobras_inspector FROM petrobras_inspectors";
    $params = [];
    if ($q !== '') {
        $sql .= " WHERE petrobras_inspector LIKE :q";
        $params[':q'] = '%' . $q . '%';
    }
    $sql .= " ORDER BY petrobras_inspector ASC LIMIT 50";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    // Padroniza para { id, name } (igual ao endpoint de auditores)
    return array_map(static fn($r) => [
        'id'   => (int)$r['id'],
        'name' => (string)$r['petrobras_inspector'],
    ], $rows);
}

public function auditedSuppliers(string $q = ''): array
{
    $sql = "SELECT id, audited_supplier FROM audited_suppliers";
    $params = [];
    if ($q !== '') {
        $sql .= " WHERE audited_supplier LIKE :q";
        $params[':q'] = '%' . $q . '%';
    }
    $sql .= " ORDER BY audited_supplier ASC LIMIT 50";

    // ajuste $this->pdo se no seu repo o PDO tiver outro nome
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    // padroniza payload
    return array_map(static fn($r) => [
        'id'   => (int)$r['id'],
        'name' => (string)$r['audited_supplier'],
    ], $rows);
}
public function locations(string $q = ''): array
{
    $sql = "SELECT id, location FROM locations";
    $params = [];
    if ($q !== '') {
        $sql .= " WHERE location LIKE :q";
        $params[':q'] = '%' . $q . '%';
    }
    $sql .= " ORDER BY location ASC LIMIT 50";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    return array_map(static fn($r) => [
        'id'   => (int)$r['id'],
        'name' => (string)$r['location'],
    ], $rows);
}

public function categories(string $q = ''): array
{
    $sql = "SELECT id, category FROM categories";
    $params = [];
    if ($q !== '') {
        $sql .= " WHERE category LIKE :q";
        $params[':q'] = '%' . $q . '%';
    }
    $sql .= " ORDER BY category ASC LIMIT 50";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    return array_map(static fn($r) => [
        'id'   => (int)$r['id'],
        'name' => (string)$r['category'],
    ], $rows);
}

public function resolverGroups(string $q = ''): array
{
    $sql = "SELECT id, resolver_group FROM resolver_groups";
    $params = [];
    if ($q !== '') {
        $sql .= " WHERE resolver_group LIKE :q";
        $params[':q'] = '%' . $q . '%';
    }
    $sql .= " ORDER BY resolver_group ASC LIMIT 50";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    return array_map(static fn($r) => [
        'id'   => (int)$r['id'],
        'name' => (string)$r['resolver_group'],
    ], $rows);
}


/** Verifica se o auditor existe pelo nome exato; retorna id ou null. */
public function findKyndrylAuditorIdByName(string $name): ?int
{
    $stmt = $this->pdo->prepare(
        "SELECT id FROM kyndryl_auditors WHERE kyndryl_auditor = :n LIMIT 1"
    );
    $stmt->execute([':n' => $name]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}
}
