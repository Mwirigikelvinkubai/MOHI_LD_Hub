<?php
/**
 * config.php — MOHI LD HUB
 * Database connection and full schema for all modules.
 * SQLite via PDO — no server required.
 */

define('DB_PATH', __DIR__ . '/data/ld_hub.db');
define('APP_NAME', 'MOHI LD HUB');
define('APP_VERSION', '2.0');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0755, true);
    }
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    return $pdo;
}

function initSchema(): void {
    $pdo = getDB();

    // =====================================================
    // MODULE 3: STAFF TRAINING DATA (existing — unchanged)
    // Email is the single unique identifier for staff
    // =====================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            staff_no    TEXT    NOT NULL,
            full_name   TEXT    NOT NULL,
            job_title   TEXT,
            workstation TEXT,
            department  TEXT,
            email       TEXT    NOT NULL UNIQUE,
            created_at  TEXT    DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS trainings (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT    NOT NULL,
            description TEXT,
            start_date  TEXT,
            end_date    TEXT,
            created_at  TEXT    DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sub_courses (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            training_id INTEGER NOT NULL,
            name        TEXT    NOT NULL,
            pass_mark   REAL    DEFAULT 50,
            FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS results (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            staff_id      INTEGER NOT NULL,
            training_id   INTEGER NOT NULL,
            sub_course_id INTEGER NOT NULL,
            score         REAL    NOT NULL,
            date_taken    TEXT,
            created_at    TEXT    DEFAULT (datetime('now')),
            FOREIGN KEY (staff_id)      REFERENCES staff(id)       ON DELETE CASCADE,
            FOREIGN KEY (training_id)   REFERENCES trainings(id)   ON DELETE CASCADE,
            FOREIGN KEY (sub_course_id) REFERENCES sub_courses(id) ON DELETE CASCADE,
            UNIQUE (staff_id, sub_course_id)
        )
    ");

    // =====================================================
    // MODULE 1: OBJECTIVES
    // =====================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS objectives (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            title       TEXT    NOT NULL,
            description TEXT,
            owner       TEXT,
            target_date TEXT,
            priority    TEXT    DEFAULT 'Medium',
            status      TEXT    DEFAULT 'Active',
            created_at  TEXT    DEFAULT (datetime('now'))
        )
    ");

    // 1.1 KPIs — linked to objectives
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kpis (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            objective_id INTEGER,
            title        TEXT    NOT NULL,
            description  TEXT,
            target_value TEXT,
            current_value TEXT,
            unit         TEXT,
            frequency    TEXT    DEFAULT 'Monthly',
            owner        TEXT,
            status       TEXT    DEFAULT 'On Track',
            baseline     TEXT,
            notes        TEXT,
            updated_at   TEXT    DEFAULT (datetime('now')),
            created_at   TEXT    DEFAULT (datetime('now')),
            FOREIGN KEY (objective_id) REFERENCES objectives(id) ON DELETE SET NULL
        )
    ");

    // 1.1.1 RACI — linked to KPIs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS raci_items (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            kpi_id       INTEGER,
            activity     TEXT    NOT NULL,
            responsible  TEXT,
            accountable  TEXT,
            consulted    TEXT,
            informed     TEXT,
            notes        TEXT,
            created_at   TEXT    DEFAULT (datetime('now')),
            FOREIGN KEY (kpi_id) REFERENCES kpis(id) ON DELETE SET NULL
        )
    ");

    // =====================================================
    // MODULE 2: INVENTORY
    // =====================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS asset_categories (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT    NOT NULL UNIQUE,
            description TEXT,
            created_at  TEXT    DEFAULT (datetime('now'))
        )
    ");

    // 2.1 Assets
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS assets (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id      INTEGER,
            name             TEXT    NOT NULL,
            description      TEXT,
            serial_number    TEXT,
            quantity         INTEGER DEFAULT 1,
            unit             TEXT    DEFAULT 'pcs',
            location         TEXT,
            condition_status TEXT    DEFAULT 'Good',
            assigned_to      TEXT,
            purchase_date    TEXT,
            warranty_expiry  TEXT,
            notes            TEXT,
            created_at       TEXT    DEFAULT (datetime('now')),
            FOREIGN KEY (category_id) REFERENCES asset_categories(id) ON DELETE SET NULL
        )
    ");

    // =====================================================
    // HUB MODULES — for extensibility (add custom modules)
    // =====================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS hub_modules (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            title       TEXT    NOT NULL,
            description TEXT,
            icon        TEXT    DEFAULT 'bi-grid',
            color       TEXT    DEFAULT 'blue',
            link_url    TEXT,
            sort_order  INTEGER DEFAULT 99,
            is_active   INTEGER DEFAULT 1,
            created_at  TEXT    DEFAULT (datetime('now'))
        )
    ");

    // =====================================================
    // USERS — hub login accounts with roles
    // Roles: admin | editor | viewer
    // =====================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            username    TEXT    NOT NULL UNIQUE,
            password    TEXT    NOT NULL,
            full_name   TEXT,
            email       TEXT,
            role        TEXT    DEFAULT 'viewer',
            is_active   INTEGER DEFAULT 1,
            last_login  TEXT,
            created_at  TEXT    DEFAULT (datetime('now'))
        )
    ");

    // Seed a default admin if no users exist yet
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ((int)$count === 0) {
        $hash = password_hash('Admin@2025!', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)")
            ->execute(['admin', $hash, 'Administrator', 'admin']);
    }
}

initSchema();

// =====================================================
// HELPERS
// =====================================================

function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $icon = $f['type'] === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle';
    return "<div class=\"alert alert-{$f['type']}\"><i class=\"bi {$icon}\"></i> {$f['message']}</div>";
}

function clean(string $val): string {
    return trim(strip_tags($val));
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/** Active nav link helper — call in header.php */
function navActive(string $page, string $activePage): string {
    return $page === $activePage ? ' active' : '';
}

// =====================================================
// ROLE HELPERS
// =====================================================

/** Return the current user's role from session, or '' if not logged in. */
function currentRole(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['ld_hub_role'] ?? '';
}

/** Return true only if the current user is an admin. */
function isAdmin(): bool {
    return currentRole() === 'admin';
}

/** Return true if the user can write data (admin or editor). */
function canEdit(): bool {
    return in_array(currentRole(), ['admin', 'editor'], true);
}

// =====================================================
// CSRF PROTECTION
// =====================================================

/**
 * Return (and generate if needed) the session CSRF token.
 */
function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify the CSRF token from a POST request.
 * Terminates with 403 on failure — call once at the top of every POST handler.
 */
function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('Security check failed (invalid CSRF token). Please go back and try again.');
    }
}