<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$barcode = isset($_POST['barcode_no']) ? trim((string) $_POST['barcode_no']) : '';
$itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
if ($barcode === '' && $itemId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Barcode or stock journal id is required']);
    exit;
}

if (!($conn instanceof mysqli)) {
    echo json_encode(['status' => 'error', 'message' => 'Database unavailable']);
    exit;
}

$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_journal_images'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    if ($chk) {
        mysqli_free_result($chk);
    }
    echo json_encode(['status' => 'error', 'message' => 'Images table not found']);
    exit;
}
mysqli_free_result($chk);

$base = isset($SiteUrl) ? rtrim((string) $SiteUrl, '/') : '';
$appRoot = '';
if ($base === '') {
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($sn !== '' && $sn !== '/') {
        $dir = rtrim(dirname($sn), '/');
        if (preg_match('#^(.*)/admin(?:/|$)#u', $dir . '/', $m)) {
            $appRoot = rtrim($m[1], '/') ?: '';
        }
    }
}

$images = [];

$rowToEntry = function (array $row) use ($base, $appRoot): ?array {
    $path = (string) ($row['image_path'] ?? '');
    $rel = ltrim(trim($path), '/');
    if ($rel === '') {
        return null;
    }
    $under = auragold_uploads_public_rel($rel);
    if (preg_match('#^https?://#i', $path)) {
        $url = $path;
    } elseif (strpos($path, '/') === 0) {
        if (preg_match('#^/uploads/#', $path)) {
            $trail = ltrim($path, '/');
            $underAbs = auragold_uploads_public_rel($trail);
            if ($base !== '') {
                $url = $base . '/' . $underAbs;
            } elseif ($appRoot !== '') {
                $url = rtrim($appRoot, '/') . '/' . $underAbs;
            } else {
                $url = '/' . $underAbs;
            }
        } else {
            $url = $path;
        }
    } elseif ($base !== '') {
        $url = $base . '/' . $under;
    } elseif ($appRoot !== '' && $appRoot !== '/' && $appRoot !== '.') {
        $url = rtrim($appRoot, '/') . '/' . $under;
    } else {
        $url = '/' . $under;
    }
    return [
        'id' => (int) ($row['id'] ?? 0),
        'item_id' => (int) ($row['item_id'] ?? 0),
        'path' => $path,
        'url' => $url,
    ];
};

$runQuery = function (string $sql) use ($conn, &$images, $rowToEntry): void {
    $rs = @mysqli_query($conn, $sql);
    if (!$rs) {
        return;
    }
    while ($row = mysqli_fetch_assoc($rs)) {
        $entry = $rowToEntry($row);
        if ($entry !== null) {
            $images[] = $entry;
        }
    }
    mysqli_free_result($rs);
};

if ($barcode !== '') {
    $esc = mysqli_real_escape_string($conn, $barcode);
    $runQuery(
        "SELECT id, item_id, image_path FROM tbl_stock_journal_images WHERE TRIM(barcode_no) = TRIM('$esc') ORDER BY id ASC"
    );
}

if ($images === [] && $itemId > 0) {
    $jid = (int) $itemId;
    $runQuery(
        "SELECT id, item_id, image_path FROM tbl_stock_journal_images WHERE item_id = $jid ORDER BY id ASC"
    );
}

echo json_encode(['status' => 'success', 'images' => $images]);
