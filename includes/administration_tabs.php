<?php
/**
 * Shared sub-tabs for Administration (Users, Roles, …).
 * Set $auragold_admin_tab before include: 'users' | 'departments' | 'designations' | 'roles' | 'permissions' | 'whitelist' | 'blocklist'
 */
$t = isset($auragold_admin_tab) ? (string) $auragold_admin_tab : 'users';
?>
<div class="um-tabs" role="tablist">
    <a href="user-management.php" class="um-tab<?php echo $t === 'users' ? ' active' : ''; ?>">Users</a>
    <a href="department-management.php" class="um-tab<?php echo $t === 'departments' ? ' active' : ''; ?>">Departments</a>
    <a href="designation-management.php" class="um-tab<?php echo $t === 'designations' ? ' active' : ''; ?>">Designations</a>
    <a href="permission-management.php" class="um-tab<?php echo $t === 'permissions' ? ' active' : ''; ?>">Permissions</a>
    <a href="whitelist-management.php" class="um-tab<?php echo $t === 'whitelist' ? ' active' : ''; ?>">Whitelist</a>
    <a href="blocklist-management.php" class="um-tab<?php echo $t === 'blocklist' ? ' active' : ''; ?>">Blocklist</a>
</div>
