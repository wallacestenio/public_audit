<?php
declare(strict_types=1);

namespace App\Support;

use PDO;

final class Auth
{
    public function __construct(private PDO $pdo) {}

    public function check(): bool
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']);
    }

    public function user(): ?array
    {
        return $this->check() ? $_SESSION['user'] : null;
    }

    public function isAdmin(): bool
    {
        return $this->check() && (int)($_SESSION['user']['user_type'] ?? 1) === 0;
    }

    public function login(string $username, string $password): bool
    {
        $u = trim($username);
        if ($u === '' || $password === '') return false;

        $sql = "SELECT id, kyndryl_auditor, password_hash, user_type
                FROM kyndryl_auditors
                WHERE kyndryl_auditor = :u
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':u' => $u]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row || ($row['password_hash'] ?? '') === '') return false;
        if (!password_verify($password, (string)$row['password_hash'])) return false;

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'        => (int)$row['id'],                 // << id do kyndryl_auditors
            'name'      => (string)$row['kyndryl_auditor'],
            'user_type' => (int)$row['user_type'],          // 0 admin / 1 user
            'logged_at' => date('c'),
        ];
        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        session_destroy();
    }
}