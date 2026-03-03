<?php
namespace Rongie\QuickHire\Services;

use PDO;
use Exception;
use Rongie\QuickHire\Core\Session;

class AuthService
{
    private PDO $pdo; // ✅ private = encapsulated

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** ✅ PUBLIC API: Register user */
    public function register(string $role, string $first, string $last, string $email, string $password, string $passwordConfirm): int
    {
        $role = strtoupper(trim($role));
        if (!in_array($role, ['JOBSEEKER','EMPLOYER'], true)) {
            throw new Exception("Invalid role.");
        }

        $first = trim($first);
        $last  = trim($last);
        $email = strtolower(trim($email));

        if ($first === '' || $last === '') throw new Exception("Name is required.");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Invalid email.");
        if (strlen($password) < 8) throw new Exception("Password must be at least 8 characters.");
        if ($password !== $passwordConfirm) throw new Exception("Passwords do not match.");
        if ($this->emailExists($email)) throw new Exception("Email already exists.");

        $hash = $this->hashPassword($password);

        $stmt = $this->pdo->prepare("
            INSERT INTO users (role, first_name, last_name, email, password_hash)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$role, $first, $last, $email, $hash]);

        return (int)$this->pdo->lastInsertId();
    }

    /** ✅ PUBLIC API: Login user */
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        $stmt = $this->pdo->prepare("SELECT id, role, password_hash, is_profile_complete FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !$this->verifyPassword($password, $user['password_hash'])) {
            throw new Exception("Invalid email or password.");
        }

        // session set
        Session::set('user_id', (int)$user['id']);
        Session::set('role', $user['role']);

        return $user;
    }

    public function logout(): void
    {
        Session::destroy();
    }

    // -------- PRIVATE helpers (encapsulation) --------
    private function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return (bool) $stmt->fetch();
    }

    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    private function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}