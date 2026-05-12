<?php
function ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function remember_cookie_secure(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function set_remember_cookie(string $value, int $expiresAt): void
{
    setcookie('remember_token', $value, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => remember_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['remember_token'] = $value;
}

function clear_remember_cookie(): void
{
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => remember_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['remember_token']);
}

function clear_persisted_remember_login(): void
{
    global $pdo;

    if (!empty($_COOKIE['remember_token']) && $pdo instanceof PDO) {
        $parts = explode(':', $_COOKIE['remember_token'], 3);
        if (count($parts) === 3) {
            [$accountType, $accountId, $rawToken] = $parts;
            if (in_array($accountType, ['user', 'trainer'], true) && ctype_digit($accountId) && $rawToken !== '') {
                $stmt = $pdo->prepare("DELETE FROM auth_remember_tokens WHERE account_type = ? AND account_id = ? AND token_hash = ?");
                $stmt->execute([$accountType, (int) $accountId, hash('sha256', $rawToken)]);
            }
        }
    }

    clear_remember_cookie();
}

function persist_remember_login(string $accountType, int $accountId): void
{
    global $pdo;

    if (!$pdo instanceof PDO || !in_array($accountType, ['user', 'trainer'], true) || $accountId <= 0) {
        return;
    }

    $rawToken = bin2hex(random_bytes(32));
    $expiresAt = time() + (30 * 24 * 60 * 60);

    $pdo->prepare("DELETE FROM auth_remember_tokens WHERE account_type = ? AND account_id = ?")
        ->execute([$accountType, $accountId]);

    $pdo->prepare("
        INSERT INTO auth_remember_tokens (account_type, account_id, token_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ")->execute([
        $accountType,
        $accountId,
        hash('sha256', $rawToken),
        date('Y-m-d H:i:s', $expiresAt),
    ]);

    set_remember_cookie($accountType . ':' . $accountId . ':' . $rawToken, $expiresAt);
}

function restore_remembered_login(): void
{
    global $pdo;

    ensure_session_started();

    if (!empty($_SESSION['user_id']) || empty($_COOKIE['remember_token']) || !$pdo instanceof PDO) {
        return;
    }

    $parts = explode(':', $_COOKIE['remember_token'], 3);
    if (count($parts) !== 3) {
        clear_remember_cookie();
        return;
    }

    [$accountType, $accountId, $rawToken] = $parts;
    if (!in_array($accountType, ['user', 'trainer'], true) || !ctype_digit($accountId) || $rawToken === '') {
        clear_remember_cookie();
        return;
    }

    $rememberStmt = $pdo->prepare("
        SELECT token_id
        FROM auth_remember_tokens
        WHERE account_type = ?
          AND account_id = ?
          AND token_hash = ?
          AND expires_at >= NOW()
        LIMIT 1
    ");
    $rememberStmt->execute([$accountType, (int) $accountId, hash('sha256', $rawToken)]);
    $tokenId = $rememberStmt->fetchColumn();

    if (!$tokenId) {
        clear_remember_cookie();
        return;
    }

    if ($accountType === 'user') {
        $accountStmt = $pdo->prepare("
            SELECT user_id, full_name, email, role
            FROM users
            WHERE user_id = ? AND is_active = 1
            LIMIT 1
        ");
        $accountStmt->execute([(int) $accountId]);
        $account = $accountStmt->fetch();
        if (!$account) {
            clear_persisted_remember_login();
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['user_id'];
        $_SESSION['role'] = $account['role'];
        $_SESSION['full_name'] = $account['full_name'];
        $_SESSION['email'] = $account['email'];
        return;
    }

    $trainerStmt = $pdo->prepare("
        SELECT trainer_id, full_name, email
        FROM trainers
        WHERE trainer_id = ? AND is_active = 1
        LIMIT 1
    ");
    $trainerStmt->execute([(int) $accountId]);
    $trainer = $trainerStmt->fetch();
    if (!$trainer) {
        clear_persisted_remember_login();
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $trainer['trainer_id'];
    $_SESSION['role'] = 'trainer';
    $_SESSION['full_name'] = $trainer['full_name'];
    $_SESSION['email'] = $trainer['email'];
}

function redirect_by_role(?string $role): void
{
    $target = match ($role) {
        'admin' => SITE_URL . '/admin/dashboard.php',
        'trainer' => SITE_URL . '/trainer/dashboard.php',
        'user' => SITE_URL . '/user/dashboard.php',
        default => SITE_URL . '/index.php',
    };

    header("Location: " . $target);
    exit;
}

function is_safe_internal_redirect_path(mixed $path): bool
{
    if (!is_string($path) || $path === '') {
        return false;
    }

    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
        return false;
    }

    return !preg_match('/[\r\n]/', $path);
}

// Function to check if a user is logged in
function require_login() {
    ensure_session_started();
    restore_remembered_login();

    if (empty($_SESSION['user_id'])) {
        // Store intended URL to redirect back after login
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $_SESSION['redirect_url'] = is_safe_internal_redirect_path($requestUri) ? $requestUri : '/';
        header("Location: " . SITE_URL . "/auth/login.php");
        exit;
    }
}

// Function to check if a user has admin privileges
function require_admin() {
    require_login();

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        redirect_by_role($_SESSION['role'] ?? null);
    }
}

// Function to ensure client-side users (and admins for testing) can access member pages
function require_user() {
    require_login();

    if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'user' && $_SESSION['role'] !== 'admin')) {
        redirect_by_role($_SESSION['role'] ?? null);
    }
}

// Function to ensure only trainers can access trainer pages
function require_trainer() {
    require_login();

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'trainer') {
        redirect_by_role($_SESSION['role'] ?? null);
    }
}

// Function to prevent logged-in users from viewing auth pages (login/register)
function guest_only() {
    ensure_session_started();
    restore_remembered_login();

    if (!empty($_SESSION['user_id'])) {
        redirect_by_role($_SESSION['role'] ?? null);
    }
}
