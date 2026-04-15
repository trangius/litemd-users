<?php

declare(strict_types=1);

namespace LiteMD\Plugins\Users;

use LiteMD\Plugin as PluginRegistry;
use LiteMD\BasePath;
use LiteMD\Http;

// ----------------------------------------------------------------------------
// Users plugin. Provides user registration, login, and member management.
// Depends on the MySQL plugin for database access.
// ----------------------------------------------------------------------------
class Plugin
{
    // ----------------------------------------------------------------------------
    // Plugin metadata shown in the admin Plugins tab.
    // ----------------------------------------------------------------------------
    public static function meta(): array
    {
        return [
            'name'        => 'Users',
            'version'     => '1.0',
            'description' => 'User registration, login, and member management.',
            'author'      => 'LiteMD',
            'requires'    => [['mysql', '1.0']],
        ];
    }

    // ----------------------------------------------------------------------------
    // Runs once when the user clicks Install. Creates the users table.
    // ----------------------------------------------------------------------------
    public static function setup(array $data = []): string
    {
        $pdo = PluginRegistry::getService('database');
        if (!$pdo) {
            throw new \RuntimeException('Database service not available. Install the MySQL plugin first.');
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');

        return 'Users table created.';
    }

    // ----------------------------------------------------------------------------
    // Returns cleanup actions shown before uninstall confirmation.
    // ----------------------------------------------------------------------------
    public static function uninstall(): array
    {
        return [
            [
                'description' => 'Drop the users table and all user data',
                'destructive' => true,
                'execute'     => function () {
                    $pdo = PluginRegistry::getService('database');
                    if ($pdo) {
                        $pdo->exec('DROP TABLE IF EXISTS users');
                    }
                },
            ],
        ];
    }

    // ----------------------------------------------------------------------------
    // Runs on every request. Registers auth service, routes, slots, admin page,
    // and API actions.
    // ----------------------------------------------------------------------------
    public static function register(): void
    {
        require_once __DIR__ . '/Auth.php';

        // Start session so auth state is available
        Auth::start();

        // Ensure reset_token columns exist (one-time migration)
        Auth::ensureResetColumns();

        // Strip /admin suffix so asset URLs are correct on both public and admin pages
        $base = BasePath::detect('/admin');

        // Register the 'auth' service so core can check login state
        PluginRegistry::addService('auth', function () {
            return new class {
                public function isLoggedIn(): bool {
                    return Auth::isLoggedIn();
                }
                public function currentUser(): ?array {
                    return Auth::currentUser();
                }
            };
        });

        // Register the /auth route (handles login/register/logout JSON API)
        PluginRegistry::addRoute('/auth', [self::class, 'handleAuthRoute']);

        // Register the user-menu slot (renders the user button + dropdown)
        PluginRegistry::addToSlot('user-menu', [self::class, 'renderUserMenu']);

        // Public CSS and JS for the auth dropdown
        PluginRegistry::addAsset('css', $base . '/plugins/users/assets/users.css');
        PluginRegistry::addAsset('js', $base . '/plugins/users/assets/users.js');

        // Admin page for member management
        PluginRegistry::addAdminPage([
            'slug'        => 'members',
            'label'       => 'Users',
            'icon'        => '<circle cx="10" cy="7" r="3" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M4 17v-1a6 6 0 0 1 12 0v1" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
            'file'        => __DIR__ . '/admin/page.php',
            'scripts'     => ['../plugins/users/admin/page.js'],
            'description' => 'View and manage registered members.',
        ]);

        // Public route for the password reset page
        PluginRegistry::addRoute('/reset-password', [self::class, 'handleResetPasswordPage']);

        // Admin API actions for member management
        PluginRegistry::addApiAction('members-list', [self::class, 'apiMembersList'], 'GET');
        PluginRegistry::addApiAction('member-delete', [self::class, 'apiMemberDelete'], 'POST');
        PluginRegistry::addApiAction('member-set-password', [self::class, 'apiMemberSetPassword'], 'POST');

        // SMTP settings under Advanced tab
        PluginRegistry::addAdvancedTab([
            'slug'  => 'users-smtp',
            'label' => 'Users',
            'file'  => __DIR__ . '/admin/settings.php',
        ]);
        PluginRegistry::addApiAction('users-smtp-save', [self::class, 'apiSmtpSave'], 'POST');
        PluginRegistry::addApiAction('users-smtp-test', [self::class, 'apiSmtpTest'], 'POST');
    }

    // ========================================================================
    // Auth route handler (replaces auth.php)
    // ========================================================================

    // ----------------------------------------------------------------------------
    // Handle POST requests to /auth. Dispatches to Auth methods based on the
    // 'action' field in the JSON body.
    // ----------------------------------------------------------------------------
    public static function handleAuthRoute(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'POST') {
            Http::jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
        }

        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            Http::jsonResponse(['ok' => false, 'error' => 'Invalid request.'], 400);
        }

        $action = (string) ($payload['action'] ?? '');

        if ($action === 'login') {
            $result = Auth::login(
                (string) ($payload['email'] ?? ''),
                (string) ($payload['password'] ?? '')
            );
            Http::jsonResponse($result, $result['ok'] ? 200 : 401);
        }

        if ($action === 'register') {
            $result = Auth::register(
                (string) ($payload['email'] ?? ''),
                (string) ($payload['password'] ?? '')
            );
            Http::jsonResponse($result, $result['ok'] ? 201 : 400);
        }

        if ($action === 'logout') {
            Auth::logout();
            Http::jsonResponse(['ok' => true]);
        }

        if ($action === 'change-password') {
            if (!Auth::isLoggedIn()) {
                Http::jsonResponse(['ok' => false, 'error' => 'Not logged in.'], 401);
            }
            $result = Auth::changePassword(
                (string) ($payload['current_password'] ?? ''),
                (string) ($payload['new_password'] ?? '')
            );
            Http::jsonResponse($result, $result['ok'] ? 200 : 400);
        }

        if ($action === 'delete-account') {
            if (!Auth::isLoggedIn()) {
                Http::jsonResponse(['ok' => false, 'error' => 'Not logged in.'], 401);
            }
            Auth::deleteCurrentUser();
            Auth::logout();
            Http::jsonResponse(['ok' => true]);
        }

        if ($action === 'forgot-password') {
            $result = Auth::forgotPassword((string) ($payload['email'] ?? ''));
            Http::jsonResponse($result);
        }

        if ($action === 'reset-password') {
            $result = Auth::resetPassword(
                (string) ($payload['token'] ?? ''),
                (string) ($payload['new_password'] ?? '')
            );
            Http::jsonResponse($result, $result['ok'] ? 200 : 400);
        }

        if ($action === 'status') {
            $user = Auth::currentUser();
            Http::jsonResponse([
                'ok' => true,
                'loggedIn' => $user !== null,
                'user' => $user,
            ]);
        }

        Http::jsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400);
    }

    // ========================================================================
    // User menu slot renderer
    // ========================================================================

    // ----------------------------------------------------------------------------
    // Render the user menu HTML for the 'user-menu' slot. Returns the HTML
    // string that gets printed in the header.
    // ----------------------------------------------------------------------------
    public static function renderUserMenu(array $ctx = []): string
    {
        $isLoggedIn = $ctx['isLoggedIn'] ?? false;
        $currentUser = $ctx['currentUser'] ?? null;

        ob_start();
        include __DIR__ . '/includes/user-menu.php';
        return ob_get_clean();
    }

    // ========================================================================
    // Admin API handlers
    // ========================================================================

    // ----------------------------------------------------------------------------
    // Return all members for the admin members table.
    // ----------------------------------------------------------------------------
    public static function apiMembersList(array $payload = []): void
    {
        $pdo = PluginRegistry::getService('database');
        // Select all columns so other plugins (e.g. MailerLite) can add
        // their own columns to the users table and have them show up here
        $stmt = $pdo->query('SELECT * FROM users ORDER BY email ASC');
        $members = $stmt->fetchAll();

        // Never expose password hashes or reset tokens to the frontend
        foreach ($members as &$member) {
            unset($member['password'], $member['reset_token'], $member['reset_token_expires_at']);
        }
        unset($member);
        editor_json_response([
            'ok' => true,
            'members' => $members,
        ]);
    }

    // ----------------------------------------------------------------------------
    // Delete a member by ID.
    // ----------------------------------------------------------------------------
    public static function apiMemberDelete(array $payload = []): void
    {
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            editor_error_response('Invalid member ID.', 400, 'deleting member');
            return;
        }
        $pdo = PluginRegistry::getService('database');
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            editor_error_response('Member not found.', 404, 'deleting member #' . $id);
            return;
        }
        editor_log('Deleted member #' . $id);
        editor_json_response(['ok' => true, 'id' => $id]);
    }

    // ----------------------------------------------------------------------------
    // Set a new password for a member by ID.
    // ----------------------------------------------------------------------------
    public static function apiMemberSetPassword(array $payload = []): void
    {
        $id = (int) ($payload['id'] ?? 0);
        $password = (string) ($payload['password'] ?? '');

        if ($id <= 0) {
            editor_error_response('Invalid member ID.', 400, 'setting password');
            return;
        }
        if (strlen($password) < Auth::MIN_PASSWORD_LENGTH) {
            editor_error_response('Password must be at least ' . Auth::MIN_PASSWORD_LENGTH . ' characters.', 400, 'setting password');
            return;
        }

        $pdo = PluginRegistry::getService('database');
        $hash = Auth::hashPassword($password);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hash, $id]);

        if ($stmt->rowCount() === 0) {
            editor_error_response('Member not found.', 404, 'setting password for member #' . $id);
            return;
        }

        editor_log('Set password for member #' . $id);
        editor_json_response(['ok' => true, 'id' => $id]);
    }

    // ========================================================================
    // Password reset page (public)
    // ========================================================================

    // ----------------------------------------------------------------------------
    // Serve the standalone reset-password HTML page at /reset-password?token=...
    // ----------------------------------------------------------------------------
    public static function handleResetPasswordPage(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        $basePath = BasePath::detect();
        $baseHref = rtrim($basePath, '/') . '/';
        $siteName = \LiteMD\Config::get('site_name', 'LiteMD');

        // Collect theme CSS hrefs for consistent styling
        $cssHrefs = [];
        if (class_exists('\\LiteMD\\Theme')) {
            \LiteMD\Theme::initFromConfig();
            $cssHrefs = \LiteMD\Theme::collectCssHrefs($basePath);
        }

        include __DIR__ . '/includes/reset-password-page.php';
    }

    // ========================================================================
    // SMTP settings API handlers
    // ========================================================================

    // ----------------------------------------------------------------------------
    // Save SMTP settings to config.php under plugins.users.smtp.
    // ----------------------------------------------------------------------------
    public static function apiSmtpSave(array $payload = []): void
    {
        $smtp = [
            'host'         => trim((string) ($payload['host'] ?? '')),
            'port'         => (int) ($payload['port'] ?? 587),
            'encryption'   => (string) ($payload['encryption'] ?? 'tls'),
            'username'     => trim((string) ($payload['username'] ?? '')),
            'password'     => (string) ($payload['password'] ?? ''),
            'from_email'   => trim((string) ($payload['from_email'] ?? '')),
            'from_name'    => trim((string) ($payload['from_name'] ?? '')),
            'email_footer' => trim((string) ($payload['email_footer'] ?? '')),
        ];

        if ($smtp['host'] === '') {
            editor_error_response('SMTP host is required.', 400, 'saving SMTP settings');
            return;
        }
        if ($smtp['from_email'] === '') {
            editor_error_response('From email is required.', 400, 'saving SMTP settings');
            return;
        }

        save_config(editor_content_dir(), function (array &$config) use ($smtp) {
            if (!isset($config['plugins'])) {
                $config['plugins'] = [];
            }
            if (!isset($config['plugins']['users'])) {
                $config['plugins']['users'] = [];
            }
            $config['plugins']['users']['smtp'] = $smtp;
        }, 'Updated SMTP settings');

        editor_json_response(['ok' => true]);
    }

    // ----------------------------------------------------------------------------
    // Send a test email to verify SMTP settings work.
    // ----------------------------------------------------------------------------
    public static function apiSmtpTest(array $payload = []): void
    {
        $testEmail = trim((string) ($payload['test_email'] ?? ''));
        if ($testEmail === '') {
            editor_error_response('Test email address is required.', 400, 'sending test email');
            return;
        }

        require_once __DIR__ . '/Mailer.php';

        try {
            $siteName = \LiteMD\Config::get('site_name', 'LiteMD');
            Mailer::send($testEmail, 'Test email from ' . $siteName, '<p>If you can read this, SMTP is working correctly.</p>');
        } catch (\Throwable $e) {
            editor_error_response($e->getMessage(), 500, 'sending test email');
            return;
        }

        editor_json_response(['ok' => true]);
    }
}
