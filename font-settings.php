<?php
session_start();
require_once 'config.php';
if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();
require_once __DIR__ . '/includes/auragold_ui_font_settings.php';
auragold_ensure_branch_id_on_settings_tables($conn);
$settings_branch_id = auragold_settings_branch_id();
auragold_ensure_app_ui_font_json_column($conn);
$msg = '';
$err = '';
$fontsCacheFile = __DIR__ . '/assets/data/google-fonts-metadata.cache.json';
if (isset($_GET['refresh_fonts']) && (string) $_GET['refresh_fonts'] === '1' && is_file($fontsCacheFile)) {
    @unlink($fontsCacheFile);
    header('Location: font-settings.php?fonts_busted=1');
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (isset($_POST['auragold_font_reset'])) {
        if (@mysqli_query($conn, 'UPDATE `tbl_settings` SET `app_ui_font_json` = NULL WHERE `id` = 1 LIMIT 1')
            || @mysqli_query($conn, 'UPDATE `tbl_settings` SET `app_ui_font_json` = NULL LIMIT 1')) {
            header('Location: font-settings.php?reset=1');
            exit;
        }
        $err = 'Could not reset. Try again.';
    } elseif (isset($_POST['auragold_font_save'])) {
        if (auragold_save_app_ui_font_settings($conn, $_POST)) {
            header('Location: font-settings.php?saved=1');
            exit;
        }
        $err = 'Save failed. Check database permissions.';
    }
}
if (isset($_GET['saved'])) {
    $msg = 'Font settings saved. Refresh other tabs to see changes.';
}
if (isset($_GET['reset'])) {
    $msg = 'Font settings reset to default (GoldMatrix standard).';
}
if (isset($_GET['fonts_busted'])) {
    $msg = 'Google Fonts list cache cleared. The latest catalog will be downloaded on the next load of this form.';
}
$fs = auragold_get_app_ui_font_settings($conn);
$weights = [300, 400, 500, 600, 700];
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Font Setting - Set Software - GoldMatrix</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <style>
        :root { --fs-page-navy: #11294b; }
        .auragold-font-page { padding: 24px; max-width: 720px; margin: 0 auto; }
        .auragold-font-page .fs-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 24px; }
        .auragold-font-page h1 { font-size: 1.4rem; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
        .auragold-font-page .fs-lead { color: #64748b; font-size: 0.9rem; margin: 0 0 20px; line-height: 1.5; }
        .auragold-font-page .fs-sec { margin-top: 22px; padding-top: 18px; border-top: 1px solid #e2e8f0; }
        .auragold-font-page .fs-sec h2 { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--fs-page-navy); margin: 0 0 14px; }
        .auragold-font-page .fs-row { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; margin-bottom: 14px; }
        .auragold-font-page .fs-field { display: flex; flex-direction: column; gap: 4px; min-width: 180px; flex: 1; }
        .auragold-font-page .fs-field label { font-size: 0.8rem; font-weight: 600; color: #334155; }
        .auragold-font-page .fs-field input, .auragold-font-page .fs-field select { max-width: 100%; }
        .auragold-font-page .fs-btns { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 22px; padding-top: 18px; border-top: 1px solid #e2e8f0; }
        .auragold-font-page .fs-btn { border: none; border-radius: 8px; padding: 10px 20px; font-weight: 600; cursor: pointer; font-size: 0.9rem; }
        .auragold-font-page .fs-btn-primary { background: linear-gradient(135deg, #11294b, #0d1f38); color: #fff; }
        .auragold-font-page .fs-btn-primary:hover { opacity: 0.95; }
        .auragold-font-page .fs-btn-muted { background: #f1f5f9; color: #334155; }
        .auragold-font-page .fs-hint { font-size: 0.75rem; color: #94a3b8; margin-top: 4px; }
        .auragold-font-page .fs-ok { color: #059669; font-size: 0.9rem; }
        .auragold-font-page .fs-err { color: #dc2626; font-size: 0.9rem; }
        .auragold-font-page .fs-font-select-wrap { flex: 2; min-width: 260px; max-width: 100%; }
        .auragold-font-page .fs-font-select-wrap .select2-container { width: 100% !important; }
        .auragold-font-page .fs-font-select-wrap .select2-container--default .select2-selection--single {
            min-height: 38px; border: 1px solid #ced4da; border-radius: 0.25rem; padding: 0 8px; background: #fff;
        }
        .auragold-font-page .fs-font-select-wrap .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px; padding-left: 0;
        }
        .auragold-font-page .fs-font-select-wrap .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
    </style>
    <link rel="stylesheet" href="assets/libs/select2/select2.css" />
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="set-software-wrapper">
                <?php include 'set-software-sidebar.php'; ?>
                <div class="set-software-main">
                    <?php include __DIR__ . '/includes/set-software-branch-banner.php'; ?>
                    <div class="auragold-font-page">
                        <h1>Font Setting</h1>
                        <p class="fs-lead">Set the default <strong>font family</strong>, <strong>size</strong>, and <strong>weight</strong> for the whole application (forms, tables, and top menu). Use a Google Fonts stylesheet URL so the family loads correctly. Changing settings applies after save on the next full page load.</p>
                        <?php if ($msg): ?><p class="fs-ok"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>
                        <?php if ($err): ?><p class="fs-err"><?php echo htmlspecialchars($err); ?></p><?php endif; ?>
                        <form class="fs-card" method="post" action="font-settings.php" autocomplete="off">
                            <div>
                                <div class="fs-sec" style="margin-top:0;border-top:0;padding-top:0">
                                    <h2>Web font (Google)</h2>
                                    <p class="fs-hint" id="ffamilyLoadStatus" style="margin-bottom:10px;">Loading Google Fonts list…</p>
                                    <div class="fs-row">
                                        <div class="fs-field fs-font-select-wrap">
                                            <label for="ffamily">Font family</label>
                                            <select class="form-control" id="ffamily" name="font_family" data-saved="<?php echo htmlspecialchars($fs['font_family'], ENT_QUOTES, 'UTF-8'); ?>" data-saved-href="<?php echo htmlspecialchars($fs['google_fonts_href'], ENT_QUOTES, 'UTF-8'); ?>"></select>
                                            <span class="fs-hint">Search to pick any family from <a href="https://fonts.google.com" target="_blank" rel="noopener">Google Fonts</a>, or type a name if it’s missing. The link below is filled for common weights. Catalog is cached ~7 days — <a href="font-settings.php?refresh_fonts=1">redownload the font list from Google</a> if needed.</span>
                                        </div>
                                    </div>
                                    <div class="fs-row">
                                        <div class="fs-field" style="flex:2;min-width:280px">
                                            <label for="ghref">Google Fonts CSS URL</label>
                                            <input type="url" class="form-control" id="ghref" name="google_fonts_href" value="<?php echo htmlspecialchars($fs['google_fonts_href']); ?>" size="60" required>
                                            <span class="fs-hint">Updated when you change the font above (weights 300–700, italics). For unusual families you can paste a custom link from Google Fonts.</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="fs-sec">
                                    <h2>App body (global)</h2>
                                    <div class="fs-row">
                                        <div class="fs-field">
                                            <label for="bpx">Font size (px)</label>
                                            <input type="number" class="form-control" id="bpx" name="body_size_px" value="<?php echo (int) $fs['body_size_px']; ?>" min="10" max="24" required>
                                        </div>
                                        <div class="fs-field">
                                            <label for="bwt">Font weight</label>
                                            <select class="form-control" id="bwt" name="body_weight" required>
                                                <?php foreach ($weights as $w): ?>
                                                <option value="<?php echo $w; ?>"<?php echo (int) $fs['body_weight'] === $w ? ' selected' : ''; ?>><?php echo $w; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="fs-field">
                                            <label for="blh">Line height</label>
                                            <input type="number" class="form-control" id="blh" name="line_height" value="<?php echo htmlspecialchars((string) $fs['line_height']); ?>" min="1" max="2.2" step="0.05" required>
                                        </div>
                                        <div class="fs-field">
                                            <label for="bsp">Letter spacing</label>
                                            <input type="text" class="form-control" id="bsp" name="body_letter_spacing" value="<?php echo htmlspecialchars($fs['body_letter_spacing'] === 'normal' ? 'normal' : (string) $fs['body_letter_spacing']); ?>" placeholder="normal or 0.02em">
                                        </div>
                                    </div>
                                </div>
                                <div class="fs-sec">
                                    <h2>Top menu (DASHBOARDS, UTILITIES, …)</h2>
                                    <div class="fs-row">
                                        <div class="fs-field">
                                            <label for="npx">Menu font size (px)</label>
                                            <input type="number" class="form-control" id="npx" name="topnav_size_px" value="<?php echo (int) $fs['topnav_size_px']; ?>" min="10" max="20" required>
                                        </div>
                                        <div class="fs-field">
                                            <label for="nwt">Inactive weight</label>
                                            <select class="form-control" id="nwt" name="topnav_weight" required>
                                                <?php foreach ($weights as $w): ?>
                                                <option value="<?php echo $w; ?>"<?php echo (int) $fs['topnav_weight'] === $w ? ' selected' : ''; ?>><?php echo $w; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="fs-field">
                                            <label for="nawt">Active tab weight</label>
                                            <select class="form-control" id="nawt" name="topnav_active_weight" required>
                                                <?php foreach ($weights as $w): ?>
                                                <option value="<?php echo $w; ?>"<?php echo (int) $fs['topnav_active_weight'] === $w ? ' selected' : ''; ?>><?php echo $w; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="fs-btns">
                                <button type="submit" class="fs-btn fs-btn-primary" name="auragold_font_save" value="1">Save font settings</button>
                                <button type="submit" class="fs-btn fs-btn-muted" name="auragold_font_reset" value="1" formnovalidate onclick="return confirm('Reset all font options to GoldMatrix defaults?');">Reset to default</button>
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
        var $sel = $('#ffamily');
        var $href = $('#ghref');
        var $status = $('#ffamilyLoadStatus');
        var saved = ($sel.attr('data-saved') || 'Roboto').trim() || 'Roboto';
        var savedHref = ($sel.attr('data-saved-href') || '').trim();

        function buildGoogleCss2Url(familyName) {
            if (!familyName) return '';
            var f = String(familyName).trim();
            var part = encodeURIComponent(f).replace(/%20/g, '%20');
            // css2: ital 0+1, weights 300–700 (common UI range)
            return 'https://fonts.googleapis.com/css2?family=' + part + ':ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap';
        }

        function applyUrlForFamily(familyName) {
            $href.val(buildGoogleCss2Url(familyName));
        }

        $.getJSON('font-settings-fonts-json.php')
            .done(function (meta) {
                var list = (meta && meta.familyMetadataList) ? meta.familyMetadataList : [];
                if (!list.length) {
                    if ($status.length) { $status.text('Could not load the font catalog. You can type a name below, or set the Google Fonts link manually.'); }
                    $sel.append($('<option></option>').val(saved).text(saved).prop('selected', true));
                    $sel.select2({
                        tags: true,
                        placeholder: 'Type or select family name',
                        width: '100%'
                    });
                    if (!savedHref) { applyUrlForFamily(saved); }
                    return;
                }
                if ($status.length) { $status.text('Loaded ' + list.length + ' Google font families. Use the search box to filter.'); }
                var data = list.map(function (x) { return { id: x.family, text: x.family }; });
                data.sort(function (a, b) { return a.text.localeCompare(b.text); });
                $sel.select2({
                    data: data,
                    tags: true,
                    placeholder: 'Type to search, or type a name not in the list…',
                    allowClear: false,
                    width: '100%'
                });
                var has = data.some(function (d) { return d.id === saved; });
                if (has) {
                    $sel.val(saved).trigger('change.select2');
                } else {
                    var opt = new Option(saved, saved, true, true);
                    $sel.append(opt);
                    $sel.val(saved).trigger('change');
                }
                if (savedHref) {
                    $href.val(savedHref);
                } else {
                    applyUrlForFamily(saved);
                }
            })
            .fail(function () {
                if ($status.length) { $status.text('Failed to load the font list. Type a family name in the field below, or set the link manually.'); }
                $sel.append($('<option></option>').val(saved).text(saved).prop('selected', true));
                if ($.fn.select2) {
                    $sel.select2({ width: '100%', tags: true, placeholder: 'e.g. Roboto, Open Sans' });
                }
                if (savedHref) { $href.val(savedHref); } else { applyUrlForFamily(saved); }
            });

        $sel.on('select2:select', function (e) {
            var name = (e && e.params && e.params.data && e.params.data.id) ? e.params.data.id : $sel.val();
            if (name) { applyUrlForFamily(name); }
        });
    })();
    </script>
</body>
</html>
