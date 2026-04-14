<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AllowedEmailRepository
{
    public function __construct(private PDO $pdo) {}

    public function findActiveByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, email
             FROM allowed_emails
             WHERE email = :email AND active = 1
             LIMIT 1"
        );
        $stmt->execute([':email' => strtolower($email)]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}