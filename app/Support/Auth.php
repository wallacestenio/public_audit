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

    /** LOGIN AGORA USA EMAIL */
public function login(string $username, string $password): bool
{
    $u = trim($username);
    if ($u === '' || $password === '') {
        return false;
    }

    $sql = "
        SELECT
            id,
            kyndryl_auditor,
            email,
            password_hash,
            user_type
        FROM kyndryl_auditors
        WHERE email = :u
           OR kyndryl_auditor = :u
        LIMIT 1
    ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':u' => $u]);

    $user = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$user || empty($user['password_hash'])) {
        return false;
    }

    if (!password_verify($password, (string)$user['password_hash'])) {
        return false;
    }

    // ✅ Login OK
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'         => (int)$user['id'],
        'name'       => (string)$user['kyndryl_auditor'],
        'email'      => (string)($user['email'] ?? ''),
        'user_type'  => (int)$user['user_type'],
        'logged_at'  => date('c'),
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