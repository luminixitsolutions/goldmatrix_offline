<?php
if (!function_exists('auragold_nominee_relationship_options')) {
    require_once __DIR__ . '/customer_nominee_schema.php';
}
$nominee_relationship_options = auragold_nominee_relationship_options();
?>
<div class="ccm-panel" id="ledgerNomineePanel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h6 class="ccm-panel-title" style="margin:0;">Nominee Details</h6>
        <button type="button" class="btn btn-sm ccm-sh-add-btn" id="addNomineeBtn" title="Add nominee" aria-label="Add nominee"
                style="background:#c5a864;color:#fff;border:none;width:34px;height:34px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:4px;cursor:pointer;">
            <i class="feather icon-plus" aria-hidden="true" style="color:#fff;"></i>
        </button>
    </div>
    <p class="text-muted small mb-2" style="margin-top:-0.35rem;">Add one or more nominees for this ledger (name, relationship, contact).</p>
    <div style="overflow-x:auto;">
        <table class="table" id="nomineesTable" style="margin-bottom:0;font-size:0.85rem;">
            <thead style="background:#11294b;color:#fff;">
                <tr>
                    <th style="padding:0.6rem 0.75rem;font-weight:600;font-size:0.85rem;border:none;min-width:140px;">Name</th>
                    <th style="padding:0.6rem 0.75rem;font-weight:600;font-size:0.85rem;border:none;min-width:130px;">Relationship</th>
                    <th style="padding:0.6rem 0.75rem;font-weight:600;font-size:0.85rem;border:none;min-width:120px;">Mobile No</th>
                    <th style="padding:0.6rem 0.75rem;font-weight:600;font-size:0.85rem;border:none;min-width:130px;">Date of Birth</th>
                    <th style="padding:0.6rem 0.75rem;font-weight:600;font-size:0.85rem;border:none;min-width:160px;">Address</th>
                    <th style="padding:0.6rem 0.75rem;font-weight:600;font-size:0.85rem;border:none;width:60px;text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody id="nomineesTableBody"></tbody>
        </table>
    </div>
    <p id="nomineesEmptyHint" class="text-muted small mb-0 mt-2">No nominees added yet. Click + to add.</p>
</div>
<script>
window.ledgerNomineeRelationshipOptions = <?php echo json_encode(
    array_values($nominee_relationship_options),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
); ?>;
</script>
