<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_gst_reports_catalog.php';

if (empty($_SESSION['Admin']) && empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$catalog = auragold_gst_reports_catalog();
$groups = auragold_gst_report_groups();
$lbl_title = function_exists('auragold_t') ? auragold_t('nav.gst_report') : 'GST Reports';

$AURAGOLD_REPORT_PAGE = true;
include 'header-script.php';
include 'sidebar.php';
?>

<div class="layout-container gst-reports-hub-page">
    <div class="main-content">
        <div class="page-container gst-hub">
            <div class="gst-hub-header">
                <div>
                    <h1 class="gst-hub-title"><?php echo htmlspecialchars($lbl_title, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="gst-hub-lead">GSTR-1, GSTR-3B, GSTR-2B reconciliation and GSTR-9 for GST filing.</p>
                </div>
                <a class="gst-hub-back" href="dashboard.php"><i class="feather icon-arrow-left"></i> Dashboard</a>
            </div>

            <?php foreach ($groups as $gKey => $gLabel): ?>
                <?php
                $items = array_filter($catalog, static function ($r) use ($gKey) {
                    return ($r['group'] ?? '') === $gKey;
                });
                if (!$items) {
                    continue;
                }
                ?>
                <section class="gst-hub-section">
                    <h2 class="gst-hub-section-title"><?php echo htmlspecialchars($gLabel, ENT_QUOTES, 'UTF-8'); ?></h2>
                    <div class="gst-hub-grid">
                        <?php foreach ($items as $item): ?>
                            <a class="gst-hub-card" href="<?php echo htmlspecialchars(auragold_gst_report_href($item['key']), ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="gst-hub-card-icon"><i class="feather <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                                <span class="gst-hub-card-body">
                                    <span class="gst-hub-card-title"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="gst-hub-card-purpose"><?php echo htmlspecialchars($item['purpose'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                                <i class="feather icon-chevron-right gst-hub-card-arrow"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.gst-reports-hub-page .page-container.gst-hub { padding: 20px 24px 40px; max-width: 1180px; }
.gst-hub-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
.gst-hub-title { margin: 0 0 6px; font-size: 1.55rem; font-weight: 700; color: #0f172a; letter-spacing: -0.02em; }
.gst-hub-lead { margin: 0; color: #64748b; font-size: 0.92rem; max-width: 560px; line-height: 1.45; }
.gst-hub-back { display: inline-flex; align-items: center; gap: 6px; color: #475569; font-size: 0.85rem; text-decoration: none; padding: 8px 12px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; }
.gst-hub-back:hover { color: #0f172a; border-color: #cbd5e1; }
.gst-hub-section { margin-bottom: 28px; }
.gst-hub-section-title { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; margin: 0 0 12px; }
.gst-hub-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }
.gst-hub-card { display: flex; align-items: center; gap: 12px; padding: 14px 14px 14px 12px; background: #fff; border: 1px solid #e8eef5; border-radius: 12px; text-decoration: none; color: inherit; transition: border-color .15s, box-shadow .15s, transform .15s; }
.gst-hub-card:hover { border-color: #c7a24a; box-shadow: 0 6px 20px rgba(15, 23, 42, 0.06); transform: translateY(-1px); color: inherit; text-decoration: none; }
.gst-hub-card-icon { width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(145deg, #fff8e8, #f5ebd0); color: #8a6a1f; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.gst-hub-card-icon i { font-size: 1.05rem; }
.gst-hub-card-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.gst-hub-card-title { font-size: 0.92rem; font-weight: 600; color: #0f172a; }
.gst-hub-card-purpose { font-size: 0.75rem; color: #64748b; }
.gst-hub-card-arrow { color: #cbd5e1; flex-shrink: 0; }
.gst-hub-card:hover .gst-hub-card-arrow { color: #c7a24a; }
@media (max-width: 640px) {
    .gst-reports-hub-page .page-container.gst-hub { padding: 16px; }
    .gst-hub-grid { grid-template-columns: 1fr; }
}
</style>

<?php include 'footer-script.php'; ?>
