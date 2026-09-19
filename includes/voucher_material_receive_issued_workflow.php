<?php

/** Compact hint for Material Receive issued panels. */
if (($auragold_voucher_ds_kind ?? '') !== 'material_receive') {
    return;
}

?>
<div class="mr-issued-receive-zone mr-issued-receive-zone--compact">
    <p class="mr-issued-quick-hint mb-0">
        <strong>How:</strong> Select lines &rarr; enter receive weight &rarr; <em>Add selected to receive</em> &rarr; Save
        <span class="mr-issued-legend-inline">
            <span class="mr-issued-badge mr-issued-badge--to-receive">To receive</span>
            <span class="mr-issued-badge mr-issued-badge--partial">Partial</span>
            <span class="mr-issued-badge mr-issued-badge--received">Received</span>
            <span class="mr-issued-badge mr-issued-badge--pending">Pending</span>
        </span>
    </p>
</div>
