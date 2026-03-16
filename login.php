<?php
/**
 * login.php — MOHI LD HUB Authentication
 * Handles login and logout for all hub users.
 *
 * ┌─────────────────────────────────────────────────┐
 * │  To change the admin password:                  │
 * │  Edit APP_ADMIN_USER and APP_ADMIN_PASS          │
 * │  in config.php                                  │
 * └─────────────────────────────────────────────────┘
 */

require_once 'config.php';
require_once 'auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ── Handle logout ──
if (isset($_GET['logout'])) {
    logoutAndRedirect();
}

// ── Redirect if already logged in ──
if (isAuthed()) {
    redirect('index.php');
}

$error   = '';
$next    = clean($_GET['next'] ?? 'index.php');

// ── Handle login POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === APP_ADMIN_USER && $password === APP_ADMIN_PASS) {
        $_SESSION['ld_hub_authed'] = true;
        $_SESSION['ld_hub_user']   = $username;
        // Regenerate session ID on login to prevent session fixation
        session_regenerate_id(true);
        redirect(filter_var($next, FILTER_VALIDATE_URL) ? 'index.php' : $next);
    } else {
        // Deliberate small delay to slow brute-force attempts
        sleep(1);
        $error = 'Incorrect username or password.';
    }
}

$loggedOut = isset($_GET['logged_out']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — MOHI LD HUB</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@500;600;700;800&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --navy:    #002F66;
            --blue:    #26A9E0;
            --green:   #8BC53F;
            --bg:      #00283C;
            --bg2:     #002F66;
            --bg3:     #003a7a;
            --border:  rgba(38,169,224,0.2);
            --text:    #f0f6ff;
            --muted:   #7aaac8;
            --accent:  #26A9E0;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Roboto', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Subtle grid background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(38,169,224,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(38,169,224,0.04) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        .login-card {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 44px 40px 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.45);
            position: relative;
            overflow: hidden;
        }

        /* Top gradient bar */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--blue), var(--green));
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }

        .brand-icon {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--blue), var(--green));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            box-shadow: 0 4px 14px rgba(38,169,224,0.3);
            flex-shrink: 0;
        }

        .brand-text h1 {
            font-family: 'Barlow', sans-serif;
            font-size: 17px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: 0.02em;
            margin: 0;
        }

        .brand-text p {
            font-size: 11px;
            color: var(--blue);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin: 2px 0 0;
        }

        h2 {
            font-family: 'Barlow', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 6px;
        }

        .sub {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 28px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 6px;
            font-family: 'Barlow', sans-serif;
        }

        .form-control {
            background: var(--bg3) !important;
            border: 1px solid var(--border) !important;
            color: var(--text) !important;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            font-family: 'Roboto', sans-serif;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .form-control:focus {
            border-color: var(--blue) !important;
            box-shadow: 0 0 0 3px rgba(38,169,224,0.15) !important;
            outline: none;
        }

        .form-control::placeholder { color: rgba(122,170,200,0.4); }

        .input-group-text {
            background: var(--bg3) !important;
            border: 1px solid var(--border) !important;
            color: var(--muted) !important;
            border-right: none !important;
            border-radius: 8px 0 0 8px;
        }

        .input-group .form-control { border-left: none !important; border-radius: 0 8px 8px 0; }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--blue), #0089BA);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-family: 'Barlow', sans-serif;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.04em;
            cursor: pointer;
            margin-top: 8px;
            transition: opacity 0.15s, transform 0.1s;
        }

        .btn-login:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-login:active { transform: translateY(0); }

        .alert-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #f87171;
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success-msg {
            background: rgba(139,197,63,0.1);
            border: 1px solid rgba(139,197,63,0.3);
            color: #a8d45a;
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .login-footer {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
            font-size: 11px;
            color: rgba(122,170,200,0.4);
            text-align: center;
            font-family: 'Barlow', sans-serif;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .password-toggle {
            position: relative;
        }
        .password-toggle .form-control { padding-right: 40px; }
        .password-toggle .toggle-eye {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--muted);
            font-size: 15px;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
        }
        .password-toggle .toggle-eye:hover { color: var(--text); }
    </style>
</head>
<body>

<div class="login-card">

    <!-- Brand -->
    <div class="brand-row">
        <div class="brand-icon"><i class="bi bi-grid-3x3-gap-fill" style="color:#fff"></i></div>
        <div class="brand-text">
            <h1>MOHI LD HUB</h1>
            <p>Learning &amp; Development</p>
        </div>
    </div>

    <h2>Sign In</h2>
    <p class="sub">Enter your credentials to access the hub.</p>

    <?php if ($loggedOut): ?>
    <div class="alert-success-msg">
        <i class="bi bi-check-circle"></i> You have been signed out successfully.
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert-error">
        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="login.php<?= $next !== 'index.php' ? '?next=' . htmlspecialchars($next) : '' ?>">

        <div class="mb-3">
            <label class="form-label">Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" name="username" class="form-control"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       placeholder="admin" autocomplete="username" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Password</label>
            <div class="password-toggle">
                <input type="password" name="password" id="pw" class="form-control"
                       placeholder="••••••••" autocomplete="current-password" required>
                <button type="button" class="toggle-eye" onclick="togglePw()">
                    <i class="bi bi-eye" id="eye-icon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>

    </form>

    <div class="login-footer">MOHI L&amp;D · <?= date('Y') ?></div>
</div>

<script>
function togglePw() {
    const pw  = document.getElementById('pw');
    const ico = document.getElementById('eye-icon');
    if (pw.type === 'password') {
        pw.type = 'text';
        ico.className = 'bi bi-eye-slash';
    } else {
        pw.type = 'password';
        ico.className = 'bi bi-eye';
    }
}
</script>

</body>
</html>