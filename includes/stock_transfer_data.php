<?php
/**
 * Stock transfer: row normalization for JSON (same row shape as stock-history inward query).
 */
require_once __DIR__ . '/stock_transfer_history_query.php';

/**
 * @param array<string,mixed> $r Row from auragold_stock_transfer_list_sql()
 * @return array<string,mixed>
 */
function auragold_stock_transfer_normalize_row(array $r) {
    $out = $r;
    $out['id'] = (int) ($r['id'] ?? 0);
    $net = isset($r['net_amt']) && $r['net_amt'] !== '' && $r['net_amt'] !== null
        ? (float) $r['net_amt']
        : (isset($r['value']) ? (float) $r['value'] : 0.0);
    $out['amount'] = $net;
    if (empty($out['image_urls']) && !empty($r['images'])) {
        $out['image_urls'] = (string) $r['images'];
    }
    return $out;
}
