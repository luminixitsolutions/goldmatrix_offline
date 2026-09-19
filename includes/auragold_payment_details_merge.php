<?php
/**
 * Merge JSON payment_details onto payment rows so edit mode restores modal fields
 * (scrap, metal exchange, card metadata, etc.). Preserves each row's database id * when the JSON payload contains an id key.
 *
 * @param array|null $payments Modified in place.
 */
function auragold_merge_payment_details_into_payments(&$payments) {
    if (!is_array($payments)) {
        return;
    }
    foreach ($payments as &$pr) {
        if (!is_array($pr)) {
            continue;
        }
        $pd_raw = $pr['payment_details'] ?? '';
        $id_keep = array_key_exists('id', $pr) ? $pr['id'] : null;
        if (!is_string($pd_raw) || $pd_raw === '') {
            continue;
        }
        $pd_dec = json_decode($pd_raw, true);
        if (!is_array($pd_dec)) {
            continue;
        }
        $pr = array_merge($pr, $pd_dec);
        if ($id_keep !== null) {
            $pr['id'] = $id_keep;
        }
    }
    unset($pr);
}
