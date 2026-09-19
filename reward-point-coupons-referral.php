<?php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();
require_once __DIR__ . '/includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/includes/auragold_reward_point_settings.php';
require_once __DIR__ . '/includes/auragold_reward_coupons.php';
require_once __DIR__ . '/includes/auragold_referral_settings.php';

auragold_ensure_branch_id_on_settings_tables($conn);
$settings_branch_id = auragold_settings_branch_id();
auragold_ensure_reward_coupons_table($conn);
auragold_ensure_referral_settings_table($conn);

$reward_settings = auragold_get_reward_point_settings($conn, $settings_branch_id);
$referral_settings = auragold_get_referral_settings($conn, $settings_branch_id);
$msg = '';
$err = '';
$referral_err = '';

/*
 * Metals: full Masters catalog for this UI (matches masters.php carat helper — not branch-exclusive).
 * Reward rules still save per configured branch via tbl_auragold_reward_point_settings.
 */
$reward_metals = getList(
    'SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 ORDER BY display_name ASC, system_name ASC, id ASC'
);
if (!is_array($reward_metals)) {
    $reward_metals = [];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['auragold_reward_tab']) && $_POST['auragold_reward_tab'] === 'reward_point') {
    $raw = isset($_POST['reward_state_json']) ? (string) $_POST['reward_state_json'] : '';
    $dec = $raw !== '' ? json_decode($raw, true) : null;
    if (!is_array($dec)) {
        $err = 'Invalid form data. Please refresh and try again.';
    } elseif ($settings_branch_id <= 0) {
        $err = 'Select a valid branch context before saving.';
    } else {
        $blocks = isset($dec['blocks']) && is_array($dec['blocks']) ? $dec['blocks'] : [];
        $active = isset($dec['active_key']) ? (string) $dec['active_key'] : '_all';
        $check = $blocks[$active] ?? ($blocks['_all'] ?? []);
        if (!is_array($check)) {
            $check = [];
        }
        $ratioL = trim((string) ($check['earn_invoice_value'] ?? ''));
        $ratioR = trim((string) ($check['earn_point'] ?? ''));
        $vd = trim((string) ($check['valid_days'] ?? ''));
        $opv = trim((string) ($check['one_pt_value'] ?? ''));
        $rd = trim((string) ($check['redeem_on'] ?? ''));
        if ($ratioL === '' || $ratioR === '' || $vd === '' || $opv === '') {
            $err = 'Fill all required fields: Earn Point Ratio (both values), Point Valid (Days), and One Pt. Value In Amount.';
        } elseif (!is_numeric($ratioL) || (float) $ratioL <= 0 || !is_numeric($ratioR) || (float) $ratioR <= 0) {
            $err = 'Earn Point Ratio must be positive numbers.';
        } elseif (!ctype_digit((string) $vd) || (int) $vd <= 0) {
            $err = 'Point Valid (Days) must be a whole number greater than zero.';
        } elseif (!is_numeric($opv) || (float) $opv < 0) {
            $err = 'One Pt. Value In Amount must be a valid number.';
        } elseif ($rd !== '' && !in_array($rd, auragold_reward_point_allowed_redeem_on(), true)) {
            $err = 'Redeem On: choose Amount or Making Amount, or leave blank.';
        } else {
            if (auragold_save_reward_point_settings($conn, $settings_branch_id, $dec)) {
                header('Location: reward-point-coupons-referral.php?saved=1&rp_tab=reward_point');
                exit;
            }
            $err = 'Could not save settings. Try again.';
        }
    }
    if ($err !== '' && is_array($dec)) {
        $reward_settings = auragold_reward_point_normalize_settings($dec);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['auragold_reward_tab']) && $_POST['auragold_reward_tab'] === 'referral') {
    $ratioL = trim((string) ($_POST['referral_earn_invoice'] ?? ''));
    $ratioR = trim((string) ($_POST['referral_earn_point'] ?? ''));
    $minInv = trim((string) ($_POST['referral_min_invoice'] ?? ''));
    $vd = trim((string) ($_POST['referral_valid_days'] ?? ''));
    $opv = trim((string) ($_POST['referral_one_pt_value'] ?? ''));
    $rd = trim((string) ($_POST['referral_redeem_on'] ?? ''));
    $otpRaw = isset($_POST['referral_otp']) ? (string) $_POST['referral_otp'] : '0';

    $minInvNum = is_numeric($minInv) ? (float) $minInv : null;
    if ($ratioL === '' || $ratioR === '' || $vd === '' || $opv === '' || $rd === '') {
        $referral_err = 'Fill all required fields: Earn Point Ratio (both values), Point Valid (Days), One Pt. Value In Amount, and Redeem On.';
    } elseif ($minInv !== '' && ($minInvNum === null || $minInvNum < 0)) {
        $referral_err = 'Min Invoice Value must be empty or a valid non-negative number.';
    } elseif (!is_numeric($ratioL) || (float) $ratioL <= 0 || !is_numeric($ratioR) || (float) $ratioR <= 0) {
        $referral_err = 'Earn Point Ratio must be positive numbers.';
    } elseif (!ctype_digit((string) $vd) || (int) $vd <= 0) {
        $referral_err = 'Point Valid (Days) must be a whole number greater than zero.';
    } elseif (!is_numeric($opv) || (float) $opv < 0) {
        $referral_err = 'One Pt. Value In Amount must be a valid number.';
    } elseif ($rd !== '' && !in_array($rd, auragold_reward_point_allowed_redeem_on(), true)) {
        $referral_err = 'Redeem On: choose Amount or Making Amount, or leave blank.';
    } elseif ($settings_branch_id <= 0) {
        $referral_err = 'Select a valid branch context before saving.';
    } else {
        $block = [
            'earn_invoice_value' => $ratioL,
            'earn_point'         => $ratioR,
            'min_invoice'        => $minInv === '' ? '' : (string) $minInvNum,
            'valid_days'         => (string) (int) $vd,
            'one_pt_value'       => $opv,
            'redeem_on'          => $rd,
            'is_otp'             => (($otpRaw === '1' || strtolower($otpRaw) === 'on') ? 1 : 0),
            'auto_round_off'     => !empty($_POST['referral_auto_round']) ? 1 : 0,
        ];
        $toSave = [
            'metal_wise' => 0,
            'active_key' => '_all',
            'blocks'     => [
                '_all' => $block,
            ],
        ];
        if (auragold_save_referral_settings($conn, $settings_branch_id, $toSave)) {
            header('Location: reward-point-coupons-referral.php?saved=refr&rp_tab=referral');
            exit;
        }
        $referral_err = 'Could not save referral settings. Try again.';
    }
    if ($referral_err !== '') {
        $referral_settings = auragold_reward_point_normalize_settings([
            'metal_wise' => 0,
            'active_key' => '_all',
            'blocks'     => [
                '_all' => [
                    'earn_invoice_value' => $ratioL,
                    'earn_point'         => $ratioR,
                    'min_invoice'        => $minInv === '' ? '' : (string) $minInvNum,
                    'valid_days'         => $vd,
                    'one_pt_value'       => $opv,
                    'redeem_on'          => $rd,
                    'is_otp'             => ($otpRaw === '1' || strtolower($otpRaw) === 'on') ? 1 : 0,
                    'auto_round_off'     => !empty($_POST['referral_auto_round']) ? 1 : 0,
                ],
            ],
        ]);
    }
}

$rp_tab_active = 'reward_point';
if ($referral_err !== '') {
    $rp_tab_active = 'referral';
} elseif (isset($_GET['rp_tab']) && in_array((string) $_GET['rp_tab'], ['reward_point', 'coupons', 'referral'], true)) {
    $rp_tab_active = (string) $_GET['rp_tab'];
}

if (isset($_GET['saved'])) {
    if ((string) $_GET['saved'] === 'refr') {
        $msg = 'Referral settings saved.';
        if (!isset($_GET['rp_tab'])) {
            $rp_tab_active = 'referral';
        }
    } else {
        $msg = 'Reward Point settings saved.';
        if (!isset($_GET['rp_tab'])) {
            $rp_tab_active = 'reward_point';
        }
    }
    $reward_settings = auragold_get_reward_point_settings($conn, $settings_branch_id);
    $referral_settings = auragold_get_referral_settings($conn, $settings_branch_id);
}

$page_title = function_exists('auragold_t')
    ? htmlspecialchars(auragold_t('set_software.reward_point_page_title'), ENT_QUOTES, 'UTF-8')
    : 'Reward Point / Coupons / Referral - Set Software';
$reward_state_json = json_encode($reward_settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$redeem_options = [
    'amount'         => 'Amount',
    'making_amount'  => 'Making Amount',
];

$rp_reward_on   = ($rp_tab_active === 'reward_point');
$rp_coupons_on  = ($rp_tab_active === 'coupons');
$rp_referral_on = ($rp_tab_active === 'referral');

$save_form_id = $rp_coupons_on ? '' : ($rp_referral_on ? 'auragold-referral-form' : 'auragold-rp-form');

$ref_blk = isset($referral_settings['blocks']['_all']) && is_array($referral_settings['blocks']['_all'])
    ? $referral_settings['blocks']['_all']
    : auragold_reward_point_default_block();

$ref_roi = htmlspecialchars((string) ($ref_blk['earn_invoice_value'] ?? ''), ENT_QUOTES, 'UTF-8');
$ref_rpt = htmlspecialchars((string) ($ref_blk['earn_point'] ?? ''), ENT_QUOTES, 'UTF-8');
$ref_min = htmlspecialchars((string) ($ref_blk['min_invoice'] ?? ''), ENT_QUOTES, 'UTF-8');
$ref_vd  = htmlspecialchars((string) ($ref_blk['valid_days'] ?? ''), ENT_QUOTES, 'UTF-8');
$ref_opv = htmlspecialchars((string) ($ref_blk['one_pt_value'] ?? ''), ENT_QUOTES, 'UTF-8');
$ref_redeem = isset($ref_blk['redeem_on']) ? (string) $ref_blk['redeem_on'] : '';
$ref_otp_on = !empty($ref_blk['is_otp']);
$ref_round  = !empty($ref_blk['auto_round_off']);
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo $page_title; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include __DIR__ . '/header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <style>
        :root {
            --rp-navy: #11294b;
            --rp-gold: #c5a864;
            --rp-gold-dark: #a68a4a;
        }
        .auragold-rp-page {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 16px 18px 36px;
            box-sizing: border-box;
        }
        .auragold-rp-card {
            background: #fff;
            border: 1px solid rgba(17, 41, 75, 0.12);
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(17, 41, 75, 0.07);
            overflow: hidden;
        }
        .auragold-rp-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 2px solid var(--rp-gold);
            background: linear-gradient(180deg, #f8f6f1 0%, #f0ebe3 100%);
        }
        .auragold-rp-head h1 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--rp-navy);
            letter-spacing: 0.02em;
        }
        .auragold-rp-btn-save {
            border: none;
            border-radius: 8px;
            padding: 10px 22px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            color: #fff;
            background: linear-gradient(135deg, var(--rp-navy), #0d1f38);
        }
        .auragold-rp-btn-save:hover { opacity: 0.94; }
        .auragold-rp-tabs {
            display: flex;
            gap: 0;
            padding: 0 12px;
            border-bottom: 1px solid rgba(17, 41, 75, 0.1);
            background: #faf9f7;
        }
        .auragold-rp-tabs button {
            border: none;
            background: transparent;
            padding: 12px 18px;
            font-weight: 600;
            font-size: 0.88rem;
            color: #64748b;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -1px;
        }
        .auragold-rp-tabs button.active {
            color: var(--rp-navy);
            border-bottom-color: var(--rp-gold);
        }
        .auragold-rp-tabs button:hover:not(.active) { color: var(--rp-navy); }
        .auragold-rp-panel { display: none; padding: 24px 24px 32px; max-height: calc(100vh - 280px); overflow-y: auto; }
        .auragold-rp-panel.active { display: block; }
        /* Extra room below last field + scrollbar clearance */
        #rp-panel-reward_point { padding-bottom: 52px; }
        .auragold-rp-ok { color: #059669; font-size: 0.9rem; margin: 0 0 12px; }
        .auragold-rp-err { color: #dc2626; font-size: 0.9rem; margin: 0 0 12px; }
        .auragold-rp-row { display: flex; flex-wrap: wrap; gap: 16px 18px; align-items: flex-end; margin-bottom: 22px; }
        .auragold-rp-field { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 200px; }
        .auragold-rp-field label { font-size: 0.8rem; font-weight: 600; color: #334155; }
        .auragold-rp-field .req { color: #dc2626; }
        .auragold-rp-hint { font-size: 0.78rem; color: #64748b; line-height: 1.45; margin: 0; }
        .auragold-rp-metal-line {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 16px;
        }
        .auragold-rp-metal-line label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--rp-navy);
            flex-shrink: 0;
        }
        .auragold-rp-chips-scroll {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: rgba(197, 168, 100, 0.75) rgba(17, 41, 75, 0.08);
            padding-bottom: 8px;
            margin-bottom: 2px;
        }
        .auragold-rp-chips-scroll::-webkit-scrollbar {
            height: 7px;
        }
        .auragold-rp-chips-scroll::-webkit-scrollbar-track {
            background: rgba(17, 41, 75, 0.06);
            border-radius: 4px;
        }
        .auragold-rp-chips-scroll::-webkit-scrollbar-thumb {
            background: rgba(17, 41, 75, 0.28);
            border-radius: 4px;
        }
        .auragold-rp-chips-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(197, 168, 100, 0.85);
        }
        .auragold-rp-chips {
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            width: max-content;
        }
        .auragold-rp-chip {
            border: 1px solid rgba(17, 41, 75, 0.2);
            background: #fff;
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--rp-navy);
            cursor: pointer;
            flex-shrink: 0;
            white-space: nowrap;
        }
        .auragold-rp-chip:hover { border-color: var(--rp-gold); }
        .auragold-rp-chip.active {
            background: linear-gradient(135deg, var(--rp-navy), #183a5c);
            color: #fff;
            border-color: var(--rp-navy);
            box-shadow: 0 0 0 2px rgba(197, 168, 100, 0.45);
        }
        /* Metal Wise off: individual metals visible but read-only chips */
        .auragold-rp-chips.auragold-rp-chips--locked .auragold-rp-chip:not([data-key="_all"]) {
            opacity: 0.45;
            cursor: not-allowed;
            pointer-events: none;
        }
        .auragold-rp-metal-hint-secondary { margin-top: 4px !important; }
        .auragold-rp-mw-row { display: flex; align-items: flex-start; gap: 12px 14px; margin-bottom: 18px; flex-wrap: wrap; }
        .auragold-rp-mw-row input[type="checkbox"] { margin-top: 4px; }
        .auragold-rp-mw-title { font-weight: 700; color: var(--rp-navy); font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; }
        .auragold-rp-placeholder { color: #64748b; font-size: 0.9rem; padding: 28px 8px 40px; text-align: center; }
        .auragold-rp-ratio { display: flex; align-items: flex-end; gap: 10px; flex: 1; min-width: 220px; }
        .auragold-rp-ratio .auragold-rp-field { min-width: 0; flex: 1; }
        .auragold-rp-ratio-sep { font-weight: 700; color: var(--rp-gold-dark); padding-bottom: 8px; font-size: 1rem; }
        .auragold-rp-radio-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .auragold-rp-radio-row label { font-weight: 500; margin: 0; }

        /* —— Coupons tab —— */
        .auragold-rp-head-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        #auragold-coupon-toolbar { display: none; flex-wrap: wrap; align-items: center; gap: 8px; }
        #auragold-coupon-toolbar.is-visible { display: flex !important; }
        .auragold-coupon-tb-btn {
            border: 1px solid rgba(17, 41, 75, 0.35);
            background: #fff;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--rp-navy);
            cursor: pointer;
        }
        .auragold-coupon-tb-btn:hover { border-color: var(--rp-gold); color: var(--rp-gold-dark); }
        .auragold-coupon-tb-btn-primary {
            background: linear-gradient(135deg, var(--rp-navy), #0d1f38);
            color: #fff;
            border-color: transparent;
        }
        .auragold-coupon-tb-btn-primary:hover { opacity: 0.94; color: #fff; }
        .auragold-coupon-tb-btn-outline-gold {
            border: 2px solid var(--rp-gold);
            color: var(--rp-navy);
            background: #fffdfb;
        }
        .auragold-coupon-tb-split { margin-left: 4px; }
        .auragold-coupon-filter-wrap { position: relative; display: inline-flex; }
        #auragold-coupon-filter-badge {
            display: none; position: absolute; top: -5px; right: -6px; min-width: 16px; height: 16px;
            padding: 0 4px; border-radius: 8px; background: #dc2626; color: #fff; font-size: 10px;
            align-items: center; justify-content: center; font-weight: 700;
            line-height: 1;
        }
        #auragold-coupon-filter-badge.has-filters { display: flex; }

        .auragold-coupon-layout { padding: 0 16px 20px 18px; }
        .auragold-coupon-split {
            display: grid;
            grid-template-columns: minmax(300px, 1fr) minmax(340px, 1.35fr);
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 991px) {
            .auragold-coupon-split { grid-template-columns: 1fr; }
        }
        .auragold-coupon-form-section {
            border: 1px solid rgba(17, 41, 75, 0.1);
            border-radius: 10px;
            padding: 16px;
            background: #fafaf8;
        }
        .auragold-coupon-form-section label { font-size: 0.8rem; font-weight: 600; color: #334155; display: block; margin-bottom: 6px; }
        .auragold-coupon-form-section .req { color: #dc2626; }
        .auragold-coupon-form-row-full { margin-bottom: 14px; }
        .auragold-coupon-form-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
        .auragold-coupon-expiry-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: flex-end;
        }
        .auragold-coupon-activechk { display: flex; align-items: center; gap: 8px; padding-bottom: 8px; }
        .auragold-coupon-activechk input { margin: 0; }
        .auragold-coupon-activechk span { font-weight: 600; color: var(--rp-navy); font-size: 0.875rem; }

        .auragold-coupon-table-wrap {
            border: 1px solid rgba(17, 41, 75, 0.1);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            min-height: 220px;
            display: flex;
            flex-direction: column;
        }
        .auragold-coupon-table-scroll { overflow: auto; max-height: calc(100vh - 420px); }
        .auragold-coupon-table { width: 100%; font-size: 0.8125rem; margin: 0; border-collapse: collapse; display: none; }
        .auragold-coupon-table thead th {
            background: linear-gradient(180deg, #f2efe8 0%, #ebe7dd 100%);
            color: var(--rp-navy);
            font-weight: 700;
            padding: 10px 8px;
            border-bottom: 2px solid var(--rp-gold);
            white-space: nowrap;
        }
        .auragold-coupon-table tbody td { padding: 8px 8px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
        .auragold-coupon-table tbody tr:nth-child(even) { background: #fcfcfb; }
        .auragold-coupon-table tbody tr[data-coupon-id]:hover { background: rgba(197, 168, 100, 0.12); cursor: pointer; }
        .auragold-coupon-table tbody tr.is-selected { background: rgba(17, 41, 75, 0.08); outline: 2px solid var(--rp-gold); outline-offset: -2px; }
        .auragold-coupon-table .auragold-coupon-td-num { text-align: right; }
        .auragold-coupon-table-empty { padding: 40px 16px; text-align: center; color: #64748b; font-size: 0.9rem; }
        .auragold-coupon-table-mini { padding: 2px 8px; border-radius: 999px; font-weight: 600; font-size: 0.75rem; }
        .auragold-coupon-pill-active { background: rgba(34, 197, 94, 0.15); color: #15803d; }
        .auragold-coupon-pill-off { background: rgba(239, 68, 68, 0.12); color: #b91c1c; }

        .auragold-coupon-pagination {
            border-top: 1px solid rgba(17, 41, 75, 0.08);
            padding: 10px 12px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 0.8125rem;
            color: #475569;
        }
        .auragold-coupon-pagination select { padding: 4px 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.8125rem; }
        .auragold-coupon-pg-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .auragold-coupon-pg-actions button {
            padding: 4px 10px; border: 1px solid rgba(17,41,75,0.25); border-radius: 6px;
            background: #fff; font-size: 0.75rem; font-weight: 600; cursor: pointer; color: var(--rp-navy);
        }
        .auragold-coupon-pg-actions button:disabled { opacity: 0.45; cursor: not-allowed; }

        /* Advance filter modal (navy + gold accents) */
        #couponAdvFilterModal .modal-header {
            border-bottom: 3px solid var(--rp-gold);
            padding: 14px 18px;
        }
        #couponAdvFilterModal .modal-title { font-weight: 700; color: var(--rp-navy); font-size: 1rem; width: 100%; text-align: center; }
        #couponAdvFilterModal .modal-footer { justify-content: center !important; gap: 12px; border-top: none; flex-wrap: wrap; }
        .auragold-coupon-filter-btn-apply {
            border: 2px solid var(--rp-navy) !important; color: var(--rp-navy) !important;
            font-weight: 700; border-radius: 8px; padding-left: 1.25rem !important; padding-right: 1.25rem !important;
            background: #fff !important;
        }
        .auragold-coupon-filter-btn-clear {
            border: 2px solid #dc2626 !important; color: #dc2626 !important;
            font-weight: 700; border-radius: 8px; background: #fff !important;
        }

        /* Referral tab — Description + two-column grid (navy + gold) */
        .auragold-ref-desc-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--rp-navy);
            margin: 0 0 8px;
            letter-spacing: 0.03em;
        }
        .auragold-ref-desc-rule {
            height: 2px;
            background: linear-gradient(90deg, var(--rp-gold) 0%, rgba(197,168,100,0.2) 100%);
            margin: 0 0 20px;
            border-radius: 1px;
        }
        .auragold-ref-ratio-block { margin-bottom: 22px; }
        .auragold-ref-ratio-block > label { margin-bottom: 8px; }
        .auragold-ref-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 28px;
            margin-bottom: 22px;
        }
        @media (max-width: 720px) {
            .auragold-ref-grid { grid-template-columns: 1fr; gap: 0; }
        }
        .auragold-ref-grid .auragold-rp-field { margin-bottom: 22px; }
        .auragold-ref-footer {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 20px 32px;
            margin-top: 8px;
        }
        .auragold-ref-footer .auragold-rp-field { min-width: 180px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="layout-content">
    <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
        <div class="set-software-wrapper">
            <?php include __DIR__ . '/set-software-sidebar.php'; ?>
            <div class="set-software-main">
                <?php include __DIR__ . '/includes/set-software-branch-banner.php'; ?>
                <div class="auragold-rp-page">
                    <div class="auragold-rp-card">
                        <div class="auragold-rp-head">
                            <h1><?php echo function_exists('auragold_t') ? htmlspecialchars(auragold_t('set_software.reward_point_coupons_referral'), ENT_QUOTES, 'UTF-8') : 'Reward Point / Coupons / Referral'; ?></h1>
                            <div class="auragold-rp-head-actions">
                                <?php $save_hide = $rp_coupons_on ? ' style="display:none;"' : ''; ?>
                                <?php $save_form_attr = !$rp_coupons_on && $save_form_id !== '' ? ' form="' . htmlspecialchars($save_form_id, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
                                <button type="submit" class="auragold-rp-btn-save" id="auragold-rp-save" data-tab="<?php echo htmlspecialchars($rp_tab_active, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $save_form_attr . $save_hide; ?>>Save</button>
                                <div id="auragold-coupon-toolbar" aria-label="Coupon actions"<?php echo $rp_coupons_on ? ' class="is-visible"' : ''; ?>>
                                    <div class="dropdown d-inline-block">
                                        <button type="button" class="auragold-coupon-tb-btn auragold-coupon-tb-split dropdown-toggle" data-toggle="dropdown" aria-expanded="false">+ Import</button>
                                        <div class="dropdown-menu">
                                            <span class="dropdown-item-text small text-muted">Import from file (soon)</span>
                                        </div>
                                    </div>
                                    <div class="dropdown d-inline-block">
                                        <button type="button" class="auragold-coupon-tb-btn dropdown-toggle" data-toggle="dropdown" aria-expanded="false">Export</button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="#" id="auragold-coupon-export-csv">Download CSV</a>
                                        </div>
                                    </div>
                                    <div class="auragold-coupon-filter-wrap">
                                        <button type="button" class="auragold-coupon-tb-btn" id="auragold-coupon-open-filter" aria-controls="couponAdvFilterModal"><i class="feather icon-filter" style="vertical-align:middle;"></i></button>
                                        <span id="auragold-coupon-filter-badge" class="is-zero" aria-live="polite"></span>
                                    </div>
                                    <button type="button" class="auragold-coupon-tb-btn" id="auragold-coupon-refresh" title="Refresh"><i class="feather icon-refresh-cw"></i></button>
                                    <button type="button" class="auragold-coupon-tb-btn auragold-coupon-tb-outline-gold" id="auragold-coupon-new">New +</button>
                                    <button type="button" class="auragold-coupon-tb-btn auragold-coupon-tb-primary" id="auragold-coupon-save-inline">Save</button>
                                </div>
                            </div>
                        </div>
                        <div class="auragold-rp-tabs" role="tablist">
                                <button type="button" class="<?php echo $rp_reward_on ? 'active' : ''; ?>" data-rp-tab="reward_point" role="tab" aria-selected="<?php echo $rp_reward_on ? 'true' : 'false'; ?>">Reward Point</button>
                                <button type="button" class="<?php echo $rp_coupons_on ? 'active' : ''; ?>" data-rp-tab="coupons" role="tab" aria-selected="<?php echo $rp_coupons_on ? 'true' : 'false'; ?>">Coupons</button>
                                <button type="button" class="<?php echo $rp_referral_on ? 'active' : ''; ?>" data-rp-tab="referral" role="tab" aria-selected="<?php echo $rp_referral_on ? 'true' : 'false'; ?>">Referral</button>
                        </div>
                        <?php if ($msg !== ''): ?>
                            <p class="auragold-rp-ok px-3 pt-3 mb-0"><?php echo htmlspecialchars($msg); ?></p>
                        <?php endif; ?>
                        <form method="post" action="reward-point-coupons-referral.php" id="auragold-rp-form" autocomplete="off">
                            <input type="hidden" name="auragold_reward_tab" value="reward_point">
                            <input type="hidden" name="reward_state_json" id="reward_state_json" value="">
                            <?php if ($settings_branch_id > 0): ?>
                            <input type="hidden" name="settings_branch_id" value="<?php echo (int) $settings_branch_id; ?>">
                            <?php endif; ?>

                            <div class="auragold-rp-panel<?php echo $rp_reward_on ? ' active' : ''; ?>" id="rp-panel-reward_point" role="tabpanel"<?php echo $rp_reward_on ? '' : ' hidden'; ?>>
                                <?php if ($err): ?><p class="auragold-rp-err"><?php echo htmlspecialchars($err); ?></p><?php endif; ?>

                                <div class="auragold-rp-mw-row">
                                    <input type="checkbox" id="reward_metal_wise" name="reward_metal_wise_ui" value="1"<?php echo !empty($reward_settings['metal_wise']) ? ' checked' : ''; ?>>
                                    <div>
                                        <div class="auragold-rp-mw-title">
                                            Metal Wise Reward
                                            <span class="feather icon-save" style="font-size:1rem;color:var(--rp-gold-dark);opacity:.85;" title="Saved with Save button"></span>
                                        </div>
                                        <p class="auragold-rp-hint">Apply separate reward settings for each metal type for flexible loyalty management.</p>
                                    </div>
                                </div>

                                <div id="reward_metal_section">
                                    <div class="auragold-rp-metal-line">
                                        <label>Metal</label>
                                        <div class="auragold-rp-chips-scroll">
                                            <div class="auragold-rp-chips<?php echo empty($reward_settings['metal_wise']) ? ' auragold-rp-chips--locked' : ''; ?>" id="reward_metal_chips"></div>
                                        </div>
                                    </div>
                                    <?php if (empty($reward_metals)): ?>
                                    <p class="auragold-rp-hint auragold-rp-metal-hint-secondary" role="note">No active metals found. Add metals under Set Software → <strong>Masters</strong>.</p>
                                    <?php endif; ?>
                                </div>

                                <div class="auragold-rp-row">
                                    <div class="auragold-rp-field" style="flex: 2;">
                                        <label>Earn Point Ratio (Value Invoice / Earn Point) <span class="req">*</span></label>
                                        <div class="auragold-rp-ratio">
                                            <div class="auragold-rp-field">
                                                <input type="text" class="form-control" id="fld_earn_invoice_value" placeholder="Invoice value">
                                            </div>
                                            <span class="auragold-rp-ratio-sep">/</span>
                                            <div class="auragold-rp-field">
                                                <input type="text" class="form-control" id="fld_earn_point" placeholder="Points earned">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="auragold-rp-row">
                                    <div class="auragold-rp-field">
                                        <label for="fld_min_invoice">Min Invoice Value</label>
                                        <input type="text" class="form-control" id="fld_min_invoice" placeholder="">
                                    </div>
                                    <div class="auragold-rp-field">
                                        <label for="fld_valid_days">Point Valid (Days) <span class="req">*</span></label>
                                        <input type="text" class="form-control" id="fld_valid_days" inputmode="numeric" placeholder="">
                                    </div>
                                </div>
                                <div class="auragold-rp-row">
                                    <div class="auragold-rp-field">
                                        <label for="fld_one_pt_value">One Pt. Value In Amount <span class="req">*</span></label>
                                        <input type="text" class="form-control" id="fld_one_pt_value" placeholder="">
                                    </div>
                                    <div class="auragold-rp-field">
                                        <label for="fld_redeem_on">Redeem On</label>
                                        <select class="form-control" id="fld_redeem_on">
                                            <option value=""></option>
                                            <?php foreach ($redeem_options as $val => $lab): ?>
                                            <option value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="auragold-rp-row">
                                    <div class="auragold-rp-field">
                                        <label>Is OTP</label>
                                        <div class="auragold-rp-radio-row">
                                            <label><input type="radio" name="fld_is_otp" value="0" checked> Off</label>
                                            <label><input type="radio" name="fld_is_otp" value="1"> On</label>
                                        </div>
                                    </div>
                                    <div class="auragold-rp-field" style="justify-content: flex-end;">
                                        <label class="d-flex align-items-center" style="gap:8px;cursor:pointer;margin-top:22px;">
                                            <input type="checkbox" id="fld_auto_round_off" value="1">
                                            <span>Auto Round Off</span>
                                        </label>
                                    </div>
                                </div>
                                <br>
                                <br>
                            </div>
                        </form>

                            <div class="auragold-rp-panel<?php echo $rp_coupons_on ? ' active' : ''; ?>" id="rp-panel-coupons" role="tabpanel"<?php echo $rp_coupons_on ? '' : ' hidden'; ?>>
                                <input type="hidden" id="coupon_settings_branch_id" value="<?php echo (int) $settings_branch_id; ?>">
                                <div id="coupon-msg" class="mx-3 mt-2 mb-0 small" aria-live="polite"></div>
                                <div class="auragold-coupon-layout">
                                    <div class="auragold-coupon-split">
                                        <div class="auragold-coupon-form-section">
                                            <form id="auragold-coupon-form" autocomplete="off" onsubmit="return false;">
                                                <input type="hidden" id="coupon_edit_id" value="0">
                                                <div class="auragold-coupon-form-row-full">
                                                    <label for="coupon_name">Coupons Name <span class="req">*</span></label>
                                                    <input type="text" class="form-control" id="coupon_name" maxlength="200" placeholder="">
                                                </div>
                                                <div class="auragold-coupon-form-two">
                                                    <div>
                                                        <label for="coupon_code">Coupons Code <span class="req">*</span></label>
                                                        <input type="text" class="form-control" id="coupon_code" maxlength="80" placeholder="" pattern="[A-Za-z0-9_-]+" title="Letters, numbers, hyphen, underscore">
                                                    </div>
                                                    <div>
                                                        <label for="coupon_value">Value <span class="req">*</span></label>
                                                        <input type="text" class="form-control" id="coupon_value" inputmode="decimal" placeholder="0.00">
                                                    </div>
                                                </div>
                                                <div class="auragold-coupon-expiry-row">
                                                    <div>
                                                        <label for="coupon_expiry">Expiry Date</label>
                                                        <div class="d-flex align-items-center" style="gap:8px;">
                                                            <input type="date" class="form-control flex-grow-1" id="coupon_expiry">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary px-2" id="coupon_expiry_clear" title="Clear date"><i class="feather icon-x"></i></button>
                                                        </div>
                                                    </div>
                                                    <label class="auragold-coupon-activechk mb-0">
                                                        <input type="checkbox" id="coupon_active" checked>
                                                        <span>Active</span>
                                                    </label>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="auragold-coupon-table-wrap">
                                            <div class="auragold-coupon-table-scroll">
                                                <table class="auragold-coupon-table" id="auragold_coupon_table">
                                                    <thead>
                                                        <tr>
                                                            <th>Code</th>
                                                            <th>Name</th>
                                                            <th class="auragold-coupon-td-num">Value</th>
                                                            <th>Expiry</th>
                                                            <th>Active</th>
                                                            <th style="width:44px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="auragold_coupon_tbody"></tbody>
                                                </table>
                                                <div class="auragold-coupon-table-empty" id="auragold_coupon_empty">No Data Found</div>
                                            </div>
                                            <div class="auragold-coupon-pagination">
                                                <span id="auragold_coupon_pg_info">Showing 0 to 0 of 0 entries</span>
                                                <div class="d-flex align-items-center flex-wrap gap-2">
                                                    <span>Show</span>
                                                    <select id="auragold_coupon_page_size" aria-label="Page size">
                                                        <option value="10">10</option>
                                                        <option value="25" selected>25</option>
                                                        <option value="50">50</option>
                                                        <option value="100">100</option>
                                                    </select>
                                                    <span>Items</span>
                                                </div>
                                                <div class="auragold-coupon-pg-actions">
                                                    <button type="button" id="auragold_coupon_pg_first">First</button>
                                                    <button type="button" id="auragold_coupon_pg_prev">Previous</button>
                                                    <button type="button" id="auragold_coupon_pg_next">Next</button>
                                                    <button type="button" id="auragold_coupon_pg_last">Last</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <form method="post" action="reward-point-coupons-referral.php" id="auragold-referral-form" autocomplete="off">
                                <input type="hidden" name="auragold_reward_tab" value="referral">
                                <?php if ($settings_branch_id > 0): ?>
                                <input type="hidden" name="settings_branch_id" value="<?php echo (int) $settings_branch_id; ?>">
                                <?php endif; ?>
                                <div class="auragold-rp-panel<?php echo $rp_referral_on ? ' active' : ''; ?>" id="rp-panel-referral" role="tabpanel"<?php echo $rp_referral_on ? '' : ' hidden'; ?>>
                                    <?php if ($referral_err !== ''): ?>
                                    <p class="auragold-rp-err"><?php echo htmlspecialchars($referral_err); ?></p>
                                    <?php endif; ?>
                                    <h2 class="auragold-ref-desc-title">Description</h2>
                                    <div class="auragold-ref-desc-rule" aria-hidden="true"></div>

                                    <div class="auragold-ref-ratio-block auragold-rp-field" style="width:100%; max-width:100%;">
                                        <label>Earn Point Ratio (Value Invoice / Earn Point) <span class="req">*</span></label>
                                        <div class="auragold-rp-ratio">
                                            <div class="auragold-rp-field">
                                                <input type="text" class="form-control" name="referral_earn_invoice" value="<?php echo $ref_roi; ?>" placeholder="Invoice value" autocomplete="off">
                                            </div>
                                            <span class="auragold-rp-ratio-sep">/</span>
                                            <div class="auragold-rp-field">
                                                <input type="text" class="form-control" name="referral_earn_point" value="<?php echo $ref_rpt; ?>" placeholder="Points earned" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="auragold-ref-grid">
                                        <div>
                                            <div class="auragold-rp-field">
                                                <label for="referral_min_invoice">Min Invoice Value</label>
                                                <input type="text" class="form-control" id="referral_min_invoice" name="referral_min_invoice" value="<?php echo $ref_min; ?>" autocomplete="off">
                                            </div>
                                            <div class="auragold-rp-field">
                                                <label for="referral_one_pt_value">One Pt. Value In Amount <span class="req">*</span></label>
                                                <input type="text" class="form-control" id="referral_one_pt_value" name="referral_one_pt_value" value="<?php echo $ref_opv; ?>" autocomplete="off">
                                            </div>
                                        </div>
                                        <div>
                                            <div class="auragold-rp-field">
                                                <label for="referral_valid_days">Point Valid (Days) <span class="req">*</span></label>
                                                <input type="text" class="form-control" id="referral_valid_days" name="referral_valid_days" value="<?php echo $ref_vd; ?>" inputmode="numeric" autocomplete="off">
                                            </div>
                                            <div class="auragold-rp-field">
                                                <label for="referral_redeem_on">Redeem On</label>
                                                <select class="form-control" id="referral_redeem_on" name="referral_redeem_on">
                                                    <option value=""<?php echo $ref_redeem === '' ? ' selected' : ''; ?>></option>
                                                    <?php foreach ($redeem_options as $val => $lab): ?>
                                                    <option value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $ref_redeem === $val ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="auragold-ref-footer">
                                        <div class="auragold-rp-field">
                                            <label>Is OTP</label>
                                            <div class="auragold-rp-radio-row">
                                                <label><input type="radio" name="referral_otp" value="1"<?php echo $ref_otp_on ? ' checked' : ''; ?>> On</label>
                                                <label><input type="radio" name="referral_otp" value="0"<?php echo !$ref_otp_on ? ' checked' : ''; ?>> Off</label>
                                            </div>
                                        </div>
                                        <div class="auragold-rp-field" style="justify-content: flex-end;">
                                            <label class="d-flex align-items-center" style="gap:8px;cursor:pointer;margin-top:22px;">
                                                <input type="checkbox" name="referral_auto_round" value="1" id="referral_auto_round"<?php echo $ref_round ? ' checked' : ''; ?>>
                                                <span>Auto Round Off</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Advance filter -->
<div class="modal fade" id="couponAdvFilterModal" tabindex="-1" role="dialog" aria-hidden="true" aria-labelledby="couponAdvFilterModalTitle">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header position-relative py-3">
                <h5 class="modal-title mx-auto mb-0" id="couponAdvFilterModalTitle">Advance Filter</h5>
                <button type="button" class="close position-absolute" style="right:12px;top:50%;transform:translateY(-50%);" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="d-block font-weight-bold small text-muted" for="modal_f_date_from">Date Range</label>
                    <div class="form-row">
                        <div class="col-6"><input type="date" class="form-control form-control-sm" id="modal_f_date_from"></div>
                        <div class="col-6"><input type="date" class="form-control form-control-sm" id="modal_f_date_to"></div>
                    </div>
                    <span class="text-muted small">Filters by expiry date (from / to).</span>
                </div>
                <div class="mb-3">
                    <label for="modal_f_code" class="font-weight-bold small">Coupon Code</label>
                    <input type="text" class="form-control" id="modal_f_code" maxlength="80" autocomplete="off" placeholder="">
                </div>
                <div class="mb-2 d-flex align-items-center">
                    <input type="checkbox" id="modal_f_active_only" style="margin-right:10px;margin-top:0;">
                    <label class="mb-0 font-weight-bold" for="modal_f_active_only">Active</label>
                </div>
            </div>
            <div class="modal-footer pb-4">
                <button type="button" class="btn auragold-coupon-filter-btn-apply px-5" id="auragold_coupon_filter_apply">Apply Filter</button>
                <button type="button" class="btn auragold-coupon-filter-btn-clear px-5" id="auragold_coupon_filter_clear">Clear Filter</button>
            </div>
        </div>
    </div>
</div>

<!-- Popper + Bootstrap JS: required for modals (.modal) and toolbar dropdowns (this page skips footer-script.php). -->
<script src="assets/libs/popper/popper.js"></script>
<script src="assets/js/bootstrap.js"></script>

<script>
(function () {
    var metals = <?php echo json_encode($reward_metals, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var state = <?php echo $reward_state_json; ?>;

    function ensureBlock(key) {
        if (!state.blocks) state.blocks = {};
        if (!state.blocks[key]) {
            state.blocks[key] = {
                earn_invoice_value: '',
                earn_point: '',
                min_invoice: '',
                valid_days: '',
                one_pt_value: '',
                redeem_on: '',
                is_otp: 0,
                auto_round_off: 0
            };
        }
        return state.blocks[key];
    }

    function metalKey(m) {
        return m === '_all' ? '_all' : String(m);
    }

    function syncFormFromState() {
        var k = metalKey(state.active_key || '_all');
        var b = ensureBlock(k);
        document.getElementById('fld_earn_invoice_value').value = b.earn_invoice_value || '';
        document.getElementById('fld_earn_point').value = b.earn_point || '';
        document.getElementById('fld_min_invoice').value = b.min_invoice || '';
        document.getElementById('fld_valid_days').value = b.valid_days || '';
        document.getElementById('fld_one_pt_value').value = b.one_pt_value || '';
        document.getElementById('fld_redeem_on').value =
            (b.redeem_on === undefined || b.redeem_on === null || String(b.redeem_on).trim() === '') ? ''
                : String(b.redeem_on);
        var otp0 = document.querySelector('input[name="fld_is_otp"][value="0"]');
        var otp1 = document.querySelector('input[name="fld_is_otp"][value="1"]');
        if ((b.is_otp | 0) === 1) { if (otp1) otp1.checked = true; }
        else { if (otp0) otp0.checked = true; }
        document.getElementById('fld_auto_round_off').checked = !!(b.auto_round_off | 0);
    }

    function readFormIntoState() {
        var k = metalKey(state.active_key || '_all');
        var b = ensureBlock(k);
        b.earn_invoice_value = document.getElementById('fld_earn_invoice_value').value.trim();
        b.earn_point = document.getElementById('fld_earn_point').value.trim();
        b.min_invoice = document.getElementById('fld_min_invoice').value.trim();
        b.valid_days = document.getElementById('fld_valid_days').value.trim();
        b.one_pt_value = document.getElementById('fld_one_pt_value').value.trim();
        b.redeem_on = document.getElementById('fld_redeem_on').value;
        b.is_otp = document.querySelector('input[name="fld_is_otp"]:checked') && document.querySelector('input[name="fld_is_otp"]:checked').value === '1' ? 1 : 0;
        b.auto_round_off = document.getElementById('fld_auto_round_off').checked ? 1 : 0;
    }

    function renderChips() {
        var wrap = document.getElementById('reward_metal_chips');
        if (!wrap) return;
        wrap.classList.toggle('auragold-rp-chips--locked', !chkMw.checked);
        wrap.innerHTML = '';
        var allBtn = document.createElement('button');
        allBtn.type = 'button';
        allBtn.className = 'auragold-rp-chip' + (metalKey(state.active_key) === '_all' ? ' active' : '');
        allBtn.setAttribute('data-key', '_all');
        allBtn.textContent = 'All';
        wrap.appendChild(allBtn);
        (metals || []).forEach(function (m) {
            var id = String(m.id);
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'auragold-rp-chip' + (metalKey(state.active_key) === id ? ' active' : '');
            btn.setAttribute('data-key', id);
            btn.textContent = m.display_name || m.system_name || id;
            wrap.appendChild(btn);
        });
        wrap.querySelectorAll('.auragold-rp-chip').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!chkMw.checked && btn.getAttribute('data-key') !== '_all') {
                    return;
                }
                readFormIntoState();
                state.active_key = btn.getAttribute('data-key') || '_all';
                syncFormFromState();
                renderChips();
            });
        });
    }

    var chkMw = document.getElementById('reward_metal_wise');
    chkMw.addEventListener('change', function () {
        state.metal_wise = chkMw.checked ? 1 : 0;
        if (!chkMw.checked) {
            readFormIntoState();
            state.active_key = '_all';
            syncFormFromState();
        }
        renderChips();
    });

    ['fld_earn_invoice_value', 'fld_earn_point', 'fld_min_invoice', 'fld_valid_days', 'fld_one_pt_value', 'fld_redeem_on', 'fld_auto_round_off'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', function () { readFormIntoState(); });
        if (el) el.addEventListener('change', function () { readFormIntoState(); });
    });
    document.querySelectorAll('input[name="fld_is_otp"]').forEach(function (r) {
        r.addEventListener('change', function () { readFormIntoState(); });
    });

    document.querySelectorAll('[data-rp-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.getAttribute('data-rp-tab');
            document.querySelectorAll('[data-rp-tab]').forEach(function (b) {
                b.classList.toggle('active', b === btn);
                b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
            });
            document.querySelectorAll('.auragold-rp-panel').forEach(function (p) {
                var on = p.id === 'rp-panel-' + tab;
                p.classList.toggle('active', on);
                if (on) p.removeAttribute('hidden'); else p.setAttribute('hidden', 'hidden');
            });
            var saveBtn = document.getElementById('auragold-rp-save');
            var couponTb = document.getElementById('auragold-coupon-toolbar');
            if (saveBtn) {
                if (tab === 'coupons') {
                    saveBtn.style.display = 'none';
                    saveBtn.removeAttribute('form');
                } else {
                    saveBtn.style.display = '';
                    saveBtn.setAttribute('form', tab === 'referral' ? 'auragold-referral-form' : 'auragold-rp-form');
                }
                saveBtn.setAttribute('data-tab', tab);
            }
            if (couponTb) {
                if (tab === 'coupons') couponTb.classList.add('is-visible');
                else couponTb.classList.remove('is-visible');
            }
            if (tab === 'coupons' && window.auragoldCouponsOnTabShown) window.auragoldCouponsOnTabShown();
        });
    });

    document.getElementById('auragold-rp-form').addEventListener('submit', function (e) {
        var rewardPanel = document.getElementById('rp-panel-reward_point');
        var rewardActive = rewardPanel && rewardPanel.classList.contains('active');
        if (!rewardActive) {
            e.preventDefault();
            return false;
        }
        readFormIntoState();
        state.metal_wise = document.getElementById('reward_metal_wise').checked ? 1 : 0;
        if (!document.getElementById('reward_metal_wise').checked) {
            state.active_key = '_all';
        }
        document.getElementById('reward_state_json').value = JSON.stringify(state);
    });

    state.metal_wise = chkMw.checked ? 1 : 0;
    if (!state.active_key) state.active_key = '_all';
    if (!chkMw.checked && metalKey(state.active_key) !== '_all') {
        state.active_key = '_all';
    }
    ensureBlock('_all');
    syncFormFromState();
    renderChips();

    (function initRpSaveForActiveTab() {
        var t = <?php echo json_encode($rp_tab_active, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        var saveBtn = document.getElementById('auragold-rp-save');
        var couponTb = document.getElementById('auragold-coupon-toolbar');
        if (saveBtn) {
            if (t === 'coupons') {
                saveBtn.style.display = 'none';
                saveBtn.removeAttribute('form');
            } else {
                saveBtn.style.display = '';
                saveBtn.setAttribute('form', t === 'referral' ? 'auragold-referral-form' : 'auragold-rp-form');
            }
        }
        if (couponTb && t === 'coupons') {
            couponTb.classList.add('is-visible');
        }
        if (t === 'coupons' && window.auragoldCouponsOnTabShown) {
            window.auragoldCouponsOnTabShown();
        }
    })();
})();
</script>
<script>
(function () {
    var API = 'ajax/reward-coupons.php';
    var branchEl = document.getElementById('coupon_settings_branch_id');
    var branchId = branchEl ? parseInt(branchEl.value || '0', 10) || 0 : 0;
    var page = 1;
    var pageSize = 25;
    var total = 0;
    var totalPages = 1;
    var listFilters = { f_date_from: '', f_date_to: '', f_code: '', f_active_only: 0 };
    function qs(sel) { return document.querySelector(sel); }

    function couponMsg(html, isErr) {
        var el = document.getElementById('coupon-msg');
        if (!el) return;
        if (!html) {
            el.innerHTML = '';
            el.className = 'mx-3 mt-2 mb-0 small';
            return;
        }
        el.innerHTML = html;
        el.className = 'mx-3 mt-2 mb-0 small ' + (isErr ? 'text-danger font-weight-bold' : 'text-success');
    }

    function updateFilterBadge(n) {
        var b = document.getElementById('auragold-coupon-filter-badge');
        if (!b) return;
        b.textContent = n > 0 ? String(n) : '';
        if (n > 0) b.classList.add('has-filters');
        else b.classList.remove('has-filters');
    }

    function fmtDisplayDate(ymd) {
        if (!ymd) return '—';
        var p = String(ymd).split('-');
        if (p.length !== 3) return ymd;
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function postCoupon(fd, cb) {
        if (typeof fetch === 'undefined') { couponMsg('Browser too old for fetch.', true); return; }
        fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) { cb(null, j); })
            .catch(function (e) { cb(e); });
    }

    function renderRows(rows) {
        var tb = document.getElementById('auragold_coupon_tbody');
        var emptyEl = document.getElementById('auragold_coupon_empty');
        var table = document.getElementById('auragold_coupon_table');
        if (!tb || !emptyEl) return;
        tb.innerHTML = '';
        if (!rows || !rows.length) {
            emptyEl.style.display = 'block';
            if (table) table.style.display = 'none';
            return;
        }
        emptyEl.style.display = 'none';
        if (table) table.style.display = 'table';
        rows.forEach(function (r) {
            var tr = document.createElement('tr');
            tr.setAttribute('data-coupon-id', r.id);
            var act = r.is_active ? '<span class="auragold-coupon-table-mini auragold-coupon-pill-active">Yes</span>' : '<span class="auragold-coupon-table-mini auragold-coupon-pill-off">No</span>';
            tr.innerHTML = '<td>' + escapeHtml(r.coupon_code || '') + '</td><td>' + escapeHtml(r.coupon_name || '') + '</td><td class="auragold-coupon-td-num">' + escapeHtml(String(r.coupon_value)) + '</td><td>' + escapeHtml(fmtDisplayDate(r.expiry_date)) + '</td><td>' + act + '</td><td><button type="button" class="btn btn-sm btn-link text-danger p-0 coupon-row-del" title="Delete">&times;</button></td>';
            tr.addEventListener('click', function (ev) {
                if (ev.target && ev.target.closest && ev.target.closest('.coupon-row-del')) return;
                loadRowIntoForm(r.id);
            });
            var delBtn = tr.querySelector('.coupon-row-del');
            if (delBtn) delBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (!confirm('Delete this coupon?')) return;
                couponDelete(r.id);
            });
            tb.appendChild(tr);
        });
    }

    function syncPagination() {
        var info = document.getElementById('auragold_coupon_pg_info');
        var from = total === 0 ? 0 : (page - 1) * pageSize + 1;
        var to = Math.min(total, page * pageSize);
        if (info) info.textContent = 'Showing ' + from + ' to ' + to + ' of ' + total + ' entries';
        var fBtn = qs('#auragold_coupon_pg_first');
        var pBtn = qs('#auragold_coupon_pg_prev');
        var nBtn = qs('#auragold_coupon_pg_next');
        var lBtn = qs('#auragold_coupon_pg_last');
        if (fBtn) fBtn.disabled = page <= 1;
        if (pBtn) pBtn.disabled = page <= 1;
        if (nBtn) nBtn.disabled = page >= totalPages || totalPages <= 1;
        if (lBtn) lBtn.disabled = page >= totalPages || totalPages <= 1;
    }

    window.auragoldCouponsReloadList = function () {
        if (branchId <= 0) {
            couponMsg('Save branch context unavailable. Use branch banner to select branch.', true);
            renderRows([]);
            total = 0; totalPages = 1; syncPagination();
            return;
        }
        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('page', String(page));
        fd.append('page_size', String(pageSize));
        fd.append('f_date_from', listFilters.f_date_from);
        fd.append('f_date_to', listFilters.f_date_to);
        fd.append('f_code', listFilters.f_code);
        if (listFilters.f_active_only) fd.append('f_active_only', '1');
        fd.append('settings_branch_id', String(branchId));
        postCoupon(fd, function (err, j) {
            if (err || !j || j.status !== 'ok') {
                couponMsg((j && j.message) ? j.message : 'Failed to load coupons.', true);
                return;
            }
            couponMsg('', false);
            total = typeof j.total === 'number' ? j.total : parseInt(j.total, 10) || 0;
            totalPages = typeof j.total_pages === 'number' ? j.total_pages : 1;
            page = typeof j.page === 'number' ? j.page : page;
            var fa = typeof j.filters_applied === 'number' ? j.filters_applied : 0;
            updateFilterBadge(fa);
            renderRows(j.rows || []);
            syncPagination();
        });
    };

    window.auragoldCouponsOnTabShown = function () {
        window.auragoldCouponsReloadList();
    };

    function resetCouponForm() {
        document.getElementById('coupon_edit_id').value = '0';
        document.getElementById('coupon_name').value = '';
        document.getElementById('coupon_code').value = '';
        document.getElementById('coupon_value').value = '';
        document.getElementById('coupon_expiry').value = '';
        document.getElementById('coupon_active').checked = true;
        document.querySelectorAll('#auragold_coupon_tbody tr.is-selected').forEach(function (tr) { tr.classList.remove('is-selected'); });
    }

    function loadRowIntoForm(id) {
        if (branchId <= 0) return;
        var fd = new FormData();
        fd.append('action', 'get');
        fd.append('id', String(id));
        fd.append('settings_branch_id', String(branchId));
        postCoupon(fd, function (err, j) {
            if (err || !j || j.status !== 'ok' || !j.row) {
                couponMsg((j && j.message) ? j.message : 'Could not load coupon.', true);
                return;
            }
            var rw = j.row;
            document.getElementById('coupon_edit_id').value = String(rw.id || 0);
            document.getElementById('coupon_name').value = rw.coupon_name || '';
            document.getElementById('coupon_code').value = rw.coupon_code || '';
            document.getElementById('coupon_value').value = rw.coupon_value || '';
            document.getElementById('coupon_expiry').value = rw.expiry_date || '';
            document.getElementById('coupon_active').checked = !!rw.is_active;
            document.querySelectorAll('#auragold_coupon_tbody tr').forEach(function (tr) {
                tr.classList.toggle('is-selected', String(tr.getAttribute('data-coupon-id')) === String(rw.id));
            });
            couponMsg('Editing coupon #' + rw.id, false);
        });
    }

    function couponSave() {
        if (branchId <= 0) {
            couponMsg('Invalid branch.', true);
            return;
        }
        var fd = new FormData();
        fd.append('action', 'save');
        fd.append('id', document.getElementById('coupon_edit_id').value || '0');
        fd.append('coupon_name', document.getElementById('coupon_name').value.trim());
        fd.append('coupon_code', document.getElementById('coupon_code').value.trim());
        fd.append('coupon_value', document.getElementById('coupon_value').value.trim());
        fd.append('expiry_date', document.getElementById('coupon_expiry').value.trim());
        if (document.getElementById('coupon_active').checked) fd.append('is_active', '1');
        fd.append('settings_branch_id', String(branchId));
        postCoupon(fd, function (err, j) {
            if (err || !j || j.status !== 'ok') {
                couponMsg((j && j.message) ? j.message : 'Save failed.', true);
                return;
            }
            couponMsg(j.message || 'Saved.', false);
            resetCouponForm();
            window.auragoldCouponsReloadList();
        });
    }

    function couponDelete(id) {
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', String(id));
        fd.append('settings_branch_id', String(branchId));
        postCoupon(fd, function (err, j) {
            couponMsg((j && j.message) ? j.message : 'Deleted.', !(j && j.status === 'ok'));
            resetCouponForm();
            window.auragoldCouponsReloadList();
        });
    }

    function exportCsv() {
        if (branchId <= 0) return;
        var fd = new FormData();
        fd.append('action', 'list');
        fd.append('page', '1');
        fd.append('page_size', '5000');
        fd.append('f_date_from', listFilters.f_date_from);
        fd.append('f_date_to', listFilters.f_date_to);
        fd.append('f_code', listFilters.f_code);
        if (listFilters.f_active_only) fd.append('f_active_only', '1');
        fd.append('settings_branch_id', String(branchId));
        postCoupon(fd, function (err, j) {
            if (err || !j || j.status !== 'ok') {
                couponMsg('Export failed.', true);
                return;
            }
            var rows = j.rows || [];
            var csv = 'code,name,value,expiry,active\r\n';
            rows.forEach(function (r) {
                var line = [
                    '"' + String(r.coupon_code || '').replace(/"/g, '""') + '"',
                    '"' + String(r.coupon_name || '').replace(/"/g, '""') + '"',
                    String(r.coupon_value),
                    '"' + String(r.expiry_date || '').replace(/"/g, '""') + '"',
                    r.is_active ? '1' : '0'
                ].join(',');
                csv += line + '\r\n';
            });
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'reward-coupons.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(function () { URL.revokeObjectURL(a.href); }, 500);
            couponMsg('Exported ' + rows.length + ' rows.', false);
        });
    }

    var ps = qs('#auragold_coupon_page_size');
    if (ps) {
        ps.addEventListener('change', function () {
            pageSize = parseInt(ps.value, 10) || 25;
            page = 1;
            window.auragoldCouponsReloadList();
        });
    }
    [['auragold_coupon_pg_first', 1], ['auragold_coupon_pg_prev', -1], ['auragold_coupon_pg_next', 2], ['auragold_coupon_pg_last', 3]].forEach(function (pair) {
        var bid = pair[0], mode = pair[1];
        var b = qs('#' + bid);
        if (!b) return;
        b.addEventListener('click', function () {
            if (mode === 1) page = 1;
            else if (mode === -1) page = Math.max(1, page - 1);
            else if (mode === 2) page = Math.min(totalPages, page + 1);
            else page = totalPages || 1;
            window.auragoldCouponsReloadList();
        });
    });

    var saveInline = qs('#auragold-coupon-save-inline');
    if (saveInline) saveInline.addEventListener('click', couponSave);

    var newBtn = qs('#auragold-coupon-new');
    if (newBtn) newBtn.addEventListener('click', function () {
        resetCouponForm();
        couponMsg('New coupon — fill and Save.', false);
    });

    var refBtn = qs('#auragold-coupon-refresh');
    if (refBtn) refBtn.addEventListener('click', function () {
        window.auragoldCouponsReloadList();
    });

    var exp = qs('#auragold-coupon-export-csv');
    if (exp) exp.addEventListener('click', function (ev) {
        ev.preventDefault();
        exportCsv();
    });

    function auragoldCouponFilterModalFillAndShow() {
        var df = qs('#modal_f_date_from'); if (df) df.value = listFilters.f_date_from || '';
        var dt = qs('#modal_f_date_to'); if (dt) dt.value = listFilters.f_date_to || '';
        var c = qs('#modal_f_code'); if (c) c.value = listFilters.f_code || '';
        var a = qs('#modal_f_active_only'); if (a) a.checked = !!listFilters.f_active_only;
        var modalEl = document.getElementById('couponAdvFilterModal');
        if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
            jQuery(modalEl).modal('show');
            return;
        }
        /* Minimal fallback if Bootstrap failed to attach */
        if (!modalEl) return;
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
        var bd = document.getElementById('couponAdvFilterBackdrop');
        if (!bd) {
            bd = document.createElement('div');
            bd.id = 'couponAdvFilterBackdrop';
            bd.className = 'modal-backdrop fade show';
            bd.style.cssText = 'z-index:1040';
            document.body.appendChild(bd);
        }
    }

    var openF = qs('#auragold-coupon-open-filter');
    if (openF) openF.addEventListener('click', auragoldCouponFilterModalFillAndShow);

    var applyBtnEl = qs('#auragold_coupon_filter_apply');
    if (applyBtnEl) applyBtnEl.addEventListener('click', function () {
        listFilters.f_date_from = (qs('#modal_f_date_from') && qs('#modal_f_date_from').value) ? qs('#modal_f_date_from').value : '';
        listFilters.f_date_to = (qs('#modal_f_date_to') && qs('#modal_f_date_to').value) ? qs('#modal_f_date_to').value : '';
        listFilters.f_code = qs('#modal_f_code') ? qs('#modal_f_code').value.trim() : '';
        listFilters.f_active_only = qs('#modal_f_active_only') && qs('#modal_f_active_only').checked ? 1 : 0;
        page = 1;
        window.auragoldCouponsReloadList();
        if (typeof jQuery !== 'undefined') jQuery('#couponAdvFilterModal').modal('hide');
    });

    var clrBtnEl = qs('#auragold_coupon_filter_clear');
    if (clrBtnEl) clrBtnEl.addEventListener('click', function () {
        listFilters = { f_date_from: '', f_date_to: '', f_code: '', f_active_only: 0 };
        ['modal_f_date_from', 'modal_f_date_to', 'modal_f_code'].forEach(function (id) { var z = qs('#' + id); if (z) z.value = ''; });
        var ac = qs('#modal_f_active_only'); if (ac) ac.checked = false;
        page = 1;
        window.auragoldCouponsReloadList();
        if (typeof jQuery !== 'undefined') jQuery('#couponAdvFilterModal').modal('hide');
    });

    var expClr = qs('#coupon_expiry_clear');
    if (expClr) expClr.addEventListener('click', function () {
        var e = qs('#coupon_expiry'); if (e) e.value = '';
    });
})();
</script>
</body>
</html>
