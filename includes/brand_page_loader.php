<?php
/**
 * Full-screen brand loader — Modern Gold Wave (premium jewellery ERP).
 * Used by dashboard shell, header-script, and sidebar.
 *
 * Skip: set $GLOBALS['DASHBOARD_SKIP_PAGE_LOADER'] = true before layout, or define('AURAGOLD_SKIP_BRAND_PAGE_LOADER', true).
 * User preference: My Profile → Page loader (tbl_users.page_loader on|off).
 */
if (!function_exists('auragold_brand_page_loader_should_show')) {
    function auragold_brand_page_loader_should_show(): bool
    {
        if (defined('AURAGOLD_SKIP_BRAND_PAGE_LOADER') && AURAGOLD_SKIP_BRAND_PAGE_LOADER) {
            return false;
        }
        if (!empty($GLOBALS['DASHBOARD_SKIP_PAGE_LOADER'])) {
            return false;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
            return false;
        }
        if (!function_exists('auragold_user_page_loader_enabled')) {
            $prefFile = __DIR__ . '/auragold_user_page_loader.php';
            if (is_file($prefFile)) {
                require_once $prefFile;
            }
        }
        if (function_exists('auragold_user_page_loader_enabled') && !auragold_user_page_loader_enabled()) {
            return false;
        }
        return true;
    }
}

if (!function_exists('auragold_brand_page_loader_css')) {
    function auragold_brand_page_loader_css(): string
    {
        return <<<'HTML'
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style id="auragold-brand-page-loader-css">
    html.auragold-page-loading,
    html.auragold-page-loading body {
        overflow: hidden !important;
        height: 100% !important;
    }
    #auragold-page-loader {
        --gm-navy: #06172B;
        --gm-navy-deep: #071A2F;
        --gm-gold-dark: #B8860B;
        --gm-gold: #D4AF37;
        --gm-gold-mid: #F5C451;
        --gm-gold-light: #FFE08A;
        --gm-white: #f4f6f9;
        position: fixed !important;
        inset: 0 !important;
        z-index: 2147483000 !important;
        display: flex !important;
        align-items: center;
        justify-content: center;
        padding: 24px 16px;
        box-sizing: border-box;
        background:
            radial-gradient(ellipse 42% 38% at 50% 46%, rgba(212, 175, 55, 0.07) 0%, transparent 62%),
            linear-gradient(180deg, var(--gm-navy) 0%, var(--gm-navy-deep) 100%);
        transition: opacity 0.5s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.5s step-end;
    }
    #auragold-page-loader.auragold-page-loader--done {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }
    #auragold-page-loader .auragold-page-loader__waves {
        position: absolute;
        left: 0;
        right: 0;
        top: 50%;
        transform: translateY(-58%);
        height: clamp(100px, 18vh, 160px);
        pointer-events: none;
        z-index: 0;
        overflow: hidden;
        -webkit-mask-image: linear-gradient(90deg, transparent 0%, #000 6%, #000 94%, transparent 100%);
        mask-image: linear-gradient(90deg, transparent 0%, #000 6%, #000 94%, transparent 100%);
    }
    #auragold-page-loader .auragold-page-loader__wave-svg {
        width: 200%;
        height: 100%;
        display: block;
        will-change: transform;
        animation: auragold-wave-drift 5.2s linear infinite;
    }
    #auragold-page-loader .auragold-page-loader__wave-line {
        fill: none;
        stroke-linecap: round;
        stroke-linejoin: round;
        vector-effect: non-scaling-stroke;
    }
    #auragold-page-loader .auragold-page-loader__wave-line--a {
        stroke: url(#gm-wave-grad-a);
        stroke-width: 1.2;
        opacity: 0.55;
        animation: auragold-wave-shimmer 5s ease-in-out infinite;
    }
    #auragold-page-loader .auragold-page-loader__wave-line--b {
        stroke: url(#gm-wave-grad-b);
        stroke-width: 1;
        opacity: 0.42;
        animation: auragold-wave-shimmer 5s ease-in-out infinite 0.6s;
    }
    #auragold-page-loader .auragold-page-loader__wave-line--c {
        stroke: url(#gm-wave-grad-c);
        stroke-width: 0.9;
        opacity: 0.35;
        animation: auragold-wave-shimmer 5s ease-in-out infinite 1.2s;
    }
    #auragold-page-loader .auragold-page-loader__wave-line--d {
        stroke: url(#gm-wave-grad-d);
        stroke-width: 1.1;
        opacity: 0.48;
        animation: auragold-wave-shimmer 5s ease-in-out infinite 1.8s;
    }
    #auragold-page-loader .auragold-page-loader__wave-line--e {
        stroke: url(#gm-wave-grad-e);
        stroke-width: 0.85;
        opacity: 0.3;
        animation: auragold-wave-shimmer 5s ease-in-out infinite 2.4s;
    }
    #auragold-page-loader .auragold-page-loader__wave-shine {
        fill: none;
        stroke: url(#gm-wave-shine);
        stroke-width: 2;
        stroke-linecap: round;
        opacity: 0.65;
        stroke-dasharray: 120 880;
        animation: auragold-wave-highlight 4.8s ease-in-out infinite;
    }
    @keyframes auragold-wave-drift {
        0% { transform: translateX(0); }
        100% { transform: translateX(-50%); }
    }
    @keyframes auragold-wave-shimmer {
        0%, 100% { opacity: 0.28; }
        50% { opacity: 0.62; }
    }
    @keyframes auragold-wave-highlight {
        0% { stroke-dashoffset: 1000; opacity: 0; }
        12% { opacity: 0.7; }
        50% { stroke-dashoffset: 0; opacity: 0.55; }
        88% { opacity: 0.7; }
        100% { stroke-dashoffset: -1000; opacity: 0; }
    }
    #auragold-page-loader .auragold-page-loader__card {
        position: relative;
        z-index: 1;
        width: min(420px, 94vw);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        animation: auragold-loader-card-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    @keyframes auragold-loader-card-in {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
    #auragold-page-loader .auragold-page-loader__logo-stage {
        position: relative;
        width: 118px;
        height: 118px;
        margin-bottom: 22px;
        flex-shrink: 0;
    }
    #auragold-page-loader .auragold-page-loader__badge-glow {
        position: absolute;
        inset: -10px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(212, 175, 55, 0.28) 0%, rgba(212, 175, 55, 0.08) 45%, transparent 72%);
        pointer-events: none;
        animation: auragold-badge-glow 3.6s ease-in-out infinite;
    }
    @keyframes auragold-badge-glow {
        0%, 100% { opacity: 0.75; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.03); }
    }
    #auragold-page-loader .auragold-page-loader__badge {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background: #fff;
        border: 2px solid transparent;
        background-clip: padding-box;
        box-shadow:
            0 0 0 1px rgba(212, 175, 55, 0.85),
            0 0 24px rgba(212, 175, 55, 0.35),
            0 8px 28px rgba(0, 0, 0, 0.22);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 14px;
        box-sizing: border-box;
    }
    #auragold-page-loader .auragold-page-loader__badge::before {
        content: "";
        position: absolute;
        inset: -2px;
        border-radius: 50%;
        padding: 2px;
        background: linear-gradient(145deg, var(--gm-gold-light), var(--gm-gold), var(--gm-gold-dark));
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        pointer-events: none;
    }
    #auragold-page-loader .auragold-page-loader__logo {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
        position: relative;
        z-index: 1;
    }
    #auragold-page-loader .auragold-page-loader__brand {
        margin: 0 0 10px;
        font-family: "DM Sans", sans-serif;
        font-size: clamp(20px, 3.8vw, 26px);
        font-weight: 600;
        letter-spacing: 2px;
        text-transform: none;
        line-height: 1.25;
        background: linear-gradient(180deg, var(--gm-gold-light) 0%, var(--gm-gold-mid) 38%, var(--gm-gold) 68%, var(--gm-gold-dark) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        color: transparent;
        text-shadow: none;
    }
    #auragold-page-loader .auragold-page-loader__suite {
        margin: 0 0 18px;
        font-family: "DM Sans", sans-serif;
        font-size: clamp(8px, 1.8vw, 10px);
        font-weight: 500;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: var(--gm-gold);
        opacity: 0.92;
        line-height: 1.5;
        max-width: 320px;
    }
    #auragold-page-loader .auragold-page-loader__status {
        margin: 0 0 14px;
        font-family: "DM Sans", sans-serif;
        font-size: clamp(12px, 2.4vw, 14px);
        font-weight: 400;
        color: rgba(244, 246, 249, 0.68);
        line-height: 1.4;
        min-height: 1.4em;
    }
    #auragold-page-loader .auragold-page-loader__dots {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 16px;
    }
    #auragold-page-loader .auragold-page-loader__dots span {
        display: block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: linear-gradient(180deg, var(--gm-gold-light), var(--gm-gold));
        box-shadow: 0 0 6px rgba(212, 175, 55, 0.4);
        opacity: 0.25;
        transform: scale(0.75);
        animation: auragold-loader-dot-pulse 1.4s ease-in-out infinite;
    }
    #auragold-page-loader .auragold-page-loader__dots span:nth-child(2) {
        animation-delay: 0.22s;
    }
    #auragold-page-loader .auragold-page-loader__dots span:nth-child(3) {
        animation-delay: 0.44s;
    }
    @keyframes auragold-loader-dot-pulse {
        0%, 70%, 100% {
            opacity: 0.22;
            transform: scale(0.72);
        }
        35% {
            opacity: 1;
            transform: scale(1);
        }
    }
    @media (max-width: 480px) {
        #auragold-page-loader .auragold-page-loader__logo-stage {
            width: 100px;
            height: 100px;
            margin-bottom: 18px;
        }
        #auragold-page-loader .auragold-page-loader__badge {
            padding: 12px;
        }
        #auragold-page-loader .auragold-page-loader__brand {
            letter-spacing: 1px;
            font-size: clamp(18px, 4.5vw, 22px);
        }
        #auragold-page-loader .auragold-page-loader__suite {
            letter-spacing: 2px;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        #auragold-page-loader .auragold-page-loader__card,
        #auragold-page-loader .auragold-page-loader__wave-svg,
        #auragold-page-loader .auragold-page-loader__wave-line,
        #auragold-page-loader .auragold-page-loader__wave-shine,
        #auragold-page-loader .auragold-page-loader__badge-glow,
        #auragold-page-loader .auragold-page-loader__dots span {
            animation: none !important;
        }
        #auragold-page-loader .auragold-page-loader__wave-line {
            opacity: 0.45;
        }
        #auragold-page-loader .auragold-page-loader__dots span {
            opacity: 0.7;
            transform: scale(1);
        }
    }
</style>
HTML;
    }
}

if (!function_exists('auragold_brand_page_loader_after_body_html')) {
    function auragold_brand_page_loader_after_body_html(): string
    {
        $logo = 'favicon.jpeg';
        if (function_exists('auragold_asset_url')) {
            $logo = auragold_asset_url('favicon.jpeg');
        }
        $logoEsc = htmlspecialchars($logo, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div id="auragold-page-loader" role="status" aria-live="polite" aria-busy="true" aria-label="Loading GoldMatrix Jewellery">
    <div class="auragold-page-loader__waves" aria-hidden="true">
        <svg class="auragold-page-loader__wave-svg" viewBox="0 0 1440 120" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="gm-wave-grad-a" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#B8860B" stop-opacity="0.15"/>
                    <stop offset="35%" stop-color="#D4AF37" stop-opacity="0.65"/>
                    <stop offset="65%" stop-color="#F5C451" stop-opacity="0.75"/>
                    <stop offset="100%" stop-color="#FFE08A" stop-opacity="0.2"/>
                </linearGradient>
                <linearGradient id="gm-wave-grad-b" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#D4AF37" stop-opacity="0.1"/>
                    <stop offset="50%" stop-color="#FFE08A" stop-opacity="0.55"/>
                    <stop offset="100%" stop-color="#B8860B" stop-opacity="0.15"/>
                </linearGradient>
                <linearGradient id="gm-wave-grad-c" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#F5C451" stop-opacity="0.08"/>
                    <stop offset="45%" stop-color="#D4AF37" stop-opacity="0.5"/>
                    <stop offset="100%" stop-color="#FFE08A" stop-opacity="0.12"/>
                </linearGradient>
                <linearGradient id="gm-wave-grad-d" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#B8860B" stop-opacity="0.12"/>
                    <stop offset="55%" stop-color="#F5C451" stop-opacity="0.6"/>
                    <stop offset="100%" stop-color="#D4AF37" stop-opacity="0.18"/>
                </linearGradient>
                <linearGradient id="gm-wave-grad-e" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#FFE08A" stop-opacity="0.06"/>
                    <stop offset="50%" stop-color="#D4AF37" stop-opacity="0.45"/>
                    <stop offset="100%" stop-color="#B8860B" stop-opacity="0.1"/>
                </linearGradient>
                <linearGradient id="gm-wave-shine" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#FFE08A" stop-opacity="0"/>
                    <stop offset="48%" stop-color="#FFE08A" stop-opacity="0"/>
                    <stop offset="50%" stop-color="#FFE08A" stop-opacity="0.95"/>
                    <stop offset="52%" stop-color="#FFE08A" stop-opacity="0"/>
                    <stop offset="100%" stop-color="#FFE08A" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <path class="auragold-page-loader__wave-line auragold-page-loader__wave-line--a" d="M-40,62 C120,28 240,94 360,62 S600,28 720,62 S960,94 1080,62 S1320,28 1480,62"/>
            <path class="auragold-page-loader__wave-line auragold-page-loader__wave-line--b" d="M-40,48 C140,78 260,18 380,48 S620,78 740,48 S980,18 1100,48 S1340,78 1480,48"/>
            <path class="auragold-page-loader__wave-line auragold-page-loader__wave-line--c" d="M-40,74 C100,98 220,50 340,74 S580,98 700,74 S940,50 1060,74 S1300,98 1480,74"/>
            <path class="auragold-page-loader__wave-line auragold-page-loader__wave-line--d" d="M-40,56 C130,38 250,72 370,56 S610,38 730,56 S970,72 1090,56 S1330,38 1480,56"/>
            <path class="auragold-page-loader__wave-line auragold-page-loader__wave-line--e" d="M-40,68 C110,88 230,48 350,68 S590,88 710,68 S950,48 1070,68 S1310,88 1480,68"/>
            <path class="auragold-page-loader__wave-shine" d="M-40,62 C120,28 240,94 360,62 S600,28 720,62 S960,94 1080,62 S1320,28 1480,62"/>
        </svg>
    </div>
    <div class="auragold-page-loader__card">
        <div class="auragold-page-loader__logo-stage">
            <div class="auragold-page-loader__badge-glow" aria-hidden="true"></div>
            <div class="auragold-page-loader__badge">
                <img class="auragold-page-loader__logo" src="{$logoEsc}" width="72" height="72" alt="GoldMatrix Jewellery">
            </div>
        </div>
        <h1 class="auragold-page-loader__brand">GoldMatrix Jewellery</h1>
        <p class="auragold-page-loader__suite">Advance Software for Smart Jewellery</p>
        <p class="auragold-page-loader__status" id="auragold-page-loader-status">Connecting to your workspace</p>
        <div class="auragold-page-loader__dots" aria-hidden="true"><span></span><span></span><span></span></div>
    </div>
</div>
<script>
(function () {
    document.documentElement.classList.add('auragold-page-loading');
    if (document.body) {
        document.body.classList.add('auragold-page-loading');
    }
})();
</script>
HTML;
    }
}

if (!function_exists('auragold_brand_page_loader_js')) {
    function auragold_brand_page_loader_js(): string
    {
        return <<<'HTML'
<script>
(function () {
    function boot() {
        var minMs = 2800;
        var t0 = typeof performance !== 'undefined' && performance.now ? performance.now() : Date.now();
        var done = false;
        var root = document.getElementById('auragold-page-loader');
        if (!root) {
            return;
        }

        document.documentElement.classList.add('auragold-page-loading');
        document.body.classList.add('auragold-page-loading');

        function finish() {
            if (done) {
                return;
            }
            done = true;
            document.documentElement.classList.remove('auragold-page-loading');
            document.body.classList.remove('auragold-page-loading');
            root.setAttribute('aria-busy', 'false');
            root.classList.add('auragold-page-loader--done');
            setTimeout(function () {
                if (root && root.parentNode) {
                    root.parentNode.removeChild(root);
                }
            }, 520);
        }

        function scheduleFinish() {
            var now = typeof performance !== 'undefined' && performance.now ? performance.now() : Date.now();
            var wait = minMs - (now - t0);
            if (wait > 0) {
                setTimeout(finish, wait);
            } else {
                finish();
            }
        }

        if (document.readyState === 'complete') {
            scheduleFinish();
        } else {
            window.addEventListener('load', scheduleFinish);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
HTML;
    }
}
