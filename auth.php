<?php
/**
 * auth.php — MOHI LD HUB
 * Session-based authentication helpers.
 * Included by header.php so every page is automatically protected.
 */

require_once __DIR__ . '/config.php';

/**
 * Redirect to login if the user is not authenticated.
 * Called at the top of header.php before any HTML output.
 */
function requireAuth(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['ld_hub_authed'])) {
        $current = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: login.php?next=' . $current);
        exit;
    }
}

/**
 * Returns true if the current session is authenticated.
 */
function isAuthed(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION['ld_hub_authed']);
}

/**
 * Destroy session and redirect to login.
 */
function logoutAndRedirect(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
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