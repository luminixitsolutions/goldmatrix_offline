/** Recovered 82×38 two-box sticker helpers (from conversation transcript). */

/** True when label preset is 82 mm × 38 mm sticker with two barcode boxes. */
function auragold_is_82x38_2box_sticker($preset): bool {
    $p = str_replace(' ', '', strtolower(trim((string) $preset)));
    return ($p === '82x38_2box' || $p === '82x38-2box');
}

/** Default geometry for 82×38 mm two-box sticker (mm). */
function auragold_82x38_2box_defaults(): array {
    return [
        'sticker_w'                => 82.0,
        'sticker_h'                => 38.0,
        'box_width_mm'             => 22.0,
        'box_height_mm'            => 16.0,
        'box1_left_mm'             => 2.0,
        'box1_top_mm'              => 13.0,
        'box2_left_mm'             => 58.0,
        'box2_top_mm'              => 2.0,
        'barcode_width_mm'         => 18.0,
        'barcode_height_mm'        => 7.0,
        'barcode_left_mm'          => 1.0,
        'barcode_top_mm'           => 1.0,
        'barcode_no_font_size'     => 7.0,
        'barcode_no_margin_top_mm' => 1.0,
    ];
}

/** Resolved box layout for 82×38 two-box sticker from saved design_layout JSON. */
function auragold_82x38_2box_layout(array $snapshot = []): array {
    $def = auragold_82x38_2box_defaults();
    $boxW = (float) ($snapshot['box_width_mm'] ?? $snapshot['dual_quadrant_width_mm'] ?? $def['box_width_mm']);
    $boxH = (float) ($snapshot['box_height_mm'] ?? $snapshot['dual_quadrant_height_mm'] ?? $def['box_height_mm']);
    if (isset($snapshot['box1']['width_mm'])) {
        $boxW = (float) $snapshot['box1']['width_mm'];
    } elseif (isset($snapshot['box1_width_mm'])) {
        $boxW = (float) $snapshot['box1_width_mm'];
    }
    if (isset($snapshot['box1']['height_mm'])) {
        $boxH = (float) $snapshot['box1']['height_mm'];
    } elseif (isset($snapshot['box1_height_mm'])) {
        $boxH = (float) $snapshot['box1_height_mm'];
    }
    $boxW = max(8.0, min(40.0, $boxW));
    $boxH = max(8.0, min(40.0, $boxH));
    $stickerW = (float) $def['sticker_w'];
    $stickerH = (float) $def['sticker_h'];
    $b1L = (float) ($snapshot['box1_left_mm'] ?? ($snapshot['box1']['left_mm'] ?? $def['box1_left_mm']));
    $b1T = (float) ($snapshot['box1_top_mm'] ?? ($snapshot['box1']['top_mm'] ?? $def['box1_top_mm']));
    $b2T = (float) ($snapshot['box2_top_mm'] ?? ($snapshot['box2']['top_mm'] ?? $def['box2_top_mm']));
    if (isset($snapshot['box2_left_mm']) && $snapshot['box2_left_mm'] !== '') {
        $b2L = (float) $snapshot['box2_left_mm'];
    } elseif (isset($snapshot['box2']['left_mm']) && $snapshot['box2']['left_mm'] !== '') {
        $b2L = (float) $snapshot['box2']['left_mm'];
    } elseif (isset($snapshot['box2_right_mm']) && $snapshot['box2_right_mm'] !== '') {
        $b2L = $stickerW - (float) $snapshot['box2_right_mm'] - $boxW;
    } else {
        $b2L = (float) $def['box2_left_mm'];
    }
    $b1L = max(0.0, min($stickerW - $boxW, $b1L));
    $b1T = max(0.0, min($stickerH - $boxH, $b1T));
    $b2L = max(0.0, min($stickerW - $boxW, $b2L));
    $b2T = max(0.0, min($stickerH - $boxH, $b2T));
    $sharedBarW = (float) ($snapshot['barcode_width_mm'] ?? $snapshot['box_barcode_width_mm'] ?? $def['barcode_width_mm']);
    $sharedBarH = (float) ($snapshot['barcode_height_mm'] ?? $snapshot['box_barcode_height_mm'] ?? $def['barcode_height_mm']);
    $box1BarW = (float) ($snapshot['box1_barcode_width_mm'] ?? $sharedBarW);
    $box1BarH = (float) ($snapshot['box1_barcode_height_mm'] ?? $sharedBarH);
    $box2BarW = (float) ($snapshot['box2_barcode_width_mm'] ?? $sharedBarW);
    $box2BarH = (float) ($snapshot['box2_barcode_height_mm'] ?? $sharedBarH);
    $barLeft = (float) ($snapshot['barcode_left_mm'] ?? $def['barcode_left_mm']);
    $barTop = (float) ($snapshot['barcode_top_mm'] ?? $def['barcode_top_mm']);
    $numFont = (float) ($snapshot['barcode_no_font_size'] ?? $snapshot['barcode_number_font_pt'] ?? $def['barcode_no_font_size']);
    $numMargin = (float) ($snapshot['barcode_no_margin_top_mm'] ?? $snapshot['barcode_number_gap_mm'] ?? $def['barcode_no_margin_top_mm']);
    $box1BarW = max(4.0, min($boxW, $box1BarW));
    $box1BarH = max(3.0, min($boxH - 2.0, $box1BarH));
    $box2BarW = max(4.0, min($boxW, $box2BarW));
    $box2BarH = max(3.0, min($boxH - 2.0, $box2BarH));
    $barLeft = max(0.0, min($boxW - max($box1BarW, $box2BarW), $barLeft));
    $barTop = max(0.0, min($boxH - max($box1BarH, $box2BarH) - 3.0, $barTop));
    $numFont = max(5.0, min(24.0, $numFont));
    $numMargin = max(0.0, min(10.0, $numMargin));
    return [
        'sticker_w'                  => $stickerW,
        'sticker_h'                  => $stickerH,
        'box_width_mm'               => round($boxW, 2),
        'box_height_mm'              => round($boxH, 2),
        'box1'                       => ['left' => round($b1L, 2), 'top' => round($b1T, 2)],
        'box2'                       => ['left' => round($b2L, 2), 'top' => round($b2T, 2)],
        'box2_left_mm'               => round($b2L, 2),
        'box1_barcode_width_mm'      => round($box1BarW, 2),
        'box1_barcode_height_mm'     => round($box1BarH, 2),
        'box2_barcode_width_mm'      => round($box2BarW, 2),
        'box2_barcode_height_mm'     => round($box2BarH, 2),
        'barcode_width_mm'           => round($box1BarW, 2),
        'barcode_height_mm'          => round($box1BarH, 2),
        'barcode_left_mm'            => round($barLeft, 2),
        'barcode_top_mm'             => round($barTop, 2),
        'barcode_no_font_size'       => round($numFont, 2),
        'barcode_no_margin_top_mm'   => round($numMargin, 2),
    ];
}

function auragold_82x38_sticker_css_vars(array $layout): string {
    return '--box-width:' . $layout['box_width_mm'] . 'mm;'
        . '--box-height:' . $layout['box_height_mm'] . 'mm;'
        . '--barcode-width:' . $layout['barcode_width_mm'] . 'mm;'
        . '--barcode-height:' . $layout['barcode_height_mm'] . 'mm;'
        . '--barcode-left:' . $layout['barcode_left_mm'] . 'mm;'
        . '--barcode-top:' . $layout['barcode_top_mm'] . 'mm;'
        . '--number-font:' . $layout['barcode_no_font_size'] . 'px;'
        . '--number-margin-top:' . $layout['barcode_no_margin_top_mm'] . 'mm;';
}

function auragold_82x38_box_design_items(array $snapshot, int $boxNum): array {
    if ($boxNum === 2) {
        if (isset($snapshot['box2']['items']) && is_array($snapshot['box2']['items'])) {
            return $snapshot['box2']['items'];
        }
        if (isset($snapshot['items2']) && is_array($snapshot['items2'])) {
            return $snapshot['items2'];
        }
        if (isset($snapshot['fields2']) && is_array($snapshot['fields2'])) {
            return $snapshot['fields2'];
        }
        return [];
    }
    if (isset($snapshot['box1']['items']) && is_array($snapshot['box1']['items'])) {
        return $snapshot['box1']['items'];
    }
    if (isset($snapshot['items']) && is_array($snapshot['items'])) {
        return $snapshot['items'];
    }
    if (isset($snapshot['fields']) && is_array($snapshot['fields'])) {
        return $snapshot['fields'];
    }
    return [];
}

function auragold_82x38_default_box_design_items(array $snapshot, int $boxNum = 1): array {
    $layout = auragold_82x38_2box_layout($snapshot);
    $barW = ($boxNum === 2) ? ($layout['box2_barcode_width_mm'] ?? $layout['barcode_width_mm']) : ($layout['box1_barcode_width_mm'] ?? $layout['barcode_width_mm']);
    $barH = ($boxNum === 2) ? ($layout['box2_barcode_height_mm'] ?? $layout['barcode_height_mm']) : ($layout['box1_barcode_height_mm'] ?? $layout['barcode_height_mm']);
    return [
        [
            'type'   => 'barcode_image',
            'left'   => $layout['barcode_left_mm'],
            'top'    => $layout['barcode_top_mm'],
            'width'  => $barW,
            'height' => $barH,
        ],
    ];
}

function render82x38DesignStickerLabel(array $print_item, array $settings, array $decoded_snapshot = [], int $page_index = 0): string {
    $snapshot = is_array($decoded_snapshot) ? $decoded_snapshot : [];
    $layout = auragold_82x38_2box_layout($snapshot);
    $boxW = (float) $layout['box_width_mm'];
    $boxH = (float) $layout['box_height_mm'];
    $idSuffix = ($page_index > 0) ? ('_' . (int) $page_index) : '';
    $html = '<div class="sticker-page" style="width:82mm;height:38mm;position:relative;overflow:hidden;">';
    $pairs = [
        1 => ['pos' => $layout['box1'], 'item' => $print_item['box1'] ?? null],
        2 => ['pos' => $layout['box2'], 'item' => $print_item['box2'] ?? null],
    ];
    foreach ($pairs as $num => $pair) {
        if (!is_array($pair['item']) || empty($pair['item']['barcode'])) {
            continue;
        }
        $productData = array_merge($pair['item']['row'] ?? [], ['barcode' => $pair['item']['barcode']]);
        $boxDesign = auragold_82x38_box_design_items($snapshot, (int) $num);
        if (empty($boxDesign)) {
            $boxDesign = auragold_82x38_default_box_design_items($snapshot, (int) $num);
        }
        $boxSettings = array_merge($settings, [
            'label_width_mm'    => $boxW,
            'label_height_mm'   => $boxH,
            'design_layout'     => $boxDesign,
            'barcode_svg_class' => 'barcode-svg-box' . (int) $num,
            'barcode_svg_id'    => 'barcodeSvgBox' . (int) $num . $idSuffix,
        ]);
        $inner = renderBarcodeLayout($productData, $boxSettings);
        if (strpos($inner, 'barcode-svg') === false) {
            $def = auragold_82x38_default_box_design_items($snapshot, (int) $num);
            $inner = renderBarcodeLayout($productData, array_merge($boxSettings, ['design_layout' => $def]));
        }
        $boxStyle = 'left:' . $pair['pos']['left'] . 'mm;top:' . $pair['pos']['top'] . 'mm;'
            . 'width:' . $boxW . 'mm;height:' . $boxH . 'mm;';
        $html .= '<div class="barcode-box barcode-box--' . (int) $num . '" style="' . htmlspecialchars($boxStyle, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="barcode-box-inner" style="position:relative;width:100%;height:100%;overflow:hidden;box-sizing:border-box;">';
        $html .= $inner;
        $html .= '</div></div>';
    }
    $html .= '</div>';
    return $html;
}

function render82x38DoubleStickerLabel(array $print_item, array $settings, array $decoded_snapshot = [], int $page_index = 0): string {
    return render82x38DesignStickerLabel($print_item, $settings, $decoded_snapshot, $page_index);
}
