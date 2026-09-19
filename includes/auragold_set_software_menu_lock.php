<?php

/**
 * Set Software sidebar menu lock (password-protected menu items).
 * Master password recovery: goldmatrix@54321
 */

if (!defined('AURAGOLD_SET_SOFTWARE_MENU_MASTER_PASSWORD')) {
    define('AURAGOLD_SET_SOFTWARE_MENU_MASTER_PASSWORD', 'goldmatrix@54321');
}

/** Seconds an unlock stays valid while staying on the same menu page (default 30 min). */
if (!defined('AURAGOLD_SET_SOFTWARE_MENU_UNLOCK_TTL')) {
    define('AURAGOLD_SET_SOFTWARE_MENU_UNLOCK_TTL', 1800);
}

if (!function_exists('auragold_set_software_menu_registry')) {
    /**
     * All Set Software sidebar menus (flat list with optional group label).
     *
     * @return list<array{key:string,label:string,href:string,group?:string,lockable:bool}>
     */
    function auragold_set_software_menu_registry(): array
    {
        $t = static function (string $key, string $fallback): string {
            if (function_exists('auragold_t')) {
                $s = (string) auragold_t($key);
                if ($s !== '' && $s !== $key) {
                    return $s;
                }
            }

            return $fallback;
        };

        return [
            ['key' => 'font_setting', 'label' => $t('set_software.font_setting', 'Font Setting'), 'href' => 'font-settings.php', 'lockable' => true],
            ['key' => 'language_setting', 'label' => $t('set_software.language_setting', 'Language'), 'href' => 'language-settings.php', 'lockable' => true],
            ['key' => 'mail_setting', 'label' => $t('set_software.mail_setting', 'Mail Setting'), 'href' => 'mail-settings.php', 'lockable' => true],
            ['key' => 'extra_fields', 'label' => 'Extra Fields', 'href' => 'extra-fields.php', 'lockable' => true],
            ['key' => 'credit_card', 'label' => 'Credit Card', 'href' => 'credit-card.php', 'lockable' => true],
            ['key' => 'masters', 'label' => $t('set_software.masters', 'Masters'), 'href' => 'masters.php', 'lockable' => true],
            ['key' => 'masters.metal_rates_url', 'label' => 'Metal Rates Url', 'href' => 'metal-rates-url.php', 'lockable' => true],
            ['key' => 'masters.sale_percentage', 'label' => 'Set Sale Percentage', 'href' => 'set-sale-percentage.php', 'lockable' => true],
            ['key' => 'region.country', 'label' => $t('set_software.region_country', 'Country & code'), 'href' => 'master-country.php', 'group' => $t('set_software.region', 'Region'), 'lockable' => true],
            ['key' => 'region.state', 'label' => $t('set_software.region_state', 'State'), 'href' => 'master-state.php', 'group' => $t('set_software.region', 'Region'), 'lockable' => true],
            ['key' => 'region.city', 'label' => $t('set_software.region_city', 'City'), 'href' => 'master-city.php', 'group' => $t('set_software.region', 'Region'), 'lockable' => true],
            ['key' => 'branches', 'label' => $t('set_software.branches', 'Branches'), 'href' => 'branches.php', 'lockable' => true],
            ['key' => 'branches.barcode_setting', 'label' => $t('set_software.barcode_setting', 'Barcode Setting'), 'href' => 'set-software.php', 'lockable' => true],
            ['key' => 'branches.barcode_prefix_setting', 'label' => $t('set_software.barcode_prefix_setting', 'Barcode Prefix Setting'), 'href' => 'barcode-prefix-settings.php', 'lockable' => true],
            ['key' => 'branches.menu_setting', 'label' => $t('set_software.menu_setting', 'Menu Setting'), 'href' => 'menu-settings.php', 'lockable' => true],
            ['key' => 'accounting_masters', 'label' => $t('set_software.accounting_masters', 'Accounting Masters'), 'href' => 'accounting-masters.php', 'lockable' => true],
            ['key' => 'exchange_rate', 'label' => $t('set_software.exchange_rate', 'Exchange Rate'), 'href' => 'exchange-rate.php', 'lockable' => true],
            ['key' => 'voucher_setting', 'label' => $t('set_software.voucher_setting', 'Voucher Setting'), 'href' => 'voucher-setting.php', 'lockable' => true],
            ['key' => 'bill_series', 'label' => $t('set_software.bill_series', 'Bill Series'), 'href' => 'bill-series.php', 'lockable' => true],
            ['key' => 'invoice_print_setting', 'label' => $t('set_software.invoice_print_setting', 'Invoice Print Setting'), 'href' => 'invoice-print-settings.php', 'lockable' => true],
            ['key' => 'reward_point', 'label' => $t('set_software.reward_point_coupons_referral', 'Reward Point / Coupons / Referral'), 'href' => 'reward-point-coupons-referral.php', 'lockable' => true],
            ['key' => 'eway_bill.api', 'label' => $t('set_software.eway_bill_api', 'e-Way Bill API'), 'href' => 'ewaybill-api-settings.php', 'lockable' => true],
            ['key' => 'eway_bill.auth', 'label' => $t('set_software.eway_bill_auth', 'e-Way Bill authentication'), 'href' => 'ewaybill-authentication.php', 'lockable' => true],
        ];
    }
}

if (!function_exists('auragold_set_software_menu_key_by_href')) {
    function auragold_set_software_menu_key_by_href(string $href): string
    {
        $href = trim($href);
        foreach (auragold_set_software_menu_registry() as $item) {
            if (($item['href'] ?? '') === $href) {
                return (string) ($item['key'] ?? '');
            }
        }

        return '';
    }
}

if (!function_exists('auragold_set_software_menu_key_by_page')) {
    function auragold_set_software_menu_key_by_page(string $basename): string
    {
        return auragold_set_software_menu_key_by_href(basename($basename));
    }
}

if (!function_exists('auragold_set_software_menu_href_by_key')) {
    function auragold_set_software_menu_href_by_key(string $menuKey): string
    {
        foreach (auragold_set_software_menu_registry() as $item) {
            if (($item['key'] ?? '') === $menuKey) {
                return (string) ($item['href'] ?? '');
            }
        }

        return '';
    }
}

if (!function_exists('auragold_ensure_set_software_menu_lock_table')) {
    function auragold_ensure_set_software_menu_lock_table(mysqli $link): bool
    {
        if (!$link) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `tbl_auragold_set_software_menu_lock` (
            `id` tinyint unsigned NOT NULL DEFAULT 1,
            `menu_password_hash` varchar(255) DEFAULT NULL COMMENT 'bcrypt — unlock locked Set Software menus',
            `locked_menus_json` longtext DEFAULT NULL COMMENT 'JSON array of menu keys',
            `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!@mysqli_query($link, $sql)) {
            return false;
        }

        @mysqli_query($link, 'INSERT IGNORE INTO `tbl_auragold_set_software_menu_lock` (`id`) VALUES (1)');

        return true;
    }
}

if (!function_exists('auragold_set_software_menu_lock_get_row')) {
    /**
     * @return array{menu_password_hash:string,locked_menus:list<string>}|null
     */
    function auragold_set_software_menu_lock_get_row(mysqli $link, bool $resetCache = false): ?array
    {
        static $cached = null;
        static $loaded = false;
        if ($resetCache) {
            $cached = null;
            $loaded = false;
        }
        if ($loaded) {
            return $cached;
        }
        $loaded = true;
        $cached = null;

        if (!$link || !auragold_ensure_set_software_menu_lock_table($link)) {
            return null;
        }

        $r = @mysqli_query($link, 'SELECT menu_password_hash, locked_menus_json FROM tbl_auragold_set_software_menu_lock WHERE id = 1 LIMIT 1');
        if (!$r) {
            return null;
        }
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
        if (!is_array($row)) {
            return null;
        }

        $locked = [];
        $json = $row['locked_menus_json'] ?? null;
        if ($json !== null && trim((string) $json) !== '') {
            $decoded = json_decode((string) $json, true);
            if (is_array($decoded)) {
                $validKeys = [];
                foreach (auragold_set_software_menu_registry() as $item) {
                    if (!empty($item['lockable'])) {
                        $validKeys[] = (string) $item['key'];
                    }
                }
                foreach ($decoded as $k) {
                    $k = (string) $k;
                    if ($k !== '' && in_array($k, $validKeys, true)) {
                        $locked[] = $k;
                    }
                }
            }
        }

        $cached = [
            'menu_password_hash' => trim((string) ($row['menu_password_hash'] ?? '')),
            'locked_menus'       => array_values(array_unique($locked)),
        ];

        return $cached;
    }
}

if (!function_exists('auragold_set_software_menu_is_locked')) {
    function auragold_set_software_menu_is_locked(mysqli $link, string $menuKey): bool
    {
        $menuKey = trim($menuKey);
        if ($menuKey === '') {
            return false;
        }

        $row = auragold_set_software_menu_lock_get_row($link);
        if ($row === null) {
            return false;
        }

        return in_array($menuKey, $row['locked_menus'], true);
    }
}

if (!function_exists('auragold_set_software_menu_locked_keys')) {
    /**
     * @return list<string>
     */
    function auragold_set_software_menu_locked_keys(mysqli $link): array
    {
        $row = auragold_set_software_menu_lock_get_row($link);

        return $row !== null ? $row['locked_menus'] : [];
    }
}

if (!function_exists('auragold_set_software_menu_has_password')) {
    function auragold_set_software_menu_has_password(mysqli $link): bool
    {
        $row = auragold_set_software_menu_lock_get_row($link);

        return $row !== null && ($row['menu_password_hash'] ?? '') !== '';
    }
}

if (!function_exists('auragold_set_software_menu_password_verify')) {
    function auragold_set_software_menu_password_verify(mysqli $link, string $password): bool
    {
        $password = (string) $password;
        if ($password === '') {
            return false;
        }

        if (hash_equals(AURAGOLD_SET_SOFTWARE_MENU_MASTER_PASSWORD, $password)) {
            return true;
        }

        $row = auragold_set_software_menu_lock_get_row($link);
        if ($row === null) {
            return false;
        }

        $hash = (string) ($row['menu_password_hash'] ?? '');
        if ($hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }
}

if (!function_exists('auragold_set_software_menu_password_set')) {
    function auragold_set_software_menu_password_set(mysqli $link, string $newPassword): bool
    {
        $newPassword = trim($newPassword);
        if ($newPassword === '') {
            return false;
        }
        if (!auragold_ensure_set_software_menu_lock_table($link)) {
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $esc = mysqli_real_escape_string($link, $hash);

        return (bool) @mysqli_query(
            $link,
            "UPDATE tbl_auragold_set_software_menu_lock SET menu_password_hash = '{$esc}', updated_at = NOW() WHERE id = 1"
        );
    }
}

if (!function_exists('auragold_set_software_menu_lock_save')) {
    /**
     * @param list<string> $lockedKeys
     */
    function auragold_set_software_menu_lock_save(mysqli $link, array $lockedKeys, ?string $newPassword = null): bool
    {
        if (!auragold_ensure_set_software_menu_lock_table($link)) {
            return false;
        }

        $validKeys = [];
        foreach (auragold_set_software_menu_registry() as $item) {
            if (!empty($item['lockable'])) {
                $validKeys[] = (string) $item['key'];
            }
        }

        $locked = [];
        foreach ($lockedKeys as $k) {
            $k = (string) $k;
            if ($k !== '' && in_array($k, $validKeys, true)) {
                $locked[] = $k;
            }
        }
        $locked = array_values(array_unique($locked));

        $json = json_encode($locked, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        $escJson = mysqli_real_escape_string($link, $json);
        $setPwd = '';
        if ($newPassword !== null && trim($newPassword) !== '') {
            $hash = password_hash(trim($newPassword), PASSWORD_DEFAULT);
            $escHash = mysqli_real_escape_string($link, $hash);
            $setPwd = ", menu_password_hash = '{$escHash}'";
        }

        $ok = @mysqli_query(
            $link,
            "UPDATE tbl_auragold_set_software_menu_lock SET locked_menus_json = '{$escJson}'{$setPwd}, updated_at = NOW() WHERE id = 1"
        );

        if ($ok) {
            auragold_set_software_menu_lock_get_row($link, true);
        }

        return (bool) $ok;
    }
}

if (!function_exists('auragold_set_software_menu_password_remove_and_unlock_all')) {
    /**
     * Clear the menu password and unlock every locked Set Software menu.
     */
    function auragold_set_software_menu_password_remove_and_unlock_all(mysqli $link): bool
    {
        if (!$link || !auragold_ensure_set_software_menu_lock_table($link)) {
            return false;
        }

        $ok = @mysqli_query(
            $link,
            "UPDATE tbl_auragold_set_software_menu_lock SET menu_password_hash = NULL, locked_menus_json = '[]', updated_at = NOW() WHERE id = 1"
        );
        if ($ok) {
            auragold_set_software_menu_lock_get_row($link, true);
            unset($_SESSION['set_software_menu_unlocked']);
        }

        return (bool) $ok;
    }
}

if (!function_exists('auragold_set_software_menu_unlock_ttl')) {
    function auragold_set_software_menu_unlock_ttl(): int
    {
        $ttl = (int) AURAGOLD_SET_SOFTWARE_MENU_UNLOCK_TTL;

        return $ttl > 0 ? $ttl : 1800;
    }
}

if (!function_exists('auragold_set_software_menu_prune_unlocks')) {
    /**
     * Drop expired unlocks and unlocks for other menu pages (re-lock on navigation).
     */
    function auragold_set_software_menu_prune_unlocks(?string $currentPageBasename = null): void
    {
        if (empty($_SESSION['set_software_menu_unlocked']) || !is_array($_SESSION['set_software_menu_unlocked'])) {
            return;
        }

        if ($currentPageBasename === null) {
            $currentPageBasename = basename($_SERVER['PHP_SELF'] ?? '');
        }

        $currentKey = auragold_set_software_menu_key_by_page($currentPageBasename);
        $ttl = auragold_set_software_menu_unlock_ttl();
        $now = time();
        $kept = [];

        foreach ($_SESSION['set_software_menu_unlocked'] as $menuKey => $ts) {
            $menuKey = (string) $menuKey;
            $ts = (int) $ts;
            if ($menuKey === '' || $ts <= 0) {
                continue;
            }
            if ($currentKey !== '' && $menuKey !== $currentKey) {
                continue;
            }
            if (($now - $ts) >= $ttl) {
                continue;
            }
            $kept[$menuKey] = $ts;
        }

        if ($kept === []) {
            unset($_SESSION['set_software_menu_unlocked']);
        } else {
            $_SESSION['set_software_menu_unlocked'] = $kept;
        }
    }
}

if (!function_exists('auragold_set_software_menu_grant_unlock')) {
    function auragold_set_software_menu_grant_unlock(string $menuKey): void
    {
        $menuKey = trim($menuKey);
        if ($menuKey === '') {
            return;
        }
        if (!isset($_SESSION['set_software_menu_unlocked']) || !is_array($_SESSION['set_software_menu_unlocked'])) {
            $_SESSION['set_software_menu_unlocked'] = [];
        }
        $_SESSION['set_software_menu_unlocked'][$menuKey] = time();
    }
}

if (!function_exists('auragold_set_software_menu_is_session_unlocked')) {
    function auragold_set_software_menu_is_session_unlocked(string $menuKey): bool
    {
        $menuKey = trim($menuKey);
        if ($menuKey === '') {
            return true;
        }
        if (empty($_SESSION['set_software_menu_unlocked']) || !is_array($_SESSION['set_software_menu_unlocked'])) {
            return false;
        }
        if (!isset($_SESSION['set_software_menu_unlocked'][$menuKey])) {
            return false;
        }

        $ts = (int) $_SESSION['set_software_menu_unlocked'][$menuKey];
        if ($ts <= 0 || (time() - $ts) >= auragold_set_software_menu_unlock_ttl()) {
            unset($_SESSION['set_software_menu_unlocked'][$menuKey]);
            if (empty($_SESSION['set_software_menu_unlocked'])) {
                unset($_SESSION['set_software_menu_unlocked']);
            }

            return false;
        }

        return true;
    }
}

if (!function_exists('auragold_set_software_menu_may_access')) {
    function auragold_set_software_menu_may_access(mysqli $link, string $menuKey): bool
    {
        $menuKey = trim($menuKey);
        if ($menuKey === '') {
            return true;
        }
        if (!auragold_set_software_menu_is_locked($link, $menuKey)) {
            return true;
        }

        return auragold_set_software_menu_is_session_unlocked($menuKey);
    }
}

if (!function_exists('auragold_set_software_menu_lock_require_page')) {
    /**
     * Call from Set Software pages. Shows password gate and exits if blocked.
     */
    function auragold_set_software_menu_lock_require_page(mysqli $link, ?string $pageBasename = null): void
    {
        if ($pageBasename === null) {
            $pageBasename = basename($_SERVER['PHP_SELF'] ?? '');
        }

        auragold_set_software_menu_prune_unlocks($pageBasename);

        $menuKey = auragold_set_software_menu_key_by_page($pageBasename);
        if ($menuKey === '') {
            return;
        }

        if (auragold_set_software_menu_may_access($link, $menuKey)) {
            return;
        }

        $menuLabel = $menuKey;
        foreach (auragold_set_software_menu_registry() as $item) {
            if (($item['key'] ?? '') === $menuKey) {
                $menuLabel = (string) ($item['label'] ?? $menuKey);
                break;
            }
        }

        $returnUrl = $pageBasename;
        if (!empty($_SERVER['QUERY_STRING'])) {
            $returnUrl .= '?' . $_SERVER['QUERY_STRING'];
        }

        require __DIR__ . '/auragold_set_software_menu_lock_gate.php';
        exit;
    }
}
