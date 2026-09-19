<?php
/**
 * License toggle: RUN/STOP in license.txt (DB password). Page access gated by a separate secret.
 * Does NOT load config/remote_license_gate (must work when license is STOP).
 *
 * URL: /auragold/admin/assets/js/pages/activate-license.php
 */
declare(strict_types=1);

/** First gate: change this and keep the URL private. */
const AURAGOLD_ACTIVATE_GATE_PASSWORD = 'hellobuddy';

if (session_status() === PHP_SESSION_NONE) {
    // Match admin/includes/session_init.php so the session cookie works on http://localhost
    // (php.ini session.cookie_secure=1 would otherwise prevent the cookie from sticking).
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $secure, true);
    }
    session_start();
}

$gateError = '';

if (isset($_GET['logout_gate'])) {
    unset($_SESSION['auragold_activate_gate_ok']);
    header('Location: ' . (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gate_password'])) {
    $gp = trim((string) ($_POST['gate_password'] ?? ''));
    if (hash_equals(AURAGOLD_ACTIVATE_GATE_PASSWORD, $gp)) {
        $_SESSION['auragold_activate_gate_ok'] = true;
        session_regenerate_id(true);
        header('Location: ' . (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        exit;
    }
    $gateError = 'Invalid password.';
}

$gateOk = !empty($_SESSION['auragold_activate_gate_ok']);

if (!$gateOk) {
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restricted</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Roboto, system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
            background: linear-gradient(160deg, #eef1f8 0%, #f5f6fa 50%, #e8ecf4 100%);
            color: #2c3e50;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .box {
            background: #fff;
            max-width: 400px;
            width: 100%;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.04);
            text-align: center;
        }
        h1 { margin: 0 0 8px; font-size: 1.25rem; color: #34495e; }
        p { margin: 0 0 20px; font-size: 0.9rem; color: #5d6d7e; }
        label { display: block; text-align: left; font-size: 0.9rem; margin-bottom: 8px; font-weight: 600; }
        input[type="password"] {
            width: 100%; padding: 12px 14px; font-size: 1rem;
            border: 1px solid #dce0e8; border-radius: 8px; margin-bottom: 16px;
        }
        input:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15); }
        button {
            width: 100%; padding: 14px; border: none; border-radius: 8px;
            background: linear-gradient(180deg, #3498db, #2980b9); color: #fff;
            font-size: 1rem; font-weight: 600; cursor: pointer;
        }
        .err { color: #c0392b; margin-bottom: 12px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Enter access password</h1>
        <p>This page is protected.</p>
        <?php if ($gateError !== ''): ?><p class="err"><?php echo htmlspecialchars($gateError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <form method="post" action="" autocomplete="off">
            <label for="gate_password">Password</label>
            <input type="password" id="gate_password" name="gate_password" required placeholder="Password" autofocus>
            <button type="submit">Continue</button>
        </form>
    </div>
</body>
</html>
    <?php
    exit;
}

require_once __DIR__ . '/../../../includes/activate_license_db.php';

$licenseFile = __DIR__ . DIRECTORY_SEPARATOR . 'license.txt';

$notice = '';
$error = '';

$doStart = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_software']);
$doStop  = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stop_software']);

if ($doStart || $doStop) {
    $password = isset($_POST['activate_password']) ? (string) $_POST['activate_password'] : '';

    $conn = auragold_activate_license_connect();
    if (!$conn) {
        $error = 'Could not connect to the database. Check server settings.';
    } elseif (!auragold_activate_license_verify_password($conn, $password)) {
        $error = 'Invalid password.';
    } elseif ($doStart) {
        $written = @file_put_contents($licenseFile, "RUN\n", LOCK_EX);
        if ($written === false) {
            $error = 'Password accepted but license.txt could not be written. Check file permissions.';
        } else {
            $notice = 'Software started. license.txt now contains RUN. You can open the application.';
        }
    } else {
        $written = @file_put_contents($licenseFile, "STOP\n", LOCK_EX);
        if ($written === false) {
            $error = 'Password accepted but license.txt could not be written. Check file permissions.';
        } else {
            $notice = 'Software stopped. license.txt now contains STOP.';
        }
    }
    if (isset($conn) && $conn instanceof mysqli) {
        mysqli_close($conn);
    }
}

$current = is_readable($licenseFile) ? trim((string) file_get_contents($licenseFile)) : '(file missing or unreadable)';
$showStartButton = ($current !== 'RUN');

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate software</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Roboto, system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
            background: linear-gradient(160deg, #eef1f8 0%, #f5f6fa 50%, #e8ecf4 100%);
            color: #2c3e50;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .box {
            background: #fff;
            max-width: 440px;
            width: 100%;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.04);
            text-align: center;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 1.35rem;
            font-weight: 700;
            color: #27ae60;
        }
        .sub {
            margin: 0 0 24px;
            font-size: 0.95rem;
            color: #5d6d7e;
        }
        .status {
            font-size: 0.9rem;
            color: #34495e;
            margin-bottom: 20px;
            padding: 12px;
            background: #f8f9fc;
            border-radius: 8px;
            word-break: break-all;
        }
        .status strong { color: #2c3e50; }
        .msg-ok {
            color: #1e8449;
            margin-bottom: 16px;
            font-weight: 600;
        }
        .msg-err {
            color: #c0392b;
            margin-bottom: 16px;
        }
        label {
            display: block;
            text-align: left;
            font-size: 0.9rem;
            color: #34495e;
            margin-bottom: 8px;
            font-weight: 600;
        }
        input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            font-size: 1rem;
            border: 1px solid #dce0e8;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        input[type="password"]:focus {
            outline: none;
            border-color: #27ae60;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.15);
        }
        button {
            appearance: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            padding: 14px 28px;
            border-radius: 8px;
            width: 100%;
        }
        button.btn-start {
            background: linear-gradient(180deg, #2ecc71, #27ae60);
            color: #fff;
            box-shadow: 0 4px 14px rgba(39, 174, 96, 0.35);
        }
        button.btn-stop {
            background: linear-gradient(180deg, #e74c3c, #c0392b);
            color: #fff;
            box-shadow: 0 4px 14px rgba(231, 76, 60, 0.35);
        }
        button:hover { filter: brightness(1.05); }
        button:active { transform: translateY(1px); }
        .hint {
            margin-top: 20px;
            font-size: 0.75rem;
            color: #95a5a6;
            line-height: 1.4;
        }
        .top-links { font-size: 0.85rem; margin-bottom: 16px; }
        .top-links a { color: #3498db; }
    </style>
</head>
<body>
    <div class="box">
        <p class="top-links"><a href="?logout_gate=1">Lock page</a></p>
        <h1>Activate software</h1>
        <p class="sub">Enter your password to set <code>license.txt</code> to <strong>RUN</strong> (start) or <strong>STOP</strong> (kill-switch).</p>
        <div class="status">Current file value: <strong><?php echo htmlspecialchars($current, ENT_QUOTES, 'UTF-8'); ?></strong></div>
        <?php if ($notice !== ''): ?>
            <p class="msg-ok"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><a href="../../../index.php">Open login</a> · <a href="<?php echo htmlspecialchars((string) ($_SERVER['SCRIPT_NAME'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Toggle again</a></p>
        <?php elseif ($error !== ''): ?>
            <p class="msg-err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($notice === ''): ?>
        <form method="post" action="" autocomplete="off">
            <label for="activate_password">Password</label>
            <input type="password" id="activate_password" name="activate_password" required placeholder="Enter activation password">
            <?php if ($showStartButton): ?>
            <button type="submit" name="start_software" value="1" class="btn-start">Start software</button>
            <?php else: ?>
            <button type="submit" name="stop_software" value="1" class="btn-stop">Stop software</button>
            <?php endif; ?>
        </form>
        <?php endif; ?>
        <p class="hint">DB password is stored (bcrypt) in <code>tbl_gst_calculation_snapshot.snapshot_version</code>.</p>
    </div>
</body>
</html>
