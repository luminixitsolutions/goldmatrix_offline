<?php
/**
 * Download a sample .xlsx for Product Opening bulk import — full column layout matching product selection grid.
 */
session_start();

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Session expired. Please log in.';
    exit;
}

$voucher = isset($_GET['voucher']) ? trim((string) $_GET['voucher']) : '';
$allowedVouchers = ['product_opening', 'purchase_invoice', 'sale_order'];
if ($voucher !== '' && !in_array($voucher, $allowedVouchers, true)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid voucher';
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_product_metal_tab_match.php';
require_once __DIR__ . '/../includes/auragold_excel_sample_columns.php';

$item_id_dl = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
$product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
$characteristic_id = isset($_GET['characteristic_id']) ? (int) $_GET['characteristic_id'] : 0;
$metal_id_sample = isset($_GET['metal_id']) ? (int) $_GET['metal_id'] : 0;
$diamond_category_sample = isset($_GET['diamond_category']) ? trim((string) $_GET['diamond_category']) : '';
if ($diamond_category_sample !== '' && !in_array($diamond_category_sample, ['Diamonds', 'GemStones', 'Jewellery'], true)) {
    $diamond_category_sample = '';
}
$metal_name_sample = '';
if ($metal_id_sample > 0) {
    $metalRowSample = getRecord('SELECT display_name, system_name FROM tbl_metal WHERE id = ' . $metal_id_sample . ' LIMIT 1');
    if ($metalRowSample) {
        $metal_name_sample = trim((string) ($metalRowSample['display_name'] ?? $metalRowSample['system_name'] ?? ''));
    }
}

if ($voucher === 'purchase_invoice') {
    if ($item_id_dl <= 0) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'item_id is required for purchase invoice sample';
        exit;
    }
    $piiDl = getRecord('SELECT product_id, product_characteristic_id FROM tbl_purchase_invoice_items WHERE id = ' . $item_id_dl . ' LIMIT 1');
    if (!$piiDl) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Purchase invoice line not found';
        exit;
    }
    $dbPid = (int) ($piiDl['product_id'] ?? 0);
    $dbCid = (int) ($piiDl['product_characteristic_id'] ?? 0);
    if ($product_id > 0 && $dbPid > 0 && $product_id !== $dbPid) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'product_id does not match this invoice line';
        exit;
    }
    if ($characteristic_id > 0 && $dbCid > 0 && $characteristic_id !== $dbCid) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'characteristic_id does not match this invoice line';
        exit;
    }
    $product_id = $dbPid > 0 ? $dbPid : $product_id;
    $characteristic_id = $dbCid > 0 ? $dbCid : $characteristic_id;
}
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$product_name_db = '';
if ($product_id > 0) {
    $pr = getRecord('SELECT name FROM tbl_products WHERE id = ' . $product_id . ' LIMIT 1');
    if ($pr && isset($pr['name'])) {
        $product_name_db = trim((string) $pr['name']);
    }
}

// Master lists (same as stock-journal-create): dropdown columns get Excel data validation.
$sample_category_names = [];
foreach (getList('SELECT id, name FROM tbl_categories WHERE status = 1 ORDER BY name ASC') as $c) {
    $n = trim((string) ($c['name'] ?? ''));
    if ($n === '') {
        continue;
    }
    $sample_category_names[] = $n;
}
$sample_category_names = array_values(array_unique($sample_category_names));

$sample_location_names = [];
foreach (getList('SELECT id, name FROM tbl_location WHERE status = 1 ORDER BY id ASC') as $l) {
    $n = trim((string) ($l['name'] ?? ''));
    if ($n === '') {
        continue;
    }
    $sample_location_names[] = $n;
}
$sample_location_names = array_values(array_unique($sample_location_names));

// Must match `window.CALCULATION_MODES` / `buildCalculationSelectOptions()` in stock-journal-create.php
// (row calculation dropdown) — not tbl_calculation_modes, which is used elsewhere for different labels.
$sample_calculation_names = [
    'Carat X Rate',
    'Rate X Gross Wt',
    'Rate X Purity Wt',
    'Rate X Net Wt',
    'Rate X Final Wt',
    'Fix',
    'Stone Charge',
    'Attach Image Type',
];

$sample_product_labels = [];

$build_sample_product_labels_from_rows = static function (array $rows): array {
    $labels = [];
    $baseCounts = [];
    foreach ($rows as $row) {
        $n = trim((string) ($row['name'] ?? ''));
        if ($n === '') {
            continue;
        }
        $mn = trim((string) ($row['metal_name'] ?? ''));
        $base = $mn !== '' ? $n . ' - ' . $mn : $n;
        $baseCounts[$base] = ($baseCounts[$base] ?? 0) + 1;
    }
    foreach ($rows as $row) {
        $n = trim((string) ($row['name'] ?? ''));
        if ($n === '') {
            continue;
        }
        $mn = trim((string) ($row['metal_name'] ?? ''));
        $cid = (int) ($row['characteristic_id'] ?? 0);
        $base = $mn !== '' ? $n . ' - ' . $mn : $n;
        $label = $base;
        if (($baseCounts[$base] ?? 0) > 1 && $cid > 0) {
            $label = $base . ' [' . $cid . ']';
        }
        $labels[] = $label;
    }

    return array_values(array_unique($labels));
};

if ($voucher === 'sale_order' && $metal_id_sample > 0) {
    $where_so = 'p.status = 1 AND pc.status = 1' . auragold_sql_pc_metal_matches_tab_metal($metal_id_sample);
    if ($diamond_category_sample !== '') {
        $where_so .= " AND pc.diamond_category = '" . esc($diamond_category_sample) . "'";
    }
    $branch_filter_so = 0;
    if (!empty($_SESSION['working_branch_id'])) {
        $branch_filter_so = (int) $_SESSION['working_branch_id'];
    } elseif (!empty($_SESSION['branch_id'])) {
        $branch_filter_so = (int) $_SESSION['branch_id'];
    }
    $where_query_so = $where_so;
    if ($branch_filter_so > 0) {
        $where_query_so .= ' AND pc.branch_id = ' . $branch_filter_so;
    }
    $sql_so = '
        SELECT p.name, m.display_name AS metal_name, pc.id AS characteristic_id
        FROM tbl_products p
        INNER JOIN tbl_product_characteristics pc ON p.id = pc.product_id
        INNER JOIN tbl_metal m ON pc.metal_id = m.id
        WHERE ' . $where_query_so . '
        ORDER BY p.name ASC, pc.id ASC
        LIMIT 3000';
    $so_rows = getList($sql_so);
    if ((!is_array($so_rows) || count($so_rows) === 0) && $branch_filter_so > 0) {
        $sql_so = '
            SELECT p.name, m.display_name AS metal_name, pc.id AS characteristic_id
            FROM tbl_products p
            INNER JOIN tbl_product_characteristics pc ON p.id = pc.product_id
            INNER JOIN tbl_metal m ON pc.metal_id = m.id
            WHERE ' . $where_so . '
            ORDER BY p.name ASC, pc.id ASC
            LIMIT 3000';
        $so_rows = getList($sql_so);
    }
    $sample_product_labels = $build_sample_product_labels_from_rows(is_array($so_rows) ? $so_rows : []);
} elseif ($characteristic_id > 0) {
    $pchars = getList(
        'SELECT p.name, m.display_name AS metal_name, m.id AS metal_id, pc.id AS characteristic_id
        FROM tbl_product_characteristics pc
        INNER JOIN tbl_products p ON pc.product_id = p.id
        INNER JOIN tbl_metal m ON m.id = pc.metal_id
        WHERE pc.id = ' . (int) $characteristic_id . ' AND p.status = 1 AND pc.status = 1
        ORDER BY p.name ASC, pc.id ASC'
    );
    $sample_product_labels = $build_sample_product_labels_from_rows(is_array($pchars) ? $pchars : []);
    if ($metal_id_sample <= 0 && !empty($pchars[0]['metal_id'])) {
        $metal_id_sample = (int) $pchars[0]['metal_id'];
        if ($metal_name_sample === '' && !empty($pchars[0]['metal_name'])) {
            $metal_name_sample = trim((string) $pchars[0]['metal_name']);
        }
    }
}
if (empty($sample_product_labels) && $product_name_db !== '') {
    $sample_product_labels = [$product_name_db];
}

// Metal-section Carat = Karat (tbl_carat) — same as .carat-select in stock-journal-create.php. The first
// template column "Carat" (after Gross Wt.) is diamond carat/weight; only the second "Carat" is karat.
$sample_carat_names = [];
foreach (getList('SELECT id, name FROM tbl_carat WHERE status = 1 ORDER BY id ASC') as $cr) {
    $n = trim((string) ($cr['name'] ?? ''));
    if ($n === '') {
        continue;
    }
    $sample_carat_names[] = $n;
}
$sample_carat_names = array_values(array_unique($sample_carat_names));

$sample_discount_types = ['Fix', 'On Amount', 'On Making Amount', 'On Diamond Amount', 'On Stone Amount', 'On Net Amount', 'On Percentage'];
$sample_making_types = ['Fix', 'Per Gram', 'Per Piece', 'Per Kilogram', 'Per Percent', 'MRP', 'M.KT'];
$sample_stone_charge_types = ['Fix', 'Per Gram'];
$sample_tax_type_labels = ['Tax on making', 'Tax of net amount', 'No tax'];
$sample_other_charge_types = ['Fix', 'Percentage'];

$product_display_for_sample = $product_name_db;
if (!empty($sample_product_labels)) {
    $product_display_for_sample = $sample_product_labels[0];
} elseif ($characteristic_id > 0) {
    $pRow = getRecord(
        'SELECT p.name, m.display_name AS metal_name
        FROM tbl_product_characteristics pc
        INNER JOIN tbl_products p ON pc.product_id = p.id
        INNER JOIN tbl_metal m ON m.id = pc.metal_id
        WHERE pc.id = ' . (int) $characteristic_id . ' AND p.status = 1 AND pc.status = 1
        LIMIT 1'
    );
    if ($pRow) {
        $n = trim((string) ($pRow['name'] ?? ''));
        $mn = trim((string) ($pRow['metal_name'] ?? ''));
        if ($n !== '') {
            $product_display_for_sample = $mn !== '' ? $n . ' - ' . $mn : $n;
        }
    }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Stock import');

// Column order matches product selection / modal groups (same labels as Show/Hide columns UI).
// metal_id filters columns to the active tab (Gold/Silver = metal columns; Diamond & Stones = diamond columns).
$ef_branch_id = function_exists('auragold_settings_branch_id') ? (int) auragold_settings_branch_id() : 0;
$headers = auragold_excel_sample_headers_for_metal_tab_with_extras($conn, $metal_id_sample, $ef_branch_id);
$extra_field_defs_sample = auragold_excel_sample_extra_field_defs($conn, $metal_id_sample, $ef_branch_id);

$sheet->fromArray([$headers], null, 'A1', true);

$exampleRow = array_fill(0, count($headers), '');
// Sample values (by header label — first matching column wins for duplicates except Carat).
$labelIndices = [];
foreach ($headers as $i => $label) {
    $labelIndices[$label][] = $i;
}
$set = function (string $label, $value) use (&$exampleRow, $labelIndices) {
    if (!empty($labelIndices[$label])) {
        $exampleRow[$labelIndices[$label][0]] = $value;
    }
};
$set('Metal Qty', 1);
$set('Weight', 10.5);
$set('Purity %', 91.75);
$set('Loss Wt.', 0);
$set('Design No', 'DN001');
$set('HUID No', 'HUID001');
$set('RFIDCode', 'RFID001');
$set('Barcode', '');
$set('Images', '(optional: URL, base64, path, or embed picture)');
if ($product_display_for_sample !== '') {
    $set('Product', $product_display_for_sample);
}
// Karat dropdown: last "Carat" when two exist (diamond + metal); sole "Carat" on metal-only tabs.
if (!empty($labelIndices['Carat'])) {
    $caratCols = $labelIndices['Carat'];
    $karatIdx = count($caratCols) > 1 ? $caratCols[count($caratCols) - 1] : $caratCols[0];
    $exampleRow[$karatIdx] = $sample_carat_names[0] ?? '';
}
// Last columns: fixed context for this voucher (do not change when adding rows — import uses page / form product & characteristic).
$set('Product Name', $product_name_db);
$set('Product ID', $product_id > 0 ? $product_id : '');
$set('Characteristic ID', $characteristic_id > 0 ? $characteristic_id : '');

$sheet->fromArray([$exampleRow], null, 'A2', true);

$lastCol = Coordinate::stringFromColumnIndex(count($headers));

$sheet->freezePane('A2');
$sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
$sheet->getStyle('A1:' . $lastCol . '1')->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FFE2E8F0');
$sheet->getStyle('A1:' . $lastCol . '1')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER)
    ->setWrapText(true);

for ($c = 1; $c <= count($headers); $c++) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
}
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->getRowDimension(2)->setRowHeight(32);

// Hidden sheet holding allowed values so Excel can show the same options as the web (supports commas in labels).
$listSheetTitle = 'ListValues';
$listSheet = $spreadsheet->createSheet();
$listSheet->setTitle($listSheetTitle);

$sj_excel_list_write = static function (Worksheet $ls, int $listCol, array $vals): int {
    $i = 0;
    $colLetter = Coordinate::stringFromColumnIndex($listCol);
    foreach ($vals as $v) {
        $i++;
        $ls->setCellValue($colLetter . $i, (string) $v);
    }

    return $i;
};

$listDefOrder = [
    'category' => $sample_category_names,
    'calculation' => $sample_calculation_names,
    'product' => $sample_product_labels,
    'location' => $sample_location_names,
    'carat' => $sample_carat_names,
    'discount_type' => $sample_discount_types,
    'making_type' => $sample_making_types,
    'stone_charge_type' => $sample_stone_charge_types,
    'tax_type' => $sample_tax_type_labels,
    'other_charge_type' => $sample_other_charge_types,
];
foreach ($extra_field_defs_sample as $efDef) {
    if (($efDef['field_type'] ?? '') !== 'dropdown') {
        continue;
    }
    $opts = $efDef['dropdown_options'] ?? [];
    if (!is_array($opts) || $opts === []) {
        continue;
    }
    $efId = (int) ($efDef['id'] ?? 0);
    if ($efId <= 0) {
        continue;
    }
    $listDefOrder['ef_' . $efId] = array_values($opts);
}

$listRangeMeta = [];
$nextListCol = 1;
foreach ($listDefOrder as $listKey => $listVals) {
    $nRows = $sj_excel_list_write($listSheet, $nextListCol, $listVals);
    if ($nRows < 1) {
        continue;
    }
    $listRangeMeta[$listKey] = [
        'col1' => $nextListCol,
        'n' => $nRows,
        'colLetter' => Coordinate::stringFromColumnIndex($nextListCol),
    ];
    $nextListCol++;
}

$listSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

$spreadsheet->setActiveSheetIndex(0);

$dvFormula = static function (string $title, string $colLetter, int $n) {
    if ($n < 1) {
        return null;
    }

    return "'" . $title . "'!" . '$' . $colLetter . '$1:$' . $colLetter . '$' . $n;
};

$applyListDv = static function (Worksheet $sh, int $dataCol1Based, ?string $f1, int $maxDataRow): void {
    if ($f1 === null) {
        return;
    }
    $colL = Coordinate::stringFromColumnIndex($dataCol1Based);
    $val = new DataValidation();
    $val->setType(DataValidation::TYPE_LIST);
    $val->setErrorStyle(DataValidation::STYLE_STOP);
    $val->setAllowBlank(true);
    $val->setShowInputMessage(true);
    $val->setShowErrorMessage(true);
    // PhpSpreadsheet Xlsx writer inverts this flag: use true so OOXML showDropDown="0"
    // (show the in-cell list arrow; false produces showDropDown="1" and hides the arrow in Excel).
    $val->setShowDropDown(true);
    $val->setPromptTitle('Allowed values');
    $val->setPrompt('Options match the Stock Journal product opening form (same masters as the page).');
    $val->setErrorTitle('Value not in list');
    $val->setError('Choose from the list, or clear the cell.');
    $val->setFormula1($f1);
    $sh->setDataValidation($colL . '2:' . $colL . $maxDataRow, $val);
};

$headerToCol1 = static function (array $hdr, string $label) {
    $i = array_search($label, $hdr, true);

    return $i === false ? null : ((int) $i + 1);
};

$getFormula1ForListKey = static function (string $key) use ($listSheetTitle, $listRangeMeta, $dvFormula) {
    if (empty($listRangeMeta[$key]) || (int) ($listRangeMeta[$key]['n'] ?? 0) < 1) {
        return null;
    }
    $m = $listRangeMeta[$key];

    return $dvFormula($listSheetTitle, (string) $m['colLetter'], (int) $m['n']);
};

$maxDvRows = 5000;
$labelToListKey = [
    'Category' => 'category',
    'Calculation' => 'calculation',
    'Product' => 'product',
    'Location' => 'location',
    'Discount Type' => 'discount_type',
    'Making Type' => 'making_type',
    'Stone Charge Type' => 'stone_charge_type',
    'Tax Type' => 'tax_type',
    'Other Charge Type' => 'other_charge_type',
];
foreach ($labelToListKey as $headerLabel => $mkey) {
    $c1 = $headerToCol1($headers, $headerLabel);
    if ($c1 === null) {
        continue;
    }
    $f1 = $getFormula1ForListKey($mkey);
    if ($f1 === null) {
        continue;
    }
    $applyListDv($sheet, $c1, $f1, $maxDvRows);
}
// Karat list validation on the metal Carat column (last when two Carat columns exist).
if (!empty($labelIndices['Carat']) && !empty($listRangeMeta['carat'])) {
    $caratCols = $labelIndices['Carat'];
    $karatIdx = count($caratCols) > 1 ? $caratCols[count($caratCols) - 1] : $caratCols[0];
    $cCarKarat = (int) $karatIdx + 1;
    $f1k = $getFormula1ForListKey('carat');
    if ($f1k !== null) {
        $applyListDv($sheet, $cCarKarat, $f1k, $maxDvRows);
    }
}
foreach ($extra_field_defs_sample as $efDef) {
    if (($efDef['field_type'] ?? '') !== 'dropdown') {
        continue;
    }
    $efId = (int) ($efDef['id'] ?? 0);
    $efLabel = trim((string) ($efDef['display_name'] ?? ''));
    if ($efId <= 0 || $efLabel === '') {
        continue;
    }
    $cEf = $headerToCol1($headers, $efLabel);
    if ($cEf === null) {
        continue;
    }
    $fEf = $getFormula1ForListKey('ef_' . $efId);
    if ($fEf !== null) {
        $applyListDv($sheet, $cEf, $fEf, $maxDvRows);
    }
}

$filename = 'stock_journal_product_opening_sample';
if ($voucher === 'sale_order') {
    $filename = 'sale_order_import_sample';
    if ($metal_name_sample !== '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '_', $metal_name_sample));
        $slug = trim($slug, '_');
        if ($slug !== '') {
            $filename .= '_' . $slug;
        }
    } elseif ($metal_id_sample > 0) {
        $filename .= '_metal' . $metal_id_sample;
    }
} elseif ($product_id > 0) {
    $filename .= '_product' . $product_id;
    if ($metal_name_sample !== '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '_', $metal_name_sample));
        $slug = trim($slug, '_');
        if ($slug !== '') {
            $filename .= '_' . $slug;
        }
    } elseif ($metal_id_sample > 0) {
        $filename .= '_metal' . $metal_id_sample;
    }
}
$filename .= '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
