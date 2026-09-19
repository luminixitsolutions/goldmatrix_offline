<?php
/**
 * Contact & Groups tab: group list + all customers (ledger) as user list.
 *
 * @var array $crm_customers  rows from tbl_customers
 * @var array $crm_contact_groups rows with user_count
 */

if (!function_exists('crm_cg_format_contact')) {
    function crm_cg_format_contact(array $row)
    {
        $m = trim((string) ($row['mobile_no'] ?? ''));
        $p = trim((string) ($row['phone_no'] ?? ''));
        $cc = trim((string) ($row['mobile_country_code'] ?? ''));
        if ($m !== '') {
            return ($cc !== '' ? $cc . ' ' : '') . $m;
        }
        return $p;
    }
}

$customers = isset($crm_customers) && is_array($crm_customers) ? $crm_customers : [];
$groups = isset($crm_contact_groups) && is_array($crm_contact_groups) ? $crm_contact_groups : [];
?>
<div class="crm-cg-wrap">
    <div class="crm-cg-toolbar">
        <div class="crm-cg-toolbar-left">
            <input type="text" id="crmGroupName" class="crm-cg-input-name" placeholder="Enter Group Name" maxlength="255" autocomplete="off">
            <label class="crm-cg-active-label">
                <input type="checkbox" id="crmGroupActive" checked> Active
            </label>
        </div>
        <div class="crm-cg-toolbar-right">
            <button type="button" class="crm-btn-outline" id="crmNewGroupBtn">New Group</button>
            <button type="button" class="crm-btn-outline" id="crmSaveGroupBtn">Save</button>
        </div>
    </div>
    <div class="crm-cg-columns">
        <div class="crm-cg-col crm-cg-col-groups">
            <div class="crm-card crm-cg-card">
                <div class="crm-table-wrap crm-cg-scroll">
                    <table class="crm-table crm-cg-table">
                        <thead>
                            <tr>
                                <th style="width:52px;">Sr.No</th>
                                <th>Group Name</th>
                                <th>Created Date</th>
                                <th style="width:88px;">User Count</th>
                                <th class="crm-th-actions" style="width:40px;"><i class="feather icon-settings" style="color:#6b46c1;"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($groups)): ?>
                            <tr>
                                <td colspan="5" class="crm-empty-cell">No Rows To Show</td>
                            </tr>
                            <?php else: ?>
                                <?php $sr = 0; ?>
                                <?php foreach ($groups as $g): ?>
                                    <?php
                                    $sr++;
                                    $gn = htmlspecialchars((string) ($g['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                                    $cd = !empty($g['created_at']) ? date('d-m-Y', strtotime($g['created_at'])) : '—';
                                    $uc = (int) ($g['user_count'] ?? 0);
                                    ?>
                                    <tr>
                                        <td><?php echo (int) $sr; ?></td>
                                        <td><?php echo $gn; ?></td>
                                        <td><?php echo htmlspecialchars($cd, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) $uc; ?></td>
                                        <td></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="crm-cg-col crm-cg-col-users">
            <div class="crm-card crm-cg-card">
                <div class="crm-table-wrap crm-cg-scroll">
                    <table class="crm-table crm-cg-table" id="crmUserListTable">
                        <thead>
                            <tr>
                                <th class="crm-cg-th-search">
                                    <span class="crm-cg-th-title">User List</span>
                                    <input type="search" class="crm-cg-search" id="crmUserSearchName" placeholder="Search" autocomplete="off">
                                </th>
                                <th class="crm-cg-th-search">
                                    <span class="crm-cg-th-title">Contact</span>
                                    <input type="search" class="crm-cg-search" id="crmUserSearchContact" placeholder="Search" autocomplete="off">
                                </th>
                                <th style="width:72px;">Status <span class="crm-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                <th style="width:44px;text-align:center;">
                                    <input type="checkbox" id="crmUserSelectAll" title="Select all">
                                </th>
                            </tr>
                        </thead>
                        <tbody id="crmUserListBody">
                            <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="4" class="crm-empty-cell">No Rows To Show</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $c): ?>
                                    <?php
                                    $cid = (int) ($c['id'] ?? 0);
                                    $nm = trim((string) ($c['name'] ?? ''));
                                    $contact = crm_cg_format_contact($c);
                                    $st = (int) ($c['status'] ?? 1);
                                    $active = ($st === 1);
                                    $name_l = function_exists('mb_strtolower') ? mb_strtolower($nm, 'UTF-8') : strtolower($nm);
                                    $contact_l = function_exists('mb_strtolower') ? mb_strtolower($contact, 'UTF-8') : strtolower($contact);
                                    $nm_esc = htmlspecialchars($nm, ENT_QUOTES, 'UTF-8');
                                    $ct_esc = htmlspecialchars($contact !== '' ? $contact : '—', ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr class="crm-user-row" data-name="<?php echo htmlspecialchars($name_l, ENT_QUOTES, 'UTF-8'); ?>" data-contact="<?php echo htmlspecialchars($contact_l, ENT_QUOTES, 'UTF-8'); ?>">
                                        <td><?php echo $nm_esc; ?></td>
                                        <td><?php echo $ct_esc; ?></td>
                                        <td class="crm-cg-status-cell">
                                            <?php if ($active): ?>
                                                <span class="crm-cg-st-active" title="Active"><i class="feather icon-check"></i></span>
                                            <?php else: ?>
                                                <span class="crm-cg-st-blocked" title="Inactive"><i class="feather icon-slash"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="checkbox" class="crm-user-cb" name="crm_user_sel[]" value="<?php echo $cid; ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
