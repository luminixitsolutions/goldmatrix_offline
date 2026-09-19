<?php
require_once __DIR__ . '/document_types_schema.php';
if (isset($conn) && $conn instanceof mysqli) {
    auragold_ensure_tbl_document_types($conn);
}
$suffixLedgerDt = '';
if (isset($conn) && $conn instanceof mysqli && function_exists('auragold_master_list_sql_suffix')) {
    $suffixLedgerDt = auragold_master_list_sql_suffix($conn, 'tbl_document_types');
}
$ledger_modal_document_types = [];
if (isset($conn) && $conn instanceof mysqli && function_exists('getList')) {
    $ledger_modal_document_types = getList(
        "SELECT id, name FROM tbl_document_types WHERE status = 1 $suffixLedgerDt ORDER BY name ASC"
    );
}
if (!is_array($ledger_modal_document_types)) {
    $ledger_modal_document_types = [];
}
$ledger_modal_document_types_payload = [];
foreach ($ledger_modal_document_types as $r) {
    $ledger_modal_document_types_payload[] = [
        'id' => isset($r['id']) ? (int) $r['id'] : 0,
        'name' => isset($r['name']) ? (string) $r['name'] : '',
    ];
}
?>
<script>
window.ledgerDocumentTypes = <?php echo json_encode(
    $ledger_modal_document_types_payload,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
); ?>;
</script>
