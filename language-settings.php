<?php
session_start();
require_once 'config.php';
if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();
if (function_exists('auragold_ensure_app_locale_column')) {
    auragold_ensure_app_locale_column($conn);
}
if (function_exists('auragold_ensure_i18n_cache_table')) {
    auragold_ensure_i18n_cache_table($conn);
}
if (function_exists('auragold_ensure_google_translate_key_column')) {
    auragold_ensure_google_translate_key_column($conn);
}

$msg  = '';
$err  = '';

// Default UI language is English when unset / empty in tbl_settings.
if (isset($conn) && $conn instanceof mysqli) {
    $__loc_row = function_exists('getRecord')
        ? @getRecord("SELECT `id`, `app_locale` FROM `tbl_settings` ORDER BY `id` ASC LIMIT 1")
        : null;
    $__loc_val = is_array($__loc_row) ? trim((string) ($__loc_row['app_locale'] ?? '')) : '';
    if ($__loc_val === '' || $__loc_val === '0') {
        if (function_exists('auragold_save_app_locale')) {
            auragold_save_app_locale($conn, 'en');
        } elseif (is_array($__loc_row) && !empty($__loc_row['id'])) {
            @mysqli_query($conn, "UPDATE `tbl_settings` SET `app_locale` = 'en' WHERE `id` = " . (int) $__loc_row['id'] . " LIMIT 1");
        } else {
            @mysqli_query($conn, "INSERT INTO `tbl_settings` (`id`, `app_locale`) VALUES (1, 'en') ON DUPLICATE KEY UPDATE `app_locale` = 'en'");
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (isset($_POST['auragold_reset_english']) && (string) $_POST['auragold_reset_english'] === '1') {
        if (function_exists('auragold_save_app_locale') && auragold_save_app_locale($conn, 'en')) {
            header('Location: language-settings.php?reset_en=1');
            exit;
        }
        $err = (string) auragold_t('lang_settings.reset_error');
    } elseif (isset($_POST['auragold_remove_google_key']) && (string) $_POST['auragold_remove_google_key'] === '1') {
        if (function_exists('auragold_save_google_translate_api_key') && auragold_save_google_translate_api_key($conn, null)) {
            header('Location: language-settings.php?key_removed=1');
            exit;
        }
        $err = (string) auragold_t('lang_settings.remove_key_error');
    } elseif (isset($_POST['auragold_locale_save']) && (string) $_POST['auragold_locale_save'] === '1') {
        $gnew = trim((string) ($_POST['google_translate_api_key'] ?? ''));
        if ($gnew !== '' && function_exists('auragold_save_google_translate_api_key')) {
            auragold_save_google_translate_api_key($conn, $gnew);
        }
        $code = isset($_POST['app_locale']) ? (string) $_POST['app_locale'] : 'en';
        if (function_exists('auragold_save_app_locale') && auragold_save_app_locale($conn, $code)) {
            @set_time_limit(300);
            $n = 0;
            if (function_exists('auragold_sanitize_app_locale') && function_exists('auragold_prewarm_i18n_for_locale')) {
                $n = (int) auragold_prewarm_i18n_for_locale($conn, auragold_sanitize_app_locale($code));
            }
            $redir = 'language-settings.php?saved=1&n=' . (int) $n . '&warmed=1';
            if ($gnew !== '') {
                $redir .= '&key_ok=1';
            }
            header('Location: ' . $redir);
            exit;
        }
        $err = (string) auragold_t('lang_settings.save_error');
    }
}

$parts = [];
if (isset($_GET['reset_en']) && (string) $_GET['reset_en'] === '1') {
    $parts[] = (string) auragold_t('lang_settings.english_reset');
} elseif (isset($_GET['key_removed']) && (string) $_GET['key_removed'] === '1') {
    $parts[] = (string) auragold_t('lang_settings.key_removed');
} elseif (isset($_GET['saved']) && (string) $_GET['saved'] === '1') {
    $line = (string) auragold_t('lang_settings.saved');
    $pwN  = (int) ($_GET['n'] ?? 0);
    if (isset($_GET['warmed']) && (string) $_GET['warmed'] === '1') {
        if ($pwN > 0) {
            $line .= ' ' . sprintf((string) auragold_t('lang_settings.prewarm_done'), $pwN);
        } else {
            $line .= ' ' . (string) auragold_t('lang_settings.prewarm_none');
        }
    }
    if (isset($_GET['key_ok']) && (string) $_GET['key_ok'] === '1') {
        $line .= ' ' . (string) auragold_t('lang_settings.key_saved');
    }
    $parts[] = $line;
}
$msg = trim(implode(' ', array_filter($parts)));

$current  = function_exists('auragold_get_locale') ? auragold_get_locale() : 'en';
$current  = trim((string) $current);
if ($current === '' || $current === '0') {
    $current = 'en';
}
// Normalize English variants to plain "en" for the dropdown default.
if (preg_match('/^en(\-[A-Za-z0-9]+)*$/i', $current)) {
    $current = 'en';
}
$hasKey   = function_exists('auragold_get_google_translate_api_key') && auragold_get_google_translate_api_key() !== '';
$hasKeyDb = function_exists('auragold_is_google_translate_api_key_in_database') && isset($conn) && auragold_is_google_translate_api_key_in_database($conn);
$html_lang = function_exists('auragold_get_html_lang') ? auragold_get_html_lang() : 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($html_lang, ENT_QUOTES, 'UTF-8'); ?>" class="default-style">
<head>
    <title><?php echo htmlspecialchars(auragold_t('lang_settings.page_title'), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <link rel="stylesheet" href="assets/libs/select2/select2.css" />
    <style>
        :root { --fs-page-navy: #11294b; }
        .auragold-lang-page { padding: 24px; max-width: 720px; margin: 0 auto; }
        .auragold-lang-page .fs-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 24px; }
        .auragold-lang-page h1 { font-size: 1.4rem; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
        .auragold-lang-page .fs-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
        .auragold-lang-page .fs-field label { font-size: 0.8rem; font-weight: 600; color: #334155; }
        .auragold-lang-page .fs-hint { font-size: 0.75rem; color: #94a3b8; margin: 0 0 8px; line-height: 1.45; }
        .auragold-lang-page .fs-btn { border: none; border-radius: 8px; padding: 10px 20px; font-weight: 600; cursor: pointer; font-size: 0.9rem; background: linear-gradient(135deg, #11294b, #0d1f38); color: #fff; }
        .auragold-lang-page .fs-btn:hover { opacity: 0.95; }
        .auragold-lang-page .fs-btn-mute { background: #e2e8f0; color: #0f172a; }
        .auragold-lang-page .fs-btn-mute:hover { background: #cbd5e1; }
        .auragold-lang-page .fs-ok { color: #059669; font-size: 0.9rem; }
        .auragold-lang-page .fs-err { color: #dc2626; font-size: 0.9rem; }
        .auragold-lang-page .fs-lang-wrap .select2-container { width: 100% !important; max-width: 100%; }
        .auragold-lang-page .fs-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="set-software-wrapper">
                <?php include 'set-software-sidebar.php'; ?>
                <div class="set-software-main">
                    <?php include __DIR__ . '/includes/set-software-branch-banner.php'; ?>
                    <div class="auragold-lang-page">
                        <h1><?php echo htmlspecialchars(auragold_t('lang_settings.heading'), ENT_QUOTES, 'UTF-8'); ?></h1>
                        <?php if (isset($_GET['refreshed_langs'])): ?>
                            <p class="fs-ok"><?php echo htmlspecialchars((string) auragold_t('lang_settings.refresh_languages'), ENT_QUOTES, 'UTF-8'); ?> &mdash; <?php echo $hasKey ? 'OK' : 'Fallback list.'; ?></p>
                        <?php endif; ?>
                        <?php if ($msg !== ''): ?><p class="fs-ok"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                        <?php if ($err !== ''): ?><p class="fs-err"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                        <form class="fs-card" method="post" action="language-settings.php" autocomplete="off" id="auragoldLangForm">
                            <div class="fs-field">
                                <label for="google_translate_api_key"><?php echo htmlspecialchars((string) auragold_t('lang_settings.google_api_key_label'), ENT_QUOTES, 'UTF-8'); ?></label>
                                <input type="password" class="form-control" name="google_translate_api_key" id="google_translate_api_key" value="" placeholder="<?php echo htmlspecialchars((string) auragold_t('lang_settings.google_api_key_ph'), ENT_QUOTES, 'UTF-8'); ?>"
                                    autocomplete="new-password">
                                <?php if (!empty($hasKeyDb)): ?><p class="fs-hint" style="margin-top:4px">•••• (<?php echo (string) (function_exists('auragold_t') ? 'saved' : 'saved'); ?>) &mdash; paste a new key to replace, or leave blank when saving to keep the current one.</p><?php endif; ?>
                            </div>
                            <div class="fs-field fs-lang-wrap">
                                <label for="app_locale"><?php echo htmlspecialchars((string) auragold_t('lang_settings.label'), ENT_QUOTES, 'UTF-8'); ?></label>
                                <select class="form-control" id="app_locale" name="app_locale" required
                                    data-saved="<?php echo htmlspecialchars($current, ENT_QUOTES, 'UTF-8'); ?>"></select>
                            </div>
                            <p class="fs-hint">
                                <a href="language-list-json.php" target="_blank" rel="noopener">JSON</a> &middot; <a href="#" id="auragoldRedownloadLangs" data-refresh="language-list-json.php?refresh=1"><?php echo htmlspecialchars((string) auragold_t('lang_settings.refresh_languages'), ENT_QUOTES, 'UTF-8'); ?></a>
                            </p>
                            <div class="fs-actions" style="margin-top:8px; margin-bottom:8px">
                                <button type="submit" class="fs-btn" name="auragold_locale_save" value="1"><?php echo htmlspecialchars((string) auragold_t('lang_settings.save'), ENT_QUOTES, 'UTF-8'); ?></button>
                            </div>
                        </form>
                        <form method="post" action="language-settings.php" class="fs-card" style="padding: 16px 24px; margin-top: 12px;">
                            <p style="margin:0 0 10px; font-size: 0.85rem; color: #64748b;"><?php if ($hasKeyDb): ?><?php echo htmlspecialchars((string) auragold_t('lang_settings.remove_google_key'), ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></p>
                            <div class="fs-actions">
                                <?php if ($hasKeyDb): ?>
                                <button type="submit" class="fs-btn fs-btn-mute" name="auragold_remove_google_key" value="1" onclick="return confirm('Remove the stored API key?');"><?php echo htmlspecialchars((string) auragold_t('lang_settings.remove_google_key'), ENT_QUOTES, 'UTF-8'); ?></button>
                                <?php endif; ?>
                                <button type="submit" class="fs-btn fs-btn-mute" name="auragold_reset_english" value="1" onclick="return confirm('Set UI language to English (en)?');"><?php echo htmlspecialchars((string) auragold_t('lang_settings.reset_to_english'), ENT_QUOTES, 'UTF-8'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/libs/select2/select2.js"></script>
    <script>
    (function () {
        if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.select2) { return; }
        var $ = jQuery;
        var $sel = $('#app_locale');
        var saved = ($sel.data('saved') || 'en').toString();
        if (!saved || saved === '0') { saved = 'en'; }
        $sel.select2({ width: '100%', placeholder: 'Type to search…' });
        // Always seed English first so default is visible even before AJAX finishes.
        $sel.append(new Option('English (en)', 'en', true, saved === 'en'));
        $.getJSON('language-list-json.php')
            .done(function (data) {
                var list = (data && data.languages) ? data.languages : [];
                var hasEn = false;
                list.forEach(function (r) {
                    if (!r || !r.language) { return; }
                    if (String(r.language) === 'en') {
                        hasEn = true;
                        return; // already seeded above
                    }
                    var t = (r.name || r.language) + ' (' + r.language + ')';
                    var o = new Option(t, r.language, false, r.language === saved);
                    $sel.append(o);
                });
                if (!hasEn && list.length === 0) {
                    // keep seeded English only
                }
                $sel.val(saved || 'en').trigger('change');
            })
            .fail(function () {
                $sel.val('en').trigger('change');
            });
        var rd = document.getElementById('auragoldRedownloadLangs');
        if (rd) {
            rd.addEventListener('click', function (e) {
                e.preventDefault();
                var u = (this.getAttribute('data-refresh') || 'language-list-json.php?refresh=1') + '&_ts=' + (new Date()).getTime();
                if (!confirm('Redownload the language list from Google (or fallback)?')) { return; }
                jQuery.getJSON(u).done(function () {
                    window.location.href = 'language-settings.php?refreshed_langs=1';
                }).fail(function () {
                    alert('Could not refresh. Check the API key and network.');
                });
            });
        }
    })();
    </script>
</body>
</html>
