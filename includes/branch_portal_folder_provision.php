<?php
/**
 * Create a filesystem folder per branch with config.php + index.php (redirects to admin with branch_entry).
 * Main:  {project_root}/{slug}/
 * Sub:   {project_root}/{main_slug}/{sub_slug}/
 * Production: see DEPLOY_NOTES.txt inside the folder (subdomain / path hints).
 */
require_once __DIR__ . '/branch_credentials.php';

if (!function_exists('auragold_branch_portal_slug_from_row')) {
    function auragold_branch_portal_slug_from_row(array $row): string {
        $code = trim((string) ($row['code'] ?? ''));
        $base = $code !== '' ? $code : trim((string) ($row['name'] ?? 'branch'));
        $s    = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $base));
        $s    = trim($s, '-');
        if ($s === '') {
            $s = 'branch-' . (int) ($row['id'] ?? 0);
        }
        if (strlen($s) > 80) {
            $s = substr($s, 0, 80);
            $s = rtrim($s, '-');
        }
        return $s;
    }
}

if (!function_exists('auragold_branch_portal_project_root')) {
    function auragold_branch_portal_project_root(): string {
        return dirname(__DIR__, 2);
    }
}

if (!function_exists('auragold_branch_portal_unique_slug_path')) {
    /**
     * @return array{slug:string,full_path:string}
     */
    function auragold_branch_portal_unique_slug_path(string $baseDir, string $desiredSlug): array {
        $desiredSlug = trim((string) $desiredSlug);
        if ($desiredSlug === '') {
            $desiredSlug = 'branch';
        }
        $slug = $desiredSlug;
        $path = $baseDir . DIRECTORY_SEPARATOR . $slug;
        $n    = 2;
        while (is_dir($path) && is_file($path . DIRECTORY_SEPARATOR . 'config.php')) {
            $slug = $desiredSlug . '-' . $n;
            $path = $baseDir . DIRECTORY_SEPARATOR . $slug;
            $n++;
            if ($n > 200) {
                break;
            }
        }
        return ['slug' => $slug, 'full_path' => $path];
    }
}

if (!function_exists('auragold_branch_portal_write_files')) {
    /**
     * @return array{ok:bool,message:string,path?:string,public_path?:string,deploy_notes?:string}
     */
    function auragold_branch_portal_write_files(
        string $fullPath,
        array $branchRow,
        string $adminRelPath,
        string $deployNotes
    ): array {
        if (!is_dir($fullPath) && !@mkdir($fullPath, 0755, true)) {
            return ['ok' => false, 'message' => 'Could not create directory: ' . $fullPath];
        }
        $id   = (int) ($branchRow['id'] ?? 0);
        $cred = auragold_branch_row_db_credentials($branchRow);
        $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
        $dbn  = trim((string) ($cred['db_name'] ?? ''));
        $dbu  = trim((string) ($cred['db_user'] ?? ''));
        $dbp  = (string) ($cred['db_pass'] ?? '');
        if ($dbn === '') {
            $dbn = defined('DB_NAME') ? (string) DB_NAME : '';
        }
        if ($dbu === '') {
            $dbu = defined('DB_USER') ? (string) DB_USER : '';
        }
        if ($dbu !== '' && $dbp === '' && defined('DB_PASS')) {
            $dbp = (string) DB_PASS;
        }

        $cfg = "<?php\n"
            . "/**\n * Auto-generated branch portal — connection for this branch (tbl_branches id " . $id . ").\n"
            . " * Regenerated when the branch is saved from Branches.\n */\n"
            . "if (!defined('AURAGOLD_BRANCH_PORTAL')) {\n    define('AURAGOLD_BRANCH_PORTAL', true);\n}\n"
            . 'define(' . "'AURAGOLD_BRANCH_ID'" . ', ' . $id . ");\n"
            . 'define(' . "'AURAGOLD_DB_HOST'" . ', ' . var_export($host, true) . ");\n"
            . 'define(' . "'AURAGOLD_DB_NAME'" . ', ' . var_export($dbn, true) . ");\n"
            . 'define(' . "'AURAGOLD_DB_USER'" . ', ' . var_export($dbu, true) . ");\n"
            . 'define(' . "'AURAGOLD_DB_PASS'" . ', ' . var_export($dbp, true) . ");\n"
            . 'define(' . "'AURAGOLD_ADMIN_REL_PATH'" . ', ' . var_export($adminRelPath, true) . ");\n";

        $idx = "<?php\n"
            . "/**\n * Branch portal entry: sends users to the shared admin app with this branch pre-selected.\n */\n"
            . "require __DIR__ . '/config.php';\n"
            . "\$bid = (int) AURAGOLD_BRANCH_ID;\n"
            . "\$rel = str_replace('\\\\', '/', rtrim((string) AURAGOLD_ADMIN_REL_PATH, '/'));\n"
            . "\$target = \$rel . '/index.php?branch_entry=' . \$bid;\n"
            . "if (!headers_sent()) {\n"
            . "    header('Location: ' . \$target);\n"
            . "    exit;\n"
            . "}\n"
            . "echo '<!DOCTYPE html><html><head><meta http-equiv=\"refresh\" content=\"0;url=' . htmlspecialchars(\$target, ENT_QUOTES, 'UTF-8') . '\"></head><body><a href=\"' . htmlspecialchars(\$target, ENT_QUOTES, 'UTF-8') . '\">Continue to login</a></body></html>';\n";

        $notes = trim($deployNotes);
        if ($notes === '') {
            $notes = 'See your hosting panel to point a subdomain or path at this folder.';
        }

        $w1 = @file_put_contents($fullPath . DIRECTORY_SEPARATOR . 'config.php', $cfg);
        $w2 = @file_put_contents($fullPath . DIRECTORY_SEPARATOR . 'index.php', $idx);
        $w3 = @file_put_contents($fullPath . DIRECTORY_SEPARATOR . 'DEPLOY_NOTES.txt', $notes . "\n");
        if ($w1 === false || $w2 === false) {
            return ['ok' => false, 'message' => 'Could not write portal files in ' . $fullPath];
        }
        return [
            'ok'           => true,
            'message'      => 'Portal folder ready.',
            'path'         => $fullPath,
            'public_path'  => str_replace('\\', '/', $fullPath),
            'deploy_notes' => $notes,
        ];
    }
}

if (!function_exists('auragold_branch_portal_production_deploy_notes')) {
    function auragold_branch_portal_production_deploy_notes(
        string $slug,
        bool $isSub,
        ?string $mainSlug
    ): string {
        global $domain;
        $d = '';
        if (isset($domain) && is_string($domain)) {
            $d = trim($domain);
        }
        if ($d === '' && defined('AURAGOLD_PORTAL_DOMAIN')) {
            $d = trim((string) AURAGOLD_PORTAL_DOMAIN);
        }
        $lines   = [];
        $lines[] = 'GoldMatrix branch portal — deployment hints';
        $lines[] = '';
        if ($d !== '') {
            if (!$isSub) {
                $lines[] = 'Suggested subdomain (main branch): https://' . $slug . '.' . $d . '/';
                $lines[] = 'Point the subdomain document root to this folder, or use a rewrite to index.php.';
            } else {
                $ms = $mainSlug !== null && $mainSlug !== '' ? $mainSlug : 'main';
                $lines[] = 'Option A — path under main site: https://' . $ms . '.' . $d . '/' . $slug . '/';
                $lines[] = 'Option B — nested folder on filesystem: webroot/' . $ms . '/' . $slug . '/ (this generator uses that layout locally).';
            }
        } else {
            $lines[] = 'Local / generic: open this folder via your web server (e.g. http://localhost/auragold/' . ($isSub && $mainSlug ? $mainSlug . '/' . $slug : $slug) . '/).';
        }
        $lines[] = '';
        $lines[] = 'The shared application always lives in the /admin folder next to this project root.';
        return implode("\n", $lines);
    }
}

if (!function_exists('auragold_branch_portal_provision')) {
    /**
     * @return array{ok:bool,message:string,path?:string,slug?:string,url_hint?:string}
     */
    function auragold_branch_portal_provision(mysqli $conn_master, int $branchId): array {
        $branchId = (int) $branchId;
        if ($branchId <= 0 || !function_exists('getRecordMaster')) {
            return ['ok' => false, 'message' => 'Invalid branch id for portal.'];
        }
        $row = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $branchId . ' LIMIT 1');
        if (!$row) {
            return ['ok' => false, 'message' => 'Branch not found for portal.'];
        }
        $mainBid  = (int) ($row['main_branch_id'] ?? 0);
        $isSub    = $mainBid > 0;
        $slug     = auragold_branch_portal_slug_from_row($row);
        $root     = auragold_branch_portal_project_root();
        $mainRow  = null;

        if ($isSub) {
            $mainRow = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $mainBid . ' LIMIT 1');
            if (!$mainRow) {
                return ['ok' => false, 'message' => 'Parent main branch missing for portal.'];
            }
            $mainSlug     = auragold_branch_portal_slug_from_row($mainRow);
            $mainBase     = $root . DIRECTORY_SEPARATOR . $mainSlug;
            $unique       = auragold_branch_portal_unique_slug_path($mainBase, $slug);
            $fullPath     = $unique['full_path'];
            $finalSlug    = $unique['slug'];
            $adminRel     = '../../admin';
            $deployNotes  = auragold_branch_portal_production_deploy_notes($finalSlug, true, $mainSlug);
        } else {
            $unique    = auragold_branch_portal_unique_slug_path($root, $slug);
            $fullPath  = $unique['full_path'];
            $finalSlug = $unique['slug'];
            $adminRel  = '../admin';
            $deployNotes = auragold_branch_portal_production_deploy_notes($finalSlug, false, null);
        }

        $w = auragold_branch_portal_write_files($fullPath, $row, $adminRel, $deployNotes);
        if (empty($w['ok'])) {
            return $w;
        }

        $projFolder = basename($root);
        if ($isSub && isset($mainRow) && is_array($mainRow)) {
            $urlHint = '(example) http://localhost/' . $projFolder . '/' . auragold_branch_portal_slug_from_row($mainRow) . '/' . $finalSlug . '/';
        } else {
            $urlHint = '(example) http://localhost/' . $projFolder . '/' . $finalSlug . '/';
        }

        return [
            'ok'       => true,
            'message'  => $w['message'] ?? 'Portal folder ready.',
            'path'     => $w['path'] ?? $fullPath,
            'slug'     => $finalSlug,
            'url_hint' => $urlHint,
        ];
    }
}

if (!function_exists('auragold_branch_portal_config_matches_branch_id')) {
    /**
     * True if generated branch portal config.php defines this tbl_branches id.
     */
    function auragold_branch_portal_config_matches_branch_id(string $configPath, int $branchId): bool {
        $raw = @file_get_contents($configPath);
        if ($raw === false || $raw === '') {
            return false;
        }
        if (preg_match("/define\s*\(\s*['\"]AURAGOLD_BRANCH_ID['\"]\s*,\s*(\d+)\s*\)/", $raw, $m)) {
            return (int) $m[1] === $branchId;
        }
        return false;
    }
}

if (!function_exists('auragold_branch_portal_find_first_folder_matching_branch')) {
    /**
     * Looks for a direct child directory of $baseDir whose config.php declares AURAGOLD_BRANCH_ID = $branchId.
     *
     * @return string|null Folder name (single segment), no slashes
     */
    function auragold_branch_portal_find_first_folder_matching_branch(string $baseDir, int $branchId): ?string {
        $baseDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $baseDir), DIRECTORY_SEPARATOR);
        if ($baseDir === '' || $branchId <= 0 || !is_dir($baseDir)) {
            return null;
        }
        $scan = @scandir($baseDir);
        if ($scan === false) {
            return null;
        }
        foreach ($scan as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $baseDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($path)) {
                continue;
            }
            $cfg = $path . DIRECTORY_SEPARATOR . 'config.php';
            if (!is_file($cfg)) {
                continue;
            }
            if (auragold_branch_portal_config_matches_branch_id($cfg, $branchId)) {
                return $entry;
            }
        }
        return null;
    }
}

if (!function_exists('auragold_branch_portal_logout_relative_url')) {
    /**
     * Relative URL from admin/ to this branch's portal folder (see auragold_branch_portal_provision layout).
     * Requires config.php (getRecordMaster).
     *
     * @return string e.g. "../main-branch/" or "../mumbai-branch/andheri/"
     */
    function auragold_branch_portal_logout_relative_url(int $branchId): string {
        $branchId = (int) $branchId;
        if ($branchId <= 0 || !function_exists('getRecordMaster')) {
            return 'index.php';
        }
        $row = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $branchId . ' LIMIT 1');
        if (!$row || !is_array($row)) {
            return 'index.php';
        }
        $root    = auragold_branch_portal_project_root();
        $mainBid = (int) ($row['main_branch_id'] ?? 0);
        if ($mainBid > 0) {
            $mainRow = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $mainBid . ' LIMIT 1');
            if (!$mainRow || !is_array($mainRow)) {
                return '../' . str_replace('\\', '/', auragold_branch_portal_slug_from_row($row)) . '/';
            }
            $mainFolder = auragold_branch_portal_find_first_folder_matching_branch($root, $mainBid);
            if ($mainFolder === null || $mainFolder === '') {
                $mainFolder = auragold_branch_portal_slug_from_row($mainRow);
            }
            $subBase = $root . DIRECTORY_SEPARATOR . $mainFolder;
            $subFolder = auragold_branch_portal_find_first_folder_matching_branch($subBase, $branchId);
            if ($subFolder === null || $subFolder === '') {
                $subFolder = auragold_branch_portal_slug_from_row($row);
            }
            return '../' . $mainFolder . '/' . $subFolder . '/';
        }
        $folder = auragold_branch_portal_find_first_folder_matching_branch($root, $branchId);
        if ($folder === null || $folder === '') {
            $folder = auragold_branch_portal_slug_from_row($row);
        }
        return '../' . $folder . '/';
    }
}
