<?php

declare(strict_types=1);

namespace LiteMD\Plugins\Users;

use LiteMD\Plugin as PluginRegistry;
use LiteMD\BasePath;

// ----------------------------------------------------------------------------
// Session-based authentication: login, registration, logout, password reset,
// and current-user lookup against the "users" table in MySQL via the database
// plugin service.
// ----------------------------------------------------------------------------
final class Auth
{
    public const MIN_PASSWORD_LENGTH = 8;

    private static bool $resetColumnsMigrated = false;

    // ----------------------------------------------------------------------------
    // Hash a password with bcrypt. Single source of truth for the algorithm.
    // ----------------------------------------------------------------------------
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

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

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return ['ok' => false, 'error' => 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'];
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

        // Insert the new user with a hashed password
        $hash = self::hashPassword($password);
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
    // Change the current user's password. Requires the current password for
    // verification before updating.
    // ----------------------------------------------------------------------------
    public static function changePassword(string $currentPassword, string $newPassword): array
    {
        if (!self::isLoggedIn()) {
            return ['ok' => false, 'error' => 'Not logged in.'];
        }

        if ($currentPassword === '' || $newPassword === '') {
            return ['ok' => false, 'error' => 'Both current and new password are required.'];
        }

        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            return ['ok' => false, 'error' => 'New password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'];
        }

        $pdo = PluginRegistry::getService('database');
        if (!$pdo) {
            return ['ok' => false, 'error' => 'Database not available.'];
        }

        // Verify the current password
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!is_array($user) || !password_verify($currentPassword, $user['password'])) {
            return ['ok' => false, 'error' => 'Current password is incorrect.'];
        }

        // Update to the new password
        $hash = self::hashPassword($newPassword);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hash, $_SESSION['user_id']]);

        return ['ok' => true];
    }

    // ----------------------------------------------------------------------------
    // Add reset_token and reset_token_expires_at columns if they don't exist.
    // Called once per request from Plugin::register().
    // ----------------------------------------------------------------------------
    public static function ensureResetColumns(): void
    {
        if (self::$resetColumnsMigrated) return;
        self::$resetColumnsMigrated = true;

        $pdo = PluginRegistry::getService('database');
        if (!$pdo) return;

        $cols = $pdo->query('SHOW COLUMNS FROM users LIKE "reset_token"')->fetchAll();
        if (empty($cols)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL');
            $pdo->exec('ALTER TABLE users ADD COLUMN reset_token_expires_at DATETIME DEFAULT NULL');
        }
    }

    // ----------------------------------------------------------------------------
    // Generate a password reset token, store its hash in the DB, and email a
    // reset link. Always returns ok:true to prevent email enumeration.
    // ----------------------------------------------------------------------------
    public static function forgotPassword(string $email): array
    {
        $email = trim($email);
        if ($email === '') {
            return ['ok' => true];
        }

        $pdo = PluginRegistry::getService('database');
        if (!$pdo) {
            return ['ok' => true];
        }

        $stmt = $pdo->prepare('SELECT id, reset_token_expires_at FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!is_array($user)) {
            // Sleep briefly to match timing of a real send
            usleep(random_int(100000, 300000));
            return ['ok' => true];
        }

        // Rate limit: skip if a token was created less than 2 minutes ago
        // (token expires in 60 min, so expires_at > now+58min means created < 2 min ago)
        if (!empty($user['reset_token_expires_at'])) {
            $expiresAt = strtotime($user['reset_token_expires_at']);
            if ($expiresAt && $expiresAt > time() + 58 * 60) {
                return ['ok' => true];
            }
        }

        // Generate token: raw goes in email, hash goes in DB
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?');
        $stmt->execute([$hashedToken, $user['id']]);

        // Build the reset URL
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = BasePath::detect();
        $resetUrl = $scheme . '://' . $host . $basePath . '/reset-password?token=' . urlencode($rawToken);

        $siteName = \LiteMD\Config::get('site_name', 'LiteMD');

        // Send the email (silently swallow errors to prevent enumeration)
        try {
            require_once __DIR__ . '/Mailer.php';
            // Build the email body with optional footer from settings
            $usersConfig = \LiteMD\Config::getPluginConfig('users', []);
            $emailFooter = trim((string) (($usersConfig['smtp'] ?? [])['email_footer'] ?? ''));

            $body = '<p>You requested a password reset for your account at ' . htmlspecialchars($siteName) . '.</p>'
                . '<p><a href="' . htmlspecialchars($resetUrl) . '">Click here to reset your password</a></p>'
                . '<p>This link expires in 1 hour. If you did not request this, you can ignore this email.</p>';
            if ($emailFooter !== '') {
                $body .= '<p>' . nl2br(htmlspecialchars($emailFooter)) . '</p>';
            }

            Mailer::send($email, 'Reset your password', $body);
        } catch (\Throwable $e) {
            // Don't reveal send failures
        }

        return ['ok' => true];
    }

    // ----------------------------------------------------------------------------
    // Validate a reset token and set a new password. Clears the token on success.
    // ----------------------------------------------------------------------------
    public static function resetPassword(string $rawToken, string $newPassword): array
    {
        if ($rawToken === '') {
            return ['ok' => false, 'error' => 'Invalid reset link.'];
        }

        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
        }

        $pdo = PluginRegistry::getService('database');
        if (!$pdo) {
            return ['ok' => false, 'error' => 'Database not available.'];
        }

        // Look up user by hashed token, check expiry
        $hashedToken = hash('sha256', $rawToken);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()');
        $stmt->execute([$hashedToken]);
        $user = $stmt->fetch();

        if (!is_array($user)) {
            return ['ok' => false, 'error' => 'Invalid or expired reset link.'];
        }

        // Update password and clear the token
        $hash = self::hashPassword($newPassword);
        $stmt = $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?');
        $stmt->execute([$hash, $user['id']]);

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
