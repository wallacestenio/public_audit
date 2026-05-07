<?php

class CategoryRepository
{
    public function __construct(private PDO $pdo) {}

    public function getAllCategories(): array
    {
        return $this->pdo
            ->query("SELECT category FROM categories ORDER BY category")
            ->fetchAll(PDO::FETCH_COLUMN);
    }
}
