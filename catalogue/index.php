<?php
/**
 * Standalone Jewellery Catalogue — beautiful public gallery view.
 * Uses catalogue/config.php only (not project config.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$conn = catalogue_db();
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$metalId = isset($_GET['metal']) ? (int) $_GET['metal'] : 0;
$categoryId = isset($_GET['category']) ? (int) $_GET['category'] : 0;

$data = catalogue_fetch_items($conn, [
    'q' => $q,
    'metal_id' => $metalId,
    'category_id' => $categoryId,
]);

$items = $data['items'];
$metals = $data['metals'];
$categories = $data['categories'];
$error = $data['error'];
$title = (string) catalogue_cfg('catalogue_title', 'Jewellery Catalogue');
$app = (string) catalogue_cfg('app_name', 'GoldMatrix');
$tagline = (string) catalogue_cfg('catalogue_tagline', '');
$count = count($items);

$placeholder = "data:image/svg+xml," . rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="750" viewBox="0 0 600 750">'
    . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
    . '<stop offset="0%" stop-color="#1a2f4a"/><stop offset="100%" stop-color="#0c1a2e"/></linearGradient></defs>'
    . '<rect width="600" height="750" fill="url(#g)"/>'
    . '<circle cx="300" cy="320" r="78" fill="none" stroke="#c9a24a" stroke-width="2" opacity=".55"/>'
    . '<path d="M260 320h80M300 280v80" stroke="#c9a24a" stroke-width="2" opacity=".4"/>'
    . '<text x="300" y="460" text-anchor="middle" fill="#e8d48a" font-family="Georgia,serif" font-size="22" opacity=".85">GoldMatrix</text>'
    . '</svg>'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo catalogue_h($title); ?> · <?php echo catalogue_h($app); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0b1728;
            --ink-soft: #1c2d45;
            --gold: #c9a24a;
            --gold-bright: #e4c06a;
            --gold-dim: #8f7130;
            --cream: #f3efe6;
            --paper: #f7f4ee;
            --line: rgba(201, 162, 74, 0.28);
            --shadow: 0 18px 50px rgba(8, 16, 30, 0.28);
            --radius: 18px;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            min-height: 100vh;
            color: var(--cream);
            font-family: "Outfit", sans-serif;
            background:
                radial-gradient(1200px 600px at 10% -10%, rgba(201, 162, 74, 0.18), transparent 55%),
                radial-gradient(900px 500px at 100% 0%, rgba(56, 110, 180, 0.16), transparent 50%),
                linear-gradient(165deg, #07111f 0%, #0f1f35 42%, #132844 100%);
            background-attachment: fixed;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.07;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c9a24a' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            z-index: 0;
        }
        .wrap {
            position: relative;
            z-index: 1;
            width: min(1240px, calc(100% - 2rem));
            margin: 0 auto;
            padding: 1.25rem 0 3.5rem;
        }

        /* Hero — brand first */
        .hero {
            position: relative;
            padding: clamp(2.5rem, 7vw, 4.75rem) 0 clamp(1.75rem, 4vw, 2.75rem);
            text-align: center;
            animation: fadeRise 0.9s ease both;
        }
        .brand {
            font-family: "Cormorant Garamond", Georgia, serif;
            font-size: clamp(2.8rem, 8vw, 5.2rem);
            font-weight: 600;
            letter-spacing: 0.04em;
            line-height: 0.95;
            margin: 0;
            background: linear-gradient(120deg, #f7e7b0 10%, var(--gold-bright) 40%, #fff8e7 55%, var(--gold) 85%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 0 0 40px rgba(201, 162, 74, 0.15);
        }
        .brand-sub {
            margin: 0.85rem auto 0;
            max-width: 34rem;
            font-weight: 300;
            font-size: 1.05rem;
            color: rgba(243, 239, 230, 0.78);
            letter-spacing: 0.02em;
        }
        .hero-rule {
            width: 72px;
            height: 2px;
            margin: 1.35rem auto 0;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            animation: expandRule 1s 0.25s ease both;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1rem;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.15rem;
            margin: 0.5rem 0 1.5rem;
            border: 1px solid var(--line);
            border-radius: calc(var(--radius) + 4px);
            background: rgba(11, 23, 40, 0.55);
            backdrop-filter: blur(12px);
            animation: fadeRise 0.8s 0.15s ease both;
        }
        .search {
            flex: 1 1 220px;
            position: relative;
            max-width: 420px;
        }
        .search input {
            width: 100%;
            border: 1px solid rgba(201, 162, 74, 0.35);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.04);
            color: var(--cream);
            padding: 0.7rem 1.1rem 0.7rem 2.6rem;
            font: 400 0.95rem "Outfit", sans-serif;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .search input::placeholder { color: rgba(243, 239, 230, 0.45); }
        .search input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 162, 74, 0.18);
        }
        .search svg {
            position: absolute;
            left: 0.95rem;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            opacity: 0.65;
            fill: none;
            stroke: var(--gold-bright);
            stroke-width: 1.8;
        }
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            align-items: center;
        }
        .chip {
            appearance: none;
            border: 1px solid rgba(201, 162, 74, 0.28);
            background: transparent;
            color: rgba(243, 239, 230, 0.85);
            border-radius: 999px;
            padding: 0.42rem 0.9rem;
            font: 500 0.8rem "Outfit", sans-serif;
            letter-spacing: 0.03em;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s, border-color 0.2s, color 0.2s, transform 0.15s;
        }
        .chip:hover { border-color: var(--gold); color: #fff; transform: translateY(-1px); }
        .chip.is-active {
            background: linear-gradient(135deg, var(--gold-dim), var(--gold));
            border-color: transparent;
            color: #0b1728;
        }
        .count-pill {
            margin-left: auto;
            font-size: 0.8rem;
            color: rgba(243, 239, 230, 0.6);
            white-space: nowrap;
        }
        .count-pill strong { color: var(--gold-bright); font-weight: 600; }

        /* Grid */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.25rem;
        }
        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            border-radius: var(--radius);
            overflow: hidden;
            background: linear-gradient(180deg, rgba(28, 45, 69, 0.92), rgba(11, 23, 40, 0.96));
            border: 1px solid rgba(201, 162, 74, 0.18);
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: transform 0.35s cubic-bezier(.2,.8,.2,1), border-color 0.25s, box-shadow 0.35s;
            animation: fadeRise 0.7s ease both;
        }
        .card:nth-child(2) { animation-delay: 0.04s; }
        .card:nth-child(3) { animation-delay: 0.08s; }
        .card:nth-child(4) { animation-delay: 0.12s; }
        .card:hover {
            transform: translateY(-6px);
            border-color: rgba(201, 162, 74, 0.55);
            box-shadow: 0 26px 60px rgba(8, 16, 30, 0.45);
        }
        .card-media {
            position: relative;
            aspect-ratio: 4 / 5;
            overflow: hidden;
            background: #0a1422;
        }
        .card-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.6s cubic-bezier(.2,.8,.2,1);
        }
        .card:hover .card-media img { transform: scale(1.06); }
        .card-media::after {
            content: "";
            position: absolute;
            inset: auto 0 0;
            height: 42%;
            background: linear-gradient(transparent, rgba(8, 14, 24, 0.88));
            pointer-events: none;
        }
        .badge {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            z-index: 2;
            padding: 0.28rem 0.65rem;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            background: rgba(11, 23, 40, 0.72);
            border: 1px solid rgba(201, 162, 74, 0.45);
            color: var(--gold-bright);
            backdrop-filter: blur(6px);
        }
        .card-body {
            padding: 1rem 1.05rem 1.15rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            flex: 1;
        }
        .card-title {
            margin: 0;
            font-family: "Cormorant Garamond", Georgia, serif;
            font-size: 1.35rem;
            font-weight: 600;
            line-height: 1.15;
            color: #fff8ea;
        }
        .card-meta {
            margin: 0;
            font-size: 0.82rem;
            color: rgba(243, 239, 230, 0.62);
        }
        .card-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.55rem;
        }
        .stat {
            font-size: 0.72rem;
            letter-spacing: 0.04em;
            padding: 0.28rem 0.55rem;
            border-radius: 8px;
            background: rgba(201, 162, 74, 0.1);
            border: 1px solid rgba(201, 162, 74, 0.2);
            color: rgba(243, 239, 230, 0.88);
        }
        .stat em {
            font-style: normal;
            color: var(--gold-bright);
            font-weight: 600;
        }

        .empty, .error-box {
            text-align: center;
            padding: 3.5rem 1.5rem;
            border: 1px dashed rgba(201, 162, 74, 0.35);
            border-radius: var(--radius);
            background: rgba(11, 23, 40, 0.4);
        }
        .empty h2, .error-box h2 {
            font-family: "Cormorant Garamond", Georgia, serif;
            font-weight: 600;
            margin: 0 0 0.5rem;
            font-size: 1.8rem;
            color: var(--gold-bright);
        }
        .empty p, .error-box p {
            margin: 0;
            color: rgba(243, 239, 230, 0.65);
        }

        /* Modal */
        .modal {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(4, 10, 18, 0.72);
            backdrop-filter: blur(8px);
        }
        .modal.is-open { display: flex; animation: fadeIn 0.25s ease; }
        .modal-card {
            width: min(920px, 100%);
            max-height: min(90vh, 860px);
            overflow: auto;
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            gap: 0;
            background: linear-gradient(160deg, #15263d, #0b1728);
            border: 1px solid rgba(201, 162, 74, 0.35);
            border-radius: 22px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.45);
            animation: scaleIn 0.3s ease;
        }
        @media (max-width: 760px) {
            .modal-card { grid-template-columns: 1fr; }
        }
        .modal-media {
            min-height: 280px;
            background: #08101c;
        }
        .modal-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            min-height: 320px;
        }
        .modal-body { padding: 1.5rem 1.6rem 1.7rem; }
        .modal-body h2 {
            margin: 0 0 0.4rem;
            font-family: "Cormorant Garamond", Georgia, serif;
            font-size: clamp(1.7rem, 4vw, 2.3rem);
            font-weight: 600;
            color: #fff8ea;
        }
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid rgba(201, 162, 74, 0.4);
            background: rgba(11, 23, 40, 0.85);
            color: var(--gold-bright);
            font-size: 1.35rem;
            cursor: pointer;
            line-height: 1;
        }
        .modal { position: fixed; }
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.65rem;
            margin-top: 1.15rem;
        }
        .detail {
            padding: 0.7rem 0.8rem;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(201, 162, 74, 0.15);
        }
        .detail span {
            display: block;
            font-size: 0.68rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(243, 239, 230, 0.45);
            margin-bottom: 0.2rem;
        }
        .detail strong {
            font-weight: 500;
            color: var(--cream);
            font-size: 0.95rem;
        }
        .foot {
            margin-top: 2.5rem;
            text-align: center;
            font-size: 0.8rem;
            color: rgba(243, 239, 230, 0.4);
        }
        .foot a { color: var(--gold); text-decoration: none; }

        @keyframes fadeRise {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: none; }
        }
        @keyframes expandRule {
            from { width: 0; opacity: 0; }
            to { width: 72px; opacity: 1; }
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scaleIn {
            from { opacity: 0; transform: translateY(12px) scale(0.98); }
            to { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="hero">
            <p class="brand"><?php echo catalogue_h($app); ?></p>
            <p class="brand-sub"><?php echo catalogue_h($tagline !== '' ? $tagline : $title); ?></p>
            <div class="hero-rule" aria-hidden="true"></div>
        </header>

        <form class="toolbar" method="get" action="">
            <div class="search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <input type="search" name="q" value="<?php echo catalogue_h($q); ?>" placeholder="Search name, article, barcode, metal…">
            </div>
            <div class="filters">
                <a class="chip<?php echo $metalId === 0 ? ' is-active' : ''; ?>" href="?<?php echo catalogue_h(http_build_query(array_filter(['q' => $q, 'category' => $categoryId ?: null]))); ?>">All metals</a>
                <?php foreach ($metals as $mid => $mname): ?>
                    <a class="chip<?php echo $metalId === (int) $mid ? ' is-active' : ''; ?>"
                       href="?<?php echo catalogue_h(http_build_query(array_filter(['q' => $q ?: null, 'metal' => $mid, 'category' => $categoryId ?: null]))); ?>">
                        <?php echo catalogue_h($mname); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if ($categories): ?>
            <div class="filters">
                <select name="category" onchange="this.form.submit()" style="border-radius:999px;border:1px solid rgba(201,162,74,.35);background:rgba(255,255,255,.04);color:var(--cream);padding:.5rem .9rem;font:500 .8rem Outfit,sans-serif;">
                    <option value="0">All categories</option>
                    <?php foreach ($categories as $cid => $cname): ?>
                        <option value="<?php echo (int) $cid; ?>"<?php echo $categoryId === (int) $cid ? ' selected' : ''; ?>><?php echo catalogue_h($cname); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($metalId): ?><input type="hidden" name="metal" value="<?php echo (int) $metalId; ?>"><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="count-pill"><strong><?php echo (int) $count; ?></strong> piece<?php echo $count === 1 ? '' : 's'; ?></div>
        </form>

        <?php if ($error !== ''): ?>
            <div class="error-box">
                <h2>Could not load catalogue</h2>
                <p><?php echo catalogue_h($error); ?></p>
                <p style="margin-top:.75rem">Check <code>catalogue/config.php</code> database settings.</p>
            </div>
        <?php elseif ($count === 0): ?>
            <div class="empty">
                <h2>No pieces found</h2>
                <p>Try another metal, category, or search term.</p>
            </div>
        <?php else: ?>
            <div class="grid" id="catalogueGrid">
                <?php foreach ($items as $i => $item):
                    $img = $item['thumb'] !== '' ? $item['thumb'] : $placeholder;
                    $payload = catalogue_h(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP));
                    ?>
                    <article class="card" tabindex="0" role="button" data-item="<?php echo $payload; ?>" style="animation-delay: <?php echo min(0.35, $i * 0.03); ?>s">
                        <div class="card-media">
                            <?php if ($item['metal'] !== ''): ?>
                                <span class="badge"><?php echo catalogue_h($item['metal']); ?></span>
                            <?php endif; ?>
                            <img src="<?php echo catalogue_h($img); ?>" alt="<?php echo catalogue_h($item['name']); ?>" loading="lazy"
                                 onerror="this.onerror=null;this.src='<?php echo catalogue_h($placeholder); ?>'">
                        </div>
                        <div class="card-body">
                            <h3 class="card-title"><?php echo catalogue_h($item['name']); ?></h3>
                            <p class="card-meta">
                                <?php
                                $bits = array_filter([$item['article'], $item['category'], $item['carat'] !== '' ? ($item['carat'] . 'K') : '']);
                                echo catalogue_h($bits ? implode(' · ', $bits) : ($item['barcode'] ?: 'Jewellery'));
                                ?>
                            </p>
                            <div class="card-stats">
                                <?php if ($item['weight'] !== '—'): ?>
                                    <span class="stat">Wt <em><?php echo catalogue_h($item['weight']); ?></em> g</span>
                                <?php endif; ?>
                                <?php if ($item['qty'] !== '—' && $item['qty'] !== '0'): ?>
                                    <span class="stat">Qty <em><?php echo catalogue_h($item['qty']); ?></em></span>
                                <?php endif; ?>
                                <?php if ($item['barcode'] !== ''): ?>
                                    <span class="stat"><?php echo catalogue_h($item['barcode']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="foot"><?php echo catalogue_h($app); ?> Catalogue · configure DB in <code>catalogue/config.php</code></p>
    </div>

    <div class="modal" id="itemModal" aria-hidden="true">
        <button type="button" class="modal-close" id="modalClose" aria-label="Close">&times;</button>
        <div class="modal-card" role="dialog" aria-modal="true">
            <div class="modal-media"><img id="modalImg" src="" alt=""></div>
            <div class="modal-body">
                <h2 id="modalTitle"></h2>
                <p class="card-meta" id="modalSub"></p>
                <div class="detail-grid">
                    <div class="detail"><span>Metal</span><strong id="modalMetal">—</strong></div>
                    <div class="detail"><span>Category</span><strong id="modalCategory">—</strong></div>
                    <div class="detail"><span>Carat</span><strong id="modalCarat">—</strong></div>
                    <div class="detail"><span>Weight (g)</span><strong id="modalWeight">—</strong></div>
                    <div class="detail"><span>Quantity</span><strong id="modalQty">—</strong></div>
                    <div class="detail"><span>Barcode</span><strong id="modalBarcode">—</strong></div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var modal = document.getElementById('itemModal');
        var placeholder = <?php echo json_encode($placeholder); ?>;
        function openItem(data) {
            if (!data) return;
            document.getElementById('modalTitle').textContent = data.name || 'Jewellery';
            var sub = [data.article, data.category].filter(Boolean).join(' · ');
            document.getElementById('modalSub').textContent = sub || data.metal || '';
            document.getElementById('modalMetal').textContent = data.metal || '—';
            document.getElementById('modalCategory').textContent = data.category || '—';
            document.getElementById('modalCarat').textContent = data.carat ? (data.carat + (String(data.carat).indexOf('K') >= 0 ? '' : 'K')) : '—';
            document.getElementById('modalWeight').textContent = data.weight || '—';
            document.getElementById('modalQty').textContent = data.qty || '—';
            document.getElementById('modalBarcode').textContent = data.barcode || '—';
            var img = document.getElementById('modalImg');
            img.src = (data.thumb || (data.gallery && data.gallery[0]) || placeholder);
            img.onerror = function () { this.onerror = null; this.src = placeholder; };
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        }
        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }
        document.querySelectorAll('.card[data-item]').forEach(function (card) {
            function go() {
                try { openItem(JSON.parse(card.getAttribute('data-item') || '{}')); } catch (e) {}
            }
            card.addEventListener('click', go);
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); }
            });
        });
        document.getElementById('modalClose').addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    })();
    </script>
</body>
</html>
