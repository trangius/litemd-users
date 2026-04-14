<?php

declare(strict_types=1);

namespace LiteMD\Plugins\Users;

use LiteMD\Plugin as PluginRegistry;

// ----------------------------------------------------------------------------
// Session-based authentication: login, registration, logout, and current-user
// lookup against the "users" table in MySQL via the database plugin service.
// ----------------------------------------------------------------------------
final class Auth
{
    // ----------------------------------------------------------------------------
    // Ensure a PHP session is active. Safe to call multiple times.
    // ----------------------------------------------------------------------------
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    // ----------------------------------------------------------------------------
    // Check whether the current session belongs to a logged-in user.
    // ----------------------------------------------------------------------------
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
    }

    // ----------------------------------------------------------------------------
    // Return the current user's database row (id, email, created_at)
    // or null if not logged in. Never exposes the password hash.
    // ----------------------------------------------------------------------------
    public static function currentUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        $pdo = PluginRegistry::getService('database');
        if (!$pdo) return null;

        $stmt = $pdo->prepare('SELECT id, email, created_at FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    // ----------------------------------------------------------------------------
    // Authenticate with email and password. On success, stores the user ID in
    // the session and returns ['ok' => true]. On failure, returns an error.
    // ----------------------------------------------------------------------------
    public static function login(string $email, string $password): array
    {
        $email = trim($email);

        if ($email === '' || $password === '') {
            return ['ok' => false, 'error' => 'Email and password are required.'];
        }

        $pdo = PluginRegistry::getService('database');
        if (!$pdo) {
            return ['ok' => false, 'error' => 'Database not available.'];
        }

        $stmt = $pdo->prepare('SELECT id, password FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify credentials using constant-time comparison
        if (!is_array($user) || !password_verify($password, $user['password'])) {
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        // Regenerate session ID to prevent fixation attacks
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];

        return ['ok' => true];
    }

    // ----------------------------------------------------------------------------
    // Create a new user account. Validates email format, password length, and
    // uniqueness. On success, logs the user in automatically.
    // ----------------------------------------------------------------------------
    public static function register(string $email, string $password): array
    {
        $email = trim($email);

        if ($email === '' || $password === '') {
            return ['ok' => false, 'error' => 'Email and password are required.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Please enter a valid email address.'];
        }

        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
        }

        $pdo = PluginRegistry::getService('database');
        if (!$pdo) {
            return ['ok' => false, 'error' => 'Database not available.'];
        }

        // Check if the email is already registered
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'An account with this email already exists.'];
        }

        // Insert the new user with a bcrypt-hashed password
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (email, password) VALUES (?, ?)');
        $stmt->execute([$email, $hash]);

        // Log the user in immediately after registration
        $userId = (int) $pdo->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;

        // Notify other plugins (e.g. Newsletter) about the new user
        PluginRegistry::renderSlot('user-registered', ['user_id' => $userId, 'email' => $email]);

        return ['ok' => true];
    }

    // ----------------------------------------------------------------------------
    // Delete the currently logged-in user's account from the database.
    // ----------------------------------------------------------------------------
    public static function deleteCurrentUser(): void
    {
        if (!self::isLoggedIn()) {
            return;
        }

        $pdo = PluginRegistry::getService('database');
        if (!$pdo) return;

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
    }

    // ----------------------------------------------------------------------------
    // Destroy the current session, logging the user out.
    // ----------------------------------------------------------------------------
    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
