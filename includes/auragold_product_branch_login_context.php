<?php
/**
 * Branch type + DB routing for product master writes (main vs sub in tbl_branches).
 * Product master and branch-scoped rows (characteristics, tax, etc.) for sub-users must live
 * in the parent main’s operational MySQL database; sub-branch is represented by branch_id + tbl_product_branches.
 *
 * Registry (example rows in tbl_branches):
 *   Main: id=1, name='Head Office', main_branch_id=0,  db_name='auragold_hq',   status=1
 *   Sub:  id=2, name='Pune',        main_branch_id=1, db_name='auragold_pune'  OR same db_name as main (shared)
 *
 * After save, the product is listed for the sub via tbl_product_branches (in the main DB) with branch_id = sub’s id;
 * no second copy of tbl_products is written in other physical databases.
 */
if (!function_exists('auragold_branch_row_db_credentials')) {
    require_once __DIR__ . '/branch_credentials.php';
}
if (!function_exists('auragold_resolve_branch_operational_credentials')) {
    require_once __DIR__ . '/subdomain_branch.php';
}

/**
 * @return int Registry branch id from session (0 if none)
 */
function auragold_get_logged_in_branch_id(): int {
    if (!empty($_SESSION['working_branch_id'])) {
        return (int) $_SESSION['working_branch_id'];
    }
    if (!empty($_SESSION['branch_id'])) {
        return (int) $_SESSION['branch_id'];
    }
    return 0;
}

/**
 * @return ?array One tbl_branches row
 */
function auragold_get_logged_in_branch_row(): ?array {
    $id = auragold_get_logged_in_branch_id();
    if ($id <= 0) {
        return null;
    }
    if (function_exists('auragold_registry_tbl_branches_row_by_id')) {
        $r = auragold_registry_tbl_branches_row_by_id($id);
        if ($r) {
            return $r;
        }
    }
    if (function_exists('getRecordMaster')) {
        $r = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . (int) $id . ' LIMIT 1');
        return $r ? $r : null;
    }
    return null;
}

/** @param array $row tbl_branches row */
function isMainBranch(array $row): bool {
    return (int) ($row['main_branch_id'] ?? 0) === 0;
}

/** @param array $row tbl_branches row */
function isSubBranch(array $row): bool {
    return (int) ($row['main_branch_id'] ?? 0) > 0;
}

/**
 * @return ?array Main tbl_branches row, or null
 */
function getParentMainBranch(?array $subOrAnyRow = null): ?array {
    $row = $subOrAnyRow;
    if ($row === null) {
        $row = auragold_get_logged_in_branch_row();
    }
    if (!$row) {
        return null;
    }
    if (isMainBranch($row)) {
        return $row;
    }
    $mid = (int) ($row['main_branch_id'] ?? 0);
    if ($mid <= 0) {
        return null;
    }
    if (function_exists('auragold_registry_tbl_branches_row_by_id')) {
        $m = auragold_registry_tbl_branches_row_by_id($mid);
        if ($m) {
            return $m;
        }
    }
    if (function_exists('getRecordMaster')) {
        $m = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $mid . ' LIMIT 1');
        return $m ? $m : null;
    }
    return null;
}

/**
 * @return mysqli|null
 */
function connectBranchDatabase(string $dbName) {
    if (!function_exists('auragold_mysqli_connect_to_branch_database')) {
        require_once __DIR__ . '/barcode_prefix_check.php';
    }
    return auragold_mysqli_connect_to_branch_database(trim($dbName));
}

/**
 * @return ?array{ id: int, name: string, status: int|mixed }
 */
function findExistingProductInMain(mysqli $link, string $name): ?array {
    if ($name === '') {
        return null;
    }
    if (!function_exists('esc')) {
        return null;
    }
    $e = esc($name);
    $q = @mysqli_query($link, "SELECT id, name FROM tbl_products WHERE name = '$e' AND status = 1 LIMIT 1");
    if (!$q || mysqli_num_rows($q) === 0) {
        if ($q) {
            mysqli_free_result($q);
        }
        return null;
    }
    $r = mysqli_fetch_assoc($q);
    mysqli_free_result($q);
    return is_array($r) ? $r : null;
}

/**
 * @return bool True if a mapping row already exists
 */
function assignProductToSubBranch(mysqli $link, int $productId, int $subBranchId, bool $isActive = true): bool {
    $productId   = (int) $productId;
    $subBranchId = (int) $subBranchId;
    if ($productId <= 0 || $subBranchId <= 0) {
        return false;
    }
    if (!function_exists('auragold_ensure_tbl_product_branches_is_active') || !function_exists('auragold_tbl_product_branches_has_is_active')) {
        require_once __DIR__ . '/auragold_product_branch_local_schema.php';
    }
    if (function_exists('auragold_ensure_tbl_product_branches_is_active')) {
        auragold_ensure_tbl_product_branches_is_active($link);
    }
    if (function_exists('auragold_tbl_product_branches_has_is_active') && auragold_tbl_product_branches_has_is_active($link)) {
        $a = $isActive ? 1 : 0;
        $sql = "INSERT INTO tbl_product_branches (product_id, branch_id, is_active) VALUES ($productId, $subBranchId, $a)
            ON DUPLICATE KEY UPDATE is_active = GREATEST(is_active, $a)";
        return (bool) mysqli_query($link, $sql);
    }
    $sql = 'INSERT IGNORE INTO tbl_product_branches (product_id, branch_id) VALUES (' . $productId . ', ' . $subBranchId . ')';
    return (bool) mysqli_query($link, $sql);
}

/**
 * Resolve which mysqli to use for product master save.
 *
 * - Main branch login: current session $conn (that branch’s DB only).
 * - Sub branch login: parent main’s operational database (no duplicate master in the sub’s DB).
 *
 * @return array{ok:bool, link: ?mysqli, close_after: bool, is_sub: bool, main_branch_id: int, sub_branch_id: int, message: string}
 */
function auragold_product_opening_mysqli_for_login(mysqli $sessionConn): array {
    global $conn_master;

    $bad = static function (string $m) {
        return [
            'ok'            => false,
            'link'          => null,
            'close_after'   => false,
            'is_sub'        => false,
            'main_branch_id'=> 0,
            'sub_branch_id' => 0,
            'message'       => $m,
        ];
    };

    $bid = auragold_get_logged_in_branch_id();
    if ($bid <= 0) {
        return $bad('No branch in session. Sign in again or select a branch.');
    }

    $row = function_exists('auragold_registry_tbl_branches_row_by_id')
        ? auragold_registry_tbl_branches_row_by_id($bid)
        : null;
    if (!$row && function_exists('getRecordMaster')) {
        $row = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . (int) $bid . ' LIMIT 1');
    }
    if (!$row) {
        return $bad('Branch (id ' . (int) $bid . ') was not found in the registry.');
    }

    if (function_exists('auragold_tbl_branch_row_is_active') && !auragold_tbl_branch_row_is_active($row)) {
        return $bad('This branch is not active. Enable it in Branches or use another account.');
    }

    $reg = ($conn_master instanceof mysqli) ? $conn_master : $sessionConn;
    if (!isSubBranch($row)) {
        return [
            'ok'            => true,
            'link'          => $sessionConn,
            'close_after'   => false,
            'is_sub'        => false,
            'main_branch_id'=> (int) ($row['id'] ?? 0),
            'sub_branch_id' => 0,
            'message'       => '',
        ];
    }

    $parent = getParentMainBranch($row);
    if (!$parent) {
        return $bad('Sub-branch is missing a valid parent main row (main_branch_id). Fix tbl_branches.');
    }

    $creds  = auragold_resolve_branch_operational_credentials($parent, $reg);
    $mainDb = trim((string) ($creds['db_name'] ?? ''));
    if ($mainDb === '') {
        return $bad('Parent main branch has no database name. Set db_name (or use “Create DB”) in Branches.');
    }

    $curDb = '';
    $rdb   = @mysqli_query($sessionConn, 'SELECT DATABASE() AS d');
    if ($rdb && $dr = mysqli_fetch_assoc($rdb)) {
        $curDb = trim((string) ($dr['d'] ?? ''));
    }
    if ($rdb) {
        mysqli_free_result($rdb);
    }

    if (strcasecmp($curDb, $mainDb) === 0) {
        return [
            'ok'            => true,
            'link'          => $sessionConn,
            'close_after'   => false,
            'is_sub'        => true,
            'main_branch_id'=> (int) ($parent['id'] ?? 0),
            'sub_branch_id' => (int) ($row['id'] ?? 0),
            'message'       => '',
        ];
    }

    $user = $creds['db_user'] !== '' ? (string) $creds['db_user'] : (defined('DB_USER') ? (string) DB_USER : 'root');
    $pass = $creds['db_user'] !== '' ? (string) $creds['db_pass'] : (defined('DB_PASS') ? (string) DB_PASS : '');

    $link = @mysqli_connect(
        defined('DB_HOST') ? (string) DB_HOST : '127.0.0.1',
        $user,
        $pass,
        $mainDb
    );
    if (!$link) {
        return $bad('Could not open parent main database “' . $mainDb . '”: ' . mysqli_connect_error());
    }
    mysqli_set_charset($link, 'utf8mb4');

    return [
        'ok'            => true,
        'link'          => $link,
        'close_after'   => true,
        'is_sub'        => true,
        'main_branch_id'=> (int) ($parent['id'] ?? 0),
        'sub_branch_id' => (int) ($row['id'] ?? 0),
        'message'       => '',
    ];
}

/** Aliases requested for readability / shared docs */
if (!function_exists('getLoggedInBranch')) {
    function getLoggedInBranch(): ?array { return auragold_get_logged_in_branch_row(); }
}
if (!function_exists('getParentMainBranchId')) {
    function getParentMainBranchId(?array $row = null): int {
        $m = getParentMainBranch($row);
        return (int) ($m['id'] ?? 0);
    }
}
if (!function_exists('addProductToMainBranch')) {
    function addProductToMainBranch(mysqli $link, array $post, array $opts = []): array {
        if (!function_exists('auragold_product_opening_save')) {
            require_once __DIR__ . '/product_opening_save_core.php';
        }
        return auragold_product_opening_save($link, $post, $opts);
    }
}
if (!function_exists('addProductByLoginContext')) {
    /**
     * @throws Exception
     * @return array{product_id:int, is_update: bool, skip_branch_sync?: bool} Same as auragold_product_opening_save.
     * Caller is responsible for closing a newly opened link from auragold_product_opening_mysqli_for_login when
     * that context return had close_after=true.
     */
    function addProductByLoginContext(mysqli $sessionConn, array $post, array $opts = []): array {
        $ctx = auragold_product_opening_mysqli_for_login($sessionConn);
        if (empty($ctx['ok']) || !($ctx['link'] instanceof mysqli)) {
            throw new Exception($ctx['message'] !== '' ? (string) $ctx['message'] : 'Invalid branch / database context.');
        }
        if (!function_exists('auragold_product_opening_save')) {
            require_once __DIR__ . '/product_opening_save_core.php';
        }
        return auragold_product_opening_save($ctx['link'], $post, $opts);
    }
}
