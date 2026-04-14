<?php
/**
 * auth.php — MOHI LD HUB
 * Session-based authentication and role helpers.
 * Included by header.php — every hub page is automatically protected.
 *
 * Session keys set on login:
 *   ld_hub_authed  bool   — true when authenticated
 *   ld_hub_user    string — username
 *   ld_hub_uid     int    — users.id
 *   ld_hub_name    string — full_name
 *   ld_hub_role    string — admin | editor | viewer
 */

require_once __DIR__ . '/config.php';

// ── Ensure session is started ──────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

// ── requireAuth() ───────────────────────────────────────────────────────
// Redirect to login if not authenticated. If session exists but is missing
// role (old session format), backfill from the DB automatically.
function requireAuth(): void {
    if (empty($_SESSION['ld_hub_authed'])) {
        $current = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: login.php?next=' . $current);
        exit;
    }

    // Backfill role/uid/name if missing (session predates user management)
    if (empty($_SESSION['ld_hub_role']) && !empty($_SESSION['ld_hub_user'])) {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE username = ?");
        $stmt->execute([$_SESSION['ld_hub_user']]);
        $row = $stmt->fetch();
        if ($row) {
            $_SESSION['ld_hub_uid']  = (int)$row['id'];
            $_SESSION['ld_hub_name'] = $row['full_name'] ?: $_SESSION['ld_hub_user'];
            $_SESSION['ld_hub_role'] = $row['role'];
        } else {
            // Username not in DB — session is stale (account deleted). Force re-login.
            session_destroy();
            header('Location: login.php');
            exit;
        }
    }
}

// ── requireAdmin() ──────────────────────────────────────────────────────
// Redirect to hub home with an error if the user is not an admin.
function requireAdmin(): void {
    requireAuth();
    if ($_SESSION['ld_hub_role'] !== 'admin') {
        setFlash('danger', 'Access denied — administrators only.');
        redirect('index.php');
    }
}

// ── isAuthed() ──────────────────────────────────────────────────────────
function isAuthed(): bool {
    return !empty($_SESSION['ld_hub_authed']);
}

// ── currentUser() ───────────────────────────────────────────────────────
// Returns an array with id, username, full_name, role; or null.
function currentUser(): ?array {
    if (empty($_SESSION['ld_hub_authed'])) return null;
    return [
        'id'        => $_SESSION['ld_hub_uid']  ?? 0,
        'username'  => $_SESSION['ld_hub_user'] ?? '',
        'full_name' => $_SESSION['ld_hub_name'] ?? '',
        'role'      => $_SESSION['ld_hub_role'] ?? 'viewer',
    ];
}

// ── loginUser() ─────────────────────────────────────────────────────────
// Validate credentials against the users table.
// Returns the user row on success, false on failure.
function loginUser(string $username, string $password): array|false {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    // Update last_login timestamp
    $pdo->prepare("UPDATE users SET last_login = datetime('now') WHERE id = ?")
        ->execute([$user['id']]);

    return $user;
}

// ── setUserSession() ────────────────────────────────────────────────────
// Populate session from a verified user row.
function setUserSession(array $user): void {
    session_regenerate_id(true);
    $_SESSION['ld_hub_authed'] = true;
    $_SESSION['ld_hub_uid']    = (int)$user['id'];
    $_SESSION['ld_hub_user']   = $user['username'];
    $_SESSION['ld_hub_name']   = $user['full_name'] ?: $user['username'];
    $_SESSION['ld_hub_role']   = $user['role'];
}

// ── logoutAndRedirect() ─────────────────────────────────────────────────
function logoutAndRedirect(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: login.php?logged_out=1');
    exit;
}