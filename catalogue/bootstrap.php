<?php
/**
 * Catalogue bootstrap — mysqli connection + helpers (standalone).
 */
declare(strict_types=1);

$CATALOGUE_CONFIG = require __DIR__ . '/config.php';
if (!is_array($CATALOGUE_CONFIG)) {
    $CATALOGUE_CONFIG = [];
}

function catalogue_cfg(string $key, $default = null)
{
    global $CATALOGUE_CONFIG;
    return array_key_exists($key, $CATALOGUE_CONFIG) ? $CATALOGUE_CONFIG[$key] : $default;
}

function catalogue_db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    $host = (string) catalogue_cfg('db_host', 'localhost');
    $user = (string) catalogue_cfg('db_user', 'root');
    $pass = (string) catalogue_cfg('db_pass', '');
    $name = (string) catalogue_cfg('db_name', '');
    $charset = (string) catalogue_cfg('db_charset', 'utf8mb4');

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @mysqli_connect($host, $user, $pass, $name);
    if (!$conn) {
        http_response_code(500);
        $err = htmlspecialchars(mysqli_connect_error() ?: 'Unknown error', ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html><body style="font-family:system-ui;padding:2rem;background:#0f172a;color:#f8fafc">';
        echo '<h1>Catalogue database connection failed</h1>';
        echo '<p>Edit <code>catalogue/config.php</code> with the correct MySQL credentials.</p>';
        echo '<p style="opacity:.75">' . $err . '</p></body></html>';
        exit;
    }
    mysqli_set_charset($conn, $charset);
    return $conn;
}

function catalogue_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function catalogue_table_exists(mysqli $conn, string $table): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
    if ($table === '') {
        return false;
    }
    $res = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
    return $res && mysqli_num_rows($res) > 0;
}

function catalogue_column_exists(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?? '';
    if ($table === '' || $column === '') {
        return $cache[$key] = false;
    }
    $res = @mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE '" . mysqli_real_escape_string($conn, $column) . "'");
    return $cache[$key] = ($res && mysqli_num_rows($res) > 0);
}

/**
 * Build a browser URL for a stored upload path.
 */
function catalogue_image_url(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $base = rtrim((string) catalogue_cfg('site_url', ''), '/');
    $rel = ltrim(str_replace('\\', '/', $path), '/');
    if (strpos($rel, 'uploads/') !== 0 && strpos($rel, 'upload/') !== 0) {
        /* keep as-is if already a web path fragment */
    }
    if ($base === '') {
        return '../' . $rel;
    }
    return $base . '/' . $rel;
}

function catalogue_fmt_num($n, int $decimals = 3): string
{
    if ($n === null || $n === '') {
        return '—';
    }
    $f = (float) $n;
    if (abs($f) < 0.0000001) {
        return '—';
    }
    return rtrim(rtrim(number_format($f, $decimals, '.', ''), '0'), '.') ?: '0';
}

/**
 * Fetch catalogue items (available stock with product meta + images).
 *
 * @return array{items: array<int,array>, metals: array<int,string>, categories: array<int,string>, error: string}
 */
function catalogue_fetch_items(mysqli $conn, array $filters = []): array
{
    $out = ['items' => [], 'metals' => [], 'categories' => [], 'error' => ''];
    if (!catalogue_table_exists($conn, 'tbl_stock') || !catalogue_table_exists($conn, 'tbl_products')) {
        $out['error'] = 'Required tables (tbl_stock / tbl_products) were not found in this database.';
        return $out;
    }

    $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
    $metalId = (int) ($filters['metal_id'] ?? 0);
    $categoryId = (int) ($filters['category_id'] ?? 0);
    $limit = (int) catalogue_cfg('max_items', 500);
    if ($limit < 1) {
        $limit = 100;
    }
    if ($limit > 2000) {
        $limit = 2000;
    }

    $hasMetal = catalogue_table_exists($conn, 'tbl_metal');
    $hasCat = catalogue_table_exists($conn, 'tbl_categories');
    $hasPc = catalogue_table_exists($conn, 'tbl_product_characteristics');
    $hasImg = catalogue_table_exists($conn, 'tbl_stock_journal_images');

    $metalExpr = "'Metal'";
    if ($hasMetal) {
        $metalExpr = "COALESCE(NULLIF(TRIM(m.display_name), ''), NULLIF(TRIM(m.system_name), ''), CONCAT('Metal ', m.id))";
    }
    $catExpr = "''";
    if ($hasCat) {
        $catExpr = "COALESCE(c.name, '')";
    }
    $caratExpr = "''";
    $purityExpr = "''";
    $pcJoin = '';
    if ($hasPc) {
        $pcJoin = 'LEFT JOIN tbl_product_characteristics pc ON pc.id = s.product_characteristic_id';
        if (catalogue_column_exists($conn, 'tbl_product_characteristics', 'carat')) {
            $caratExpr = "COALESCE(NULLIF(TRIM(pc.carat), ''), '')";
        }
        if (catalogue_column_exists($conn, 'tbl_product_characteristics', 'purity_sale')) {
            $purityExpr = "COALESCE(pc.purity_sale, '')";
        }
    }
    $imgExpr = "''";
    $imgJoin = '';
    if ($hasImg) {
        $imgJoin = "LEFT JOIN (
            SELECT barcode_no, GROUP_CONCAT(image_path ORDER BY id SEPARATOR ',') AS image_urls
            FROM tbl_stock_journal_images
            GROUP BY barcode_no
        ) imgs ON imgs.barcode_no = s.barcode";
        $imgExpr = "COALESCE(imgs.image_urls, '')";
    }

    $where = ['s.status = 1'];
    if (catalogue_column_exists($conn, 'tbl_stock', 'current_qty')
        && catalogue_column_exists($conn, 'tbl_stock', 'current_weight')) {
        $where[] = '(IFNULL(s.current_qty, 0) > 0 OR IFNULL(s.current_weight, 0) > 0.0001)';
    }
    if ($metalId > 0) {
        $where[] = 's.metal_id = ' . $metalId;
    }
    if ($categoryId > 0 && $hasCat) {
        $where[] = 'p.category_id = ' . $categoryId;
    }
    if ($q !== '') {
        $like = '%' . mysqli_real_escape_string($conn, $q) . '%';
        $where[] = "(LOWER(IFNULL(p.name,'')) LIKE '{$like}'
            OR LOWER(IFNULL(p.article,'')) LIKE '{$like}'
            OR LOWER(IFNULL(s.barcode,'')) LIKE '{$like}'
            OR LOWER({$metalExpr}) LIKE '{$like}'
            OR LOWER({$catExpr}) LIKE '{$like}')";
    }
    $whereSql = implode(' AND ', $where);

    $sql = "
        SELECT
            s.id AS stock_id,
            s.barcode,
            s.metal_id,
            s.product_id,
            s.current_qty,
            s.current_weight,
            p.name AS product_name,
            p.article,
            p.category_id,
            {$metalExpr} AS metal_name,
            {$catExpr} AS category_name,
            {$caratExpr} AS carat,
            {$purityExpr} AS purity,
            {$imgExpr} AS image_urls
        FROM tbl_stock s
        LEFT JOIN tbl_products p ON p.id = s.product_id
        " . ($hasMetal ? 'LEFT JOIN tbl_metal m ON m.id = s.metal_id' : '') . '
        ' . ($hasCat ? 'LEFT JOIN tbl_categories c ON c.id = p.category_id' : '') . "
        {$pcJoin}
        {$imgJoin}
        WHERE {$whereSql}
        ORDER BY s.id DESC
        LIMIT {$limit}
    ";

    $res = @mysqli_query($conn, $sql);
    if (!$res) {
        $out['error'] = 'Query failed: ' . mysqli_error($conn);
        return $out;
    }

    $metals = [];
    $categories = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $thumb = '';
        $gallery = [];
        $raw = trim((string) ($row['image_urls'] ?? ''));
        if ($raw !== '') {
            foreach (explode(',', $raw) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $url = catalogue_image_url($part);
                if ($url === '') {
                    continue;
                }
                $gallery[] = $url;
                if ($thumb === '') {
                    $thumb = $url;
                }
            }
        }
        $metalName = trim((string) ($row['metal_name'] ?? 'Jewellery'));
        $catName = trim((string) ($row['category_name'] ?? ''));
        $mid = (int) ($row['metal_id'] ?? 0);
        $cid = (int) ($row['category_id'] ?? 0);
        if ($mid > 0 && $metalName !== '') {
            $metals[$mid] = $metalName;
        }
        if ($cid > 0 && $catName !== '') {
            $categories[$cid] = $catName;
        }

        $name = trim((string) ($row['product_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['article'] ?? ''));
        }
        if ($name === '') {
            $name = 'Jewellery Piece';
        }

        $out['items'][] = [
            'stock_id' => (int) ($row['stock_id'] ?? 0),
            'barcode' => trim((string) ($row['barcode'] ?? '')),
            'name' => $name,
            'article' => trim((string) ($row['article'] ?? '')),
            'metal_id' => $mid,
            'metal' => $metalName,
            'category_id' => $cid,
            'category' => $catName,
            'carat' => trim((string) ($row['carat'] ?? '')),
            'purity' => catalogue_fmt_num($row['purity'] ?? '', 2),
            'qty' => catalogue_fmt_num($row['current_qty'] ?? 0, 0),
            'weight' => catalogue_fmt_num($row['current_weight'] ?? 0, 3),
            'thumb' => $thumb,
            'gallery' => $gallery,
        ];
    }

    asort($metals, SORT_NATURAL | SORT_FLAG_CASE);
    asort($categories, SORT_NATURAL | SORT_FLAG_CASE);
    $out['metals'] = $metals;
    $out['categories'] = $categories;
    return $out;
}
