<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/includes/auragold_require_login.php';

// Redirect if already logged in — keep active session, do not show login again.
// Load config first so post-login preference can be read from the operational DB.
if (auragold_is_logged_in_session()) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/auragold_user_login_dashboard.php';
    header('Location: ' . auragold_resolve_post_login_redirect_url((int) ($_SESSION['user_id'] ?? 0)));
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_user_login_dashboard.php';

$login_error = '';
if (isset($_GET['login_error']) && is_string($_GET['login_error'])) {
    $login_error = trim($_GET['login_error']);
}
$branch_entry_id = isset($_GET['branch_entry']) ? (int) $_GET['branch_entry'] : 0;

$login_promo_image_src = '';
foreach (['20260822_195828.png', 'login_page_iage.jpeg', 'login_page_image.jpeg', 'login_image.jpg', 'login_image.jpeg'] as $login_promo_candidate) {
    $login_promo_path = __DIR__ . DIRECTORY_SEPARATOR . $login_promo_candidate;
    if (is_file($login_promo_path)) {
        $login_promo_image_src = $login_promo_candidate . '?v=' . (int) @filemtime($login_promo_path);
        break;
    }
}

$login_form_logo_src = '';
foreach (['logo_login.png', 'assets/img/logo-dark.png', 'assets/img/logo.png', 'favicon.jpeg'] as $login_logo_candidate) {
    $login_logo_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $login_logo_candidate);
    if (is_file($login_logo_path)) {
        $login_form_logo_src = $login_logo_candidate . '?v=' . (int) @filemtime($login_logo_path);
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars(auragold_app_name() . ' – Login', ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="icon" type="image/jpeg" href="favicon.jpeg">
<style>
:root {
    --gold: #e8912d;
    --gold-deep: #d97706;
    --gold-soft: #f6b35a;
    --gold-glow: rgba(232, 145, 45, 0.35);
    --navy: #0b2540;
    --navy-mid: #163a5c;
    --navy-soft: #1e4976;
    --white: #ffffff;
    --cream: #fffdf9;
    --panel-edge: #e8eef5;
    --input-border: #d5dee8;
    --input-bg: #ffffff;
    --text-dark: #15233a;
    --text-muted: #6b7a90;
    --radius-field: 12px;
    --radius-card: 24px;
}

* { box-sizing: border-box; }

html, body {
    margin: 0;
    padding: 0;
    min-height: 100%;
    min-height: 100dvh;
    width: 100%;
}

body {
    font-family: Outfit, "Segoe UI", sans-serif;
    font-size: 0.9375rem;
    font-weight: 400;
    color: var(--text-dark);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    background: var(--navy);
}

/* Ambient page background */
.login-page-shell {
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    align-items: stretch;
    justify-content: stretch;
    padding: 0;
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(ellipse 80% 60% at 15% 20%, rgba(232, 145, 45, 0.14) 0%, transparent 55%),
        radial-gradient(ellipse 70% 50% at 85% 80%, rgba(30, 73, 118, 0.45) 0%, transparent 50%),
        linear-gradient(145deg, #071a2e 0%, #0b2540 42%, #112d4a 100%);
}

.login-page-shell::before {
    content: "";
    position: absolute;
    inset: 0;
    opacity: 0.04;
    background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23f6b35a' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    pointer-events: none;
}

.login-card-wrap {
    display: flex;
    flex-direction: row;
    width: 100%;
    max-width: 100%;
    min-height: 100vh;
    min-height: 100dvh;
    background: var(--white);
    border-radius: 0;
    overflow: hidden;
    position: relative;
    z-index: 1;
    box-shadow: none;
    animation: cardIn 700ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

.login-card-wrap > .row {
    flex: 1 1 auto;
    min-height: 0;
    width: 100%;
    margin: 0;
}

.login-hero-col,
.login-form-col {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    min-height: 100dvh;
    padding: 0;
}

.login-form-col .login-right {
    flex: 1 1 auto;
}

.login-card-wrap::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gold-deep), var(--gold-soft), var(--gold-deep));
    z-index: 10;
}

@keyframes cardIn {
    from { opacity: 0; transform: translateY(20px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

/* Left promo image panel */
.login-hero {
    width: 100%;
    height: 100%;
    min-height: 100%;
    position: relative;
    overflow: hidden;
    background: linear-gradient(160deg, #0f3050 0%, #1a2f4a 50%, #0b2540 100%);
    line-height: 0;
}

.login-hero .promo-image {
    width: 100%;
    height: 100%;
    min-height: 100%;
    object-fit: cover;
    object-position: center center;
    display: block;
}

.login-hero-no-image {
    width: 100%;
    height: 100%;
    min-height: 100%;
    background:
        radial-gradient(circle at 30% 40%, rgba(232, 145, 45, 0.12) 0%, transparent 50%),
        radial-gradient(circle at 70% 70%, rgba(246, 179, 90, 0.08) 0%, transparent 40%),
        linear-gradient(160deg, #0f3050 0%, #163a5c 100%);
}

/* Right form panel */
.login-right {
    width: 100%;
    height: 100%;
    min-height: 100%;
    min-width: 0;
    background: var(--cream);
    padding: 32px 28px;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    justify-content: center;
    overflow-x: hidden;
    overflow-y: auto;
}

.login-panel {
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
}

.login-form-logo {
    display: block;
    width: auto;
    max-width: 240px;
    height: auto;
    max-height: 72px;
    margin: 0 auto 22px;
    object-fit: contain;
}

.login-form-eyebrow {
    margin: 0 0 6px;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--gold-deep);
}

.login-right .login-title {
    text-align: left;
    font-family: "Source Serif 4", Georgia, serif;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -0.025em;
    margin: 0 0 6px;
    line-height: 1.15;
}

.login-subtitle {
    text-align: left;
    font-size: 0.875rem;
    color: var(--text-muted);
    margin: 0 0 24px;
    font-weight: 400;
    line-height: 1.5;
}

.login-steps {
    display: flex;
    align-items: center;
    gap: 0;
    margin-bottom: 26px;
    padding: 4px;
    background: rgba(11, 37, 64, 0.05);
    border-radius: 999px;
    border: 1px solid rgba(11, 37, 64, 0.06);
}

.login-step-pill {
    flex: 1;
    text-align: center;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--text-muted);
    padding: 8px 12px;
    border-radius: 999px;
    transition: color 0.25s ease, background 0.25s ease, box-shadow 0.25s ease;
}

.login-step-pill.is-active {
    color: var(--navy);
    background: var(--white);
    box-shadow: 0 2px 8px rgba(11, 37, 64, 0.1);
}

.login-step-pill.is-done {
    color: var(--gold-deep);
}

.login-right .form-label {
    font-weight: 500;
    color: var(--navy-mid);
    font-size: 0.8rem;
    letter-spacing: 0.01em;
    margin-bottom: 7px;
}

.login-right .form-control {
    height: 50px;
    border-radius: var(--radius-field);
    border: 1px solid var(--input-border);
    background: var(--input-bg);
    padding: 0 44px 0 44px;
    font-size: 0.9rem;
    font-family: inherit;
    color: var(--text-dark);
    box-shadow: 0 1px 2px rgba(11, 37, 64, 0.04);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.login-right .form-control:hover {
    border-color: #b8c7d8;
}

.login-right .form-control:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--gold-glow);
    outline: none;
    background: var(--white);
}

.login-right .form-control::placeholder {
    color: #98a6b8;
}

.login-right .input-wrap {
    position: relative;
}

.login-right .input-wrap .input-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #8a9bb0;
    pointer-events: none;
    display: flex;
    align-items: center;
    transition: color 0.2s ease;
    z-index: 1;
}

.login-right .input-wrap:focus-within .input-icon {
    color: var(--gold-deep);
}

.login-right .input-wrap .input-icon svg {
    flex-shrink: 0;
    width: 18px;
    height: 18px;
}

.login-pwd-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 6px;
    cursor: pointer;
    color: #8a9bb0;
    display: flex;
    align-items: center;
    border-radius: 8px;
    transition: color 0.2s ease, background 0.2s ease;
    z-index: 2;
}

.login-pwd-toggle:hover {
    color: var(--gold-deep);
    background: rgba(232, 145, 45, 0.08);
}

.login-pwd-toggle svg {
    width: 18px;
    height: 18px;
}

.login-right .form-check {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: auto;
    padding-left: 0;
    margin-bottom: 20px !important;
}

.login-right .form-check-input {
    width: 1.1em;
    height: 1.1em;
    margin: 0;
    float: none;
    border-color: var(--input-border);
    cursor: pointer;
}

.login-right .form-check-input:checked {
    background-color: var(--gold-deep);
    border-color: var(--gold-deep);
}

.login-right .form-check-input:focus {
    box-shadow: 0 0 0 3px var(--gold-glow);
}

.login-right .form-check-label {
    font-size: 0.875rem;
    color: var(--text-muted);
    cursor: pointer;
    margin: 0;
    user-select: none;
}

.btn-login {
    height: 50px;
    width: 100%;
    border-radius: 999px;
    font-family: inherit;
    font-weight: 600;
    font-size: 0.92rem;
    letter-spacing: 0.04em;
    background: linear-gradient(135deg, var(--gold-soft) 0%, var(--gold) 50%, var(--gold-deep) 100%);
    border: none;
    color: var(--white);
    box-shadow: 0 6px 20px rgba(217, 119, 6, 0.32);
    transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    position: relative;
    overflow: hidden;
}

.btn-login::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(255,255,255,0.15) 0%, transparent 50%);
    pointer-events: none;
}

.btn-login:hover {
    color: var(--white);
    filter: brightness(1.05);
    box-shadow: 0 10px 28px rgba(217, 119, 6, 0.4);
    transform: translateY(-1px);
}

.btn-login:active {
    transform: translateY(0);
    filter: brightness(0.98);
}

.btn-login:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    transform: none;
    filter: none;
}

.btn-login.is-loading {
    color: transparent;
    pointer-events: none;
}

.btn-login.is-loading::after {
    content: "";
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin: -10px 0 0 -10px;
    border: 2px solid rgba(255, 255, 255, 0.35);
    border-top-color: #fff;
    border-radius: 50%;
    animation: loginSpin 0.7s linear infinite;
}

@keyframes loginSpin {
    to { transform: rotate(360deg); }
}

.login-right .mb-3 { margin-bottom: 16px !important; }

.login-branch-select {
    width: 100%;
    height: 50px;
    padding: 10px 12px 10px 44px;
    border: 1px solid var(--input-border);
    border-radius: var(--radius-field);
    font-size: 0.9rem;
    font-family: inherit;
    color: var(--text-dark);
    background-color: var(--input-bg);
    appearance: auto;
    box-shadow: 0 1px 2px rgba(11, 37, 64, 0.04);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.login-branch-select:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--gold-glow);
    outline: none;
}

.login-branch-wrap {
    position: relative;
}

.login-branch-wrap .input-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #8a9bb0;
    pointer-events: none;
    z-index: 1;
    display: flex;
}

.login-branch-wrap .login-branch-select {
    padding-left: 44px;
}

#message .alert,
.login-right .alert {
    border-radius: 10px;
    border: none;
    margin-top: 4px;
    margin-bottom: 16px;
    font-size: 0.85rem;
    padding: 10px 14px;
}

.login-right .alert-danger {
    background: #fef2f2;
    color: #b91c1c;
}

.login-step {
    animation: stepIn 350ms ease both;
}

@keyframes stepIn {
    from { opacity: 0; transform: translateX(8px); }
    to { opacity: 1; transform: translateX(0); }
}

.login-step--hidden {
    display: none !important;
}

.login-back-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.84rem;
    color: var(--navy-mid);
    text-decoration: none;
    margin-bottom: 16px;
    font-weight: 500;
    cursor: pointer;
    background: none;
    border: none;
    padding: 6px 0;
    transition: color 0.15s ease;
}

.login-back-link:hover {
    color: var(--gold-deep);
    text-decoration: none;
}

.login-page-footer {
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px solid rgba(11, 37, 64, 0.08);
    text-align: center;
    font-size: 0.75rem;
    color: var(--text-muted);
    letter-spacing: 0.02em;
}

#loginNextBtn:disabled { opacity: 0.55; cursor: not-allowed; }

@media (max-width: 992px) {
    .login-right {
        padding: 32px 32px 28px;
    }
}

@media (max-width: 767.98px) {
    html, body {
        height: auto;
        min-height: 100%;
        min-height: 100dvh;
        overflow-x: hidden;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }

    .login-page-shell {
        align-items: flex-start;
        padding: 0;
    }

    .login-card-wrap {
        flex-direction: column;
        min-height: auto;
    }

    .login-hero-col,
    .login-form-col {
        min-height: auto;
    }

    .login-hero,
    .login-hero .promo-image,
    .login-hero-no-image {
        min-height: 220px;
        height: auto;
        max-height: 280px;
    }

    .login-right {
        min-height: 0;
        height: auto;
        padding: 28px 24px 24px;
        justify-content: flex-start;
    }

    .login-right .login-title {
        font-size: 1.4rem;
    }
}

@media (max-width: 480px) {
    .login-right {
        padding: 24px 18px 20px;
    }

    .login-steps {
        margin-bottom: 20px;
    }

    .login-step-pill {
        font-size: 0.65rem;
        padding: 7px 8px;
    }
}
</style>

</head>
<body>

<div class="login-page-shell">
<div class="login-card-wrap">
    <div class="row g-0 flex-grow-1">
        <div class="col-12 col-md-8 login-hero-col">
            <div class="login-hero" aria-hidden="true">
                <?php if ($login_promo_image_src !== ''): ?>
                <img src="<?php echo htmlspecialchars($login_promo_image_src, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="promo-image" decoding="async">
                <?php else: ?>
                <div class="login-hero-no-image" aria-hidden="true"></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-md-4 login-form-col">
            <div class="login-right">
        <div class="login-panel">
           
            <p class="login-form-eyebrow">Welcome back</p>
            <h1 class="login-title">Sign in to <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="login-subtitle">Enter your branch server URL, username, and password to continue.</p>

            <div class="login-steps" role="navigation" aria-label="Login progress">
                <span class="login-step-pill is-active" id="loginStepPill1">1 · Sign in</span>
                <span class="login-step-pill" id="loginStepPill2">2 · Branch</span>
            </div>

            <?php if ($login_error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>

            <form id="loginForm" method="post" action="login_submit.php" autocomplete="on">
                <input type="hidden" name="login_db_name" id="login_db_name" value="">

                <div id="loginStep1" class="login-step">
                    <div class="mb-3">
                        <label class="form-label" for="login_target_url">Branch server URL</label>
                        <div class="input-wrap">
                            <span class="input-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span>
                            <input type="text" name="login_target_url" id="login_target_url" class="form-control" placeholder="e.g. https://ratnapuram.goldmatrixsoft.com/" autocomplete="url" inputmode="url" maxlength="500" autocapitalize="none" required aria-required="true">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-wrap">
                            <span class="input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                            <input type="text" name="username" id="username" class="form-control" placeholder="Username" required autocomplete="username">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-wrap">
                            <span class="input-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Password" required autocomplete="current-password" style="padding-right: 44px;">
                            <button type="button" class="login-pwd-toggle" id="loginPwdToggle" aria-label="Show password" title="Show password">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <div id="login_step1_err" class="alert alert-danger py-2 px-3 mb-3" style="display:none;" role="alert"></div>
                    <button type="button" class="btn btn-login w-100" id="loginNextBtn">Continue</button>
                </div>

                <div id="loginStep2" class="login-step login-step--hidden" aria-hidden="true" style="display:none">
                    <button type="button" class="login-back-link" id="loginBackToStep1">← Back to sign in</button>
                    <div id="login_verify_err" class="alert alert-danger py-2 px-3 mb-3" style="display:none;" role="alert"></div>
                    <div class="mb-3">
                        <label class="form-label" for="login_branch_id">Branch</label>
                        <div class="login-branch-wrap">
                            <span class="input-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></span>
                            <select id="login_branch_id" class="login-branch-select" disabled>
                                <option value="">—</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3" id="login_fy_wrap" style="display:none;">
                        <label class="form-label" for="financial_year_id">Financial Year</label>
                        <div class="login-branch-wrap">
                            <span class="input-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                            <select id="financial_year_id" class="login-branch-select" data-fy-select="1" disabled>
                                <option value="">—</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-login w-100" type="submit" id="login_submit_btn" disabled>Sign in</button>
                </div>
            </form>

            <p class="login-page-footer">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> &middot; Secure login</p>
        </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
window.AURAGOLD_DEFAULT_DB_NAME = <?php echo json_encode(defined('DB_NAME') ? (string) DB_NAME : '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.AURAGOLD_ENTRY_BRANCH_ID = <?php echo (int) $branch_entry_id; ?>;
</script>
<script>
(function () {
    var credentialsVerified = false;
    var verifySeq = 0;

    function setStep1Error(msg) {
        var el = document.getElementById('login_step1_err');
        if (!el) return;
        if (msg) {
            el.textContent = msg;
            el.style.display = 'block';
        } else {
            el.textContent = '';
            el.style.display = 'none';
        }
    }
    function setVerifyError(msg) {
        var el = document.getElementById('login_verify_err');
        if (!el) return;
        if (msg) {
            el.textContent = msg;
            el.style.display = 'block';
        } else {
            el.textContent = '';
            el.style.display = 'none';
        }
    }
    function updateLoginStepPills(step) {
        var p1 = document.getElementById('loginStepPill1');
        var p2 = document.getElementById('loginStepPill2');
        if (!p1 || !p2) return;
        p1.classList.remove('is-active', 'is-done');
        p2.classList.remove('is-active', 'is-done');
        if (step === 1) {
            p1.classList.add('is-active');
        } else {
            p1.classList.add('is-done');
            p2.classList.add('is-active');
        }
    }
    function showStep1() {
        var a = document.getElementById('loginStep1');
        var b = document.getElementById('loginStep2');
        if (a) {
            a.classList.remove('login-step--hidden');
            a.style.display = '';
        }
        if (b) {
            b.classList.add('login-step--hidden');
            b.style.display = 'none';
            b.setAttribute('aria-hidden', 'true');
        }
        var nb = document.getElementById('loginNextBtn');
        if (nb) {
            nb.disabled = false;
            nb.classList.remove('is-loading');
        }
        updateLoginStepPills(1);
    }
    function showStep2() {
        var a = document.getElementById('loginStep1');
        var b = document.getElementById('loginStep2');
        if (a) {
            a.classList.add('login-step--hidden');
            a.style.display = 'none';
        }
        if (b) {
            b.classList.remove('login-step--hidden');
            b.style.display = '';
            b.setAttribute('aria-hidden', 'false');
        }
        updateLoginStepPills(2);
    }

    function resetBranchState() {
        credentialsVerified = false;
        var form = document.getElementById('loginForm');
        if (form) {
            var sh = form.querySelector('input[name="login_branch_id"][data-superadmin-hidden="1"]');
            if (sh) {
                sh.remove();
            }
            var sbd = form.querySelector('input[name="login_branch_id"][data-superbranch-hidden="1"]');
            if (sbd) {
                sbd.remove();
            }
        }
        var s = document.getElementById('login_branch_id');
        var btn = document.getElementById('login_submit_btn');
        if (s) {
            s.innerHTML = '<option value="">—</option>';
            s.disabled = true;
            s.removeAttribute('name');
            s.removeAttribute('required');
        }
        if (btn) btn.disabled = true;
        var hidDb = document.getElementById('login_db_name');
        if (hidDb) {
            hidDb.value = '';
        }
        var fy = document.getElementById('financial_year_id');
        var wrap = document.getElementById('login_fy_wrap');
        if (fy) {
            fy.innerHTML = '<option value="">—</option>';
            fy.disabled = true;
            fy.removeAttribute('name');
            fy.removeAttribute('required');
        }
        if (wrap) wrap.style.display = 'none';
    }

    function applySuperadminDirectLogin() {
        var form = document.getElementById('loginForm');
        var s = document.getElementById('login_branch_id');
        var wrap = document.getElementById('login_fy_wrap');
        var fy = document.getElementById('financial_year_id');
        var btn = document.getElementById('login_submit_btn');
        resetBranchState();
        if (form) {
            var h = document.createElement('input');
            h.type = 'hidden';
            h.name = 'login_branch_id';
            h.value = '0';
            h.setAttribute('data-superadmin-hidden', '1');
            form.appendChild(h);
        }
        if (s) {
            s.innerHTML = '<option value="0">Superadmin — default main (no selection)</option>';
            s.disabled = true;
            s.removeAttribute('name');
            s.removeAttribute('required');
        }
        if (wrap) {
            wrap.style.display = 'none';
        }
        if (fy) {
            fy.innerHTML = '<option value="">—</option>';
            fy.disabled = true;
            fy.removeAttribute('name');
            fy.removeAttribute('required');
        }
        credentialsVerified = true;
        setVerifyError('');
        var hidDbSa = document.getElementById('login_db_name');
        if (hidDbSa && typeof window.AURAGOLD_DEFAULT_DB_NAME === 'string') {
            hidDbSa.value = window.AURAGOLD_DEFAULT_DB_NAME;
        }
        if (btn) {
            btn.disabled = false;
        }
    }

    function applySuperbranchDirectLogin(loginDbName) {
        var form = document.getElementById('loginForm');
        resetBranchState();
        if (form) {
            var h = document.createElement('input');
            h.type = 'hidden';
            h.name = 'login_branch_id';
            h.value = '0';
            h.setAttribute('data-superbranch-hidden', '1');
            form.appendChild(h);
        }
        var hidDb = document.getElementById('login_db_name');
        if (hidDb) {
            hidDb.value = loginDbName && typeof loginDbName === 'string' ? loginDbName : (typeof window.AURAGOLD_DEFAULT_DB_NAME === 'string' ? window.AURAGOLD_DEFAULT_DB_NAME : '');
        }
        setStep1Error('');
        setVerifyError('');
        if (form) {
            form.submit();
        }
    }

    function syncLoginDbHiddenFromBranchSelect() {
        var s = document.getElementById('login_branch_id');
        var hidDb = document.getElementById('login_db_name');
        if (!s || !hidDb) {
            return;
        }
        var opt = s.options[s.selectedIndex];
        if (!opt || opt.value === '') {
            hidDb.value = '';
            return;
        }
        var dn = opt.getAttribute('data-db-name');
        hidDb.value = dn !== null ? String(dn) : '';
    }

    function updateLoginButtonEnabled() {
        var s = document.getElementById('login_branch_id');
        var btn = document.getElementById('login_submit_btn');
        if (!s || !btn) return;
        if (!credentialsVerified) {
            btn.disabled = true;
            return;
        }
        var v = s.value;
        btn.disabled = (v === '' || v === null);
    }

    function pad2(n) { return n < 10 ? '0' + n : String(n); }
    function isoToDisplay(iso) {
        if (!iso || typeof iso !== 'string') return '';
        var p = iso.split('-');
        if (p.length !== 3) return iso;
        return pad2(parseInt(p[2], 10)) + '-' + pad2(parseInt(p[1], 10)) + '-' + p[0];
    }
    function fyOptionLabel(y) {
        var a = isoToDisplay(y.start_date || '');
        var b = isoToDisplay(y.end_date || '');
        if (a && b) return a + ' — ' + b;
        return 'FY #' + y.id;
    }
    function loadFinancialYears() {
        var branchEl = document.getElementById('login_branch_id');
        var wrap = document.getElementById('login_fy_wrap');
        var sel = document.getElementById('financial_year_id');
        if (!branchEl || !wrap || !sel) return;
        var bid = branchEl.value;
        if (bid === '' || bid === null) {
            wrap.style.display = 'none';
            return;
        }
        sel.innerHTML = '<option value="">Loading…</option>';
        sel.disabled = true;
        sel.removeAttribute('name');
        sel.removeAttribute('required');
        wrap.style.display = 'block';
        fetch('ajax/login_financial_years.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'login_branch_id=' + encodeURIComponent(bid),
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var years = (data && data.years) ? data.years : [];
                years = years.filter(function (y) { return y && y.is_active; });
                sel.innerHTML = '';
                if (years.length === 0) {
                    wrap.style.display = 'none';
                    sel.removeAttribute('name');
                    sel.removeAttribute('required');
                    sel.disabled = true;
                    return;
                }
                wrap.style.display = 'block';
                sel.disabled = false;
                sel.setAttribute('name', 'financial_year_id');
                sel.setAttribute('required', 'required');
                var pick = 0;
                for (var i = 0; i < years.length; i++) {
                    if (years[i].is_active) {
                        pick = i;
                        break;
                    }
                }
                years.forEach(function (y, idx) {
                    var opt = document.createElement('option');
                    opt.value = String(y.id);
                    opt.textContent = fyOptionLabel(y);
                    if (idx === pick) opt.selected = true;
                    sel.appendChild(opt);
                });
            })
            .catch(function () {
                sel.innerHTML = '';
                var o = document.createElement('option');
                o.value = '';
                o.textContent = 'Could not load years';
                sel.appendChild(o);
                sel.disabled = true;
                sel.removeAttribute('name');
                sel.removeAttribute('required');
                wrap.style.display = 'block';
            });
    }

    function formatBranchOptionLabel(b) {
        var label = String(b.label || '').trim();
        var dbn = (b.db_name !== undefined && b.db_name !== null) ? String(b.db_name).trim() : '';
        if (dbn === '') {
            return label;
        }
        return label + ' (' + dbn + ')';
    }

    function applyVerifiedBranches(branches) {
        var s = document.getElementById('login_branch_id');
        if (!s || !Array.isArray(branches)) return;
        s.innerHTML = '';
        branches.forEach(function (b) {
            var opt = document.createElement('option');
            opt.value = String(b.id);
            opt.textContent = formatBranchOptionLabel(b);
            if (b.db_name !== undefined && b.db_name !== null) {
                opt.setAttribute('data-db-name', String(b.db_name));
            }
            s.appendChild(opt);
        });
        s.disabled = false;
        s.setAttribute('name', 'login_branch_id');
        s.setAttribute('required', 'required');
        credentialsVerified = true;

        function selectEntryBranchIfAny() {
            var want = typeof window.AURAGOLD_ENTRY_BRANCH_ID === 'number' ? window.AURAGOLD_ENTRY_BRANCH_ID : 0;
            if (want <= 0 || !s) {
                return;
            }
            for (var i = 0; i < s.options.length; i++) {
                if (parseInt(s.options[i].value, 10) === want) {
                    s.selectedIndex = i;
                    syncLoginDbHiddenFromBranchSelect();
                    return;
                }
            }
        }

        if (branches.length === 1) {
            s.selectedIndex = 0;
            selectEntryBranchIfAny();
            syncLoginDbHiddenFromBranchSelect();
            loadFinancialYears();
            updateLoginButtonEnabled();
            showStep2();
            return;
        }

        var ph = document.createElement('option');
        ph.value = '';
        ph.selected = true;
        ph.textContent = '— Select branch —';
        s.insertBefore(ph, s.firstChild);
        selectEntryBranchIfAny();
        var wantBr = typeof window.AURAGOLD_ENTRY_BRANCH_ID === 'number' ? window.AURAGOLD_ENTRY_BRANCH_ID : 0;
        if (s.selectedIndex < 0 || (s.options[s.selectedIndex] && s.options[s.selectedIndex].value === '')) {
            if (wantBr <= 0) {
                s.selectedIndex = 0;
            }
        }
        syncLoginDbHiddenFromBranchSelect();
        updateLoginButtonEnabled();
        if (s.options[s.selectedIndex] && String(s.options[s.selectedIndex].value || '') !== '') {
            loadFinancialYears();
        }
        showStep2();
    }

    function runVerify() {
        setStep1Error('');
        setVerifyError('');
        var uEl = document.getElementById('username');
        var pEl = document.getElementById('password');
        var urlEl = document.getElementById('login_target_url');
        var turl = urlEl ? String(urlEl.value || '').trim() : '';
        if (turl === '') {
            setStep1Error('IP address / server URL is required.');
            return;
        }
        var u = uEl ? String(uEl.value || '').trim() : '';
        var p = pEl ? String(pEl.value || '') : '';
        if (u === '' || p === '') {
            setStep1Error('Enter username and password.');
            return;
        }
        var nextBtn = document.getElementById('loginNextBtn');
        if (nextBtn) {
            nextBtn.disabled = true;
            nextBtn.classList.add('is-loading');
        }
        var reqId = ++verifySeq;
        var be = typeof window.AURAGOLD_ENTRY_BRANCH_ID === 'number' ? window.AURAGOLD_ENTRY_BRANCH_ID : 0;
        var body =
            'username=' + encodeURIComponent(u) +
            '&password=' + encodeURIComponent(p) +
            '&login_target_url=' + encodeURIComponent(turl);
        if (be > 0) {
            body += '&branch_entry=' + encodeURIComponent(String(be));
        }
        fetch('ajax/login_verify_credentials.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (nextBtn) {
                    nextBtn.disabled = false;
                    nextBtn.classList.remove('is-loading');
                }
                if (reqId !== verifySeq) {
                    return;
                }
                if (data && data.success && data.superbranch_direct) {
                    var dbn = data.login_db_name ? String(data.login_db_name) : '';
                    applySuperbranchDirectLogin(dbn);
                    return;
                }
                if (data && data.success && data.is_superadmin) {
                    if (Array.isArray(data.branches) && data.branches.length > 0) {
                        resetBranchState();
                        setVerifyError('');
                        applyVerifiedBranches(data.branches);
                        return;
                    }
                    applySuperadminDirectLogin();
                    showStep2();
                    return;
                }
                if (data && data.success && Array.isArray(data.branches) && data.branches.length > 0) {
                    resetBranchState();
                    setVerifyError('');
                    applyVerifiedBranches(data.branches);
                    return;
                }
                credentialsVerified = false;
                resetBranchState();
                setStep1Error((data && data.message) ? data.message : 'Could not verify. Try again.');
            })
            .catch(function () {
                if (nextBtn) {
                    nextBtn.disabled = false;
                    nextBtn.classList.remove('is-loading');
                }
                if (reqId !== verifySeq) {
                    return;
                }
                credentialsVerified = false;
                resetBranchState();
                setStep1Error('Network error. Try again.');
            });
    }

    var br = document.getElementById('login_branch_id');
    var un = document.getElementById('username');
    var pw = document.getElementById('password');
    var form = document.getElementById('loginForm');
    var nextBtn = document.getElementById('loginNextBtn');
    var backBtn = document.getElementById('loginBackToStep1');

    function onCredentialInput() {
        setStep1Error('');
    }
    function autoFillLoginTargetUrlFromHost(input) {
        if (!input || String(input.value || '').trim() !== '' || !window.location || !window.location.hostname) {
            return;
        }
        var host = String(window.location.hostname).toLowerCase();
        var first = host.split('.')[0];
        var reserved = { main: 1, gm: 1, www: 1, localhost: 1 };
        if (reserved[first] || host.indexOf('127.') === 0) {
            return;
        }
        if (window.location.origin) {
            input.value = window.location.origin.replace(/\/+$/, '') + '/';
        }
    }
    var urlIn = document.getElementById('login_target_url');
    if (urlIn) {
        urlIn.addEventListener('input', onCredentialInput);
        autoFillLoginTargetUrlFromHost(urlIn);
    }
    if (un) {
        un.addEventListener('input', onCredentialInput);
    }
    if (pw) {
        pw.addEventListener('input', onCredentialInput);
    }
    var pwdToggle = document.getElementById('loginPwdToggle');
    if (pwdToggle && pw) {
        pwdToggle.addEventListener('click', function () {
            var isHidden = pw.type === 'password';
            pw.type = isHidden ? 'text' : 'password';
            pwdToggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            pwdToggle.setAttribute('title', isHidden ? 'Hide password' : 'Show password');
            pwdToggle.innerHTML = isHidden
                ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        });
    }
    function isLoginStep1Visible() {
        var st1 = document.getElementById('loginStep1');
        return !!(st1 && !st1.classList.contains('login-step--hidden') && st1.style.display !== 'none');
    }
    function isLoginStep2Visible() {
        var st2 = document.getElementById('loginStep2');
        return !!(st2 && !st2.classList.contains('login-step--hidden') && st2.style.display !== 'none');
    }
    var loginEnterSubmitLock = false;
    function triggerLoginSubmit() {
        if (loginEnterSubmitLock) {
            return;
        }
        var submitBtn = document.getElementById('login_submit_btn');
        if (submitBtn && !submitBtn.disabled) {
            loginEnterSubmitLock = true;
            submitBtn.click();
            setTimeout(function () {
                loginEnterSubmitLock = false;
            }, 400);
        }
    }
    function handleLoginEnterKey(e) {
        if (e.key !== 'Enter') {
            return;
        }
        if (isLoginStep1Visible()) {
            e.preventDefault();
            if (nextBtn && !nextBtn.disabled) {
                runVerify();
            }
            return;
        }
        if (isLoginStep2Visible()) {
            e.preventDefault();
            e.stopPropagation();
            triggerLoginSubmit();
        }
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            runVerify();
        });
    }
    if (form) {
        form.addEventListener('keydown', handleLoginEnterKey, true);
    }
    var fy = document.getElementById('financial_year_id');
    if (br) {
        br.addEventListener('keydown', handleLoginEnterKey);
    }
    if (fy) {
        fy.addEventListener('keydown', handleLoginEnterKey);
    }

    if (backBtn) {
        backBtn.addEventListener('click', function () {
            verifySeq++;
            resetBranchState();
            setVerifyError('');
            setStep1Error('');
            showStep1();
        });
    }
    if (br) {
        br.addEventListener('change', function () {
            syncLoginDbHiddenFromBranchSelect();
            loadFinancialYears();
            updateLoginButtonEnabled();
        });
    }
    if (form) {
        form.addEventListener('submit', function (e) {
            var st2 = document.getElementById('loginStep2');
            if (st2 && (st2.classList.contains('login-step--hidden') || st2.style.display === 'none')) {
                e.preventDefault();
                setStep1Error('Use Next to sign in, then select branch and year.');
                return;
            }
            if (!credentialsVerified) {
                e.preventDefault();
                setVerifyError('Enter a valid username and password on the first step, then select a branch.');
                return;
            }
            var formEl = document.getElementById('loginForm');
            var superHidden =
                formEl && formEl.querySelector('input[name="login_branch_id"][data-superadmin-hidden="1"]');
            var sbranchHidden =
                formEl && formEl.querySelector('input[name="login_branch_id"][data-superbranch-hidden="1"]');
            if (superHidden || sbranchHidden) {
                return;
            }
            var s = document.getElementById('login_branch_id');
            if (!s || s.value === '') {
                e.preventDefault();
                setVerifyError('Select a branch.');
            }
        });
    }

})();
</script>

</body>
</html>
