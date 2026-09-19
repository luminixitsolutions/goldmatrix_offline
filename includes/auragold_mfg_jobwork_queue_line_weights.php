<?php
/**
 * Jobwork line weights — manufacturing cards, queue modal, queue table, save.
 *
 * Display / carrying total (what the user sees on the card and in Total Wt):
 * - If final_weight > 0: use final_weight as-is (user-entered saved display total; do not add diamond again).
 * - Else: fallback = original_metal − saved_loss + diamond (legacy rows without explicit final).
 *
 * original_metal = net_weight if set > 0, else gross_weight.
 * On Jobwork Queue transfer save, persist total_wt input as final_weight, loss and diamond as entered.
 */

if (!function_exists('auragold_mfg_jobwork_line_saved_loss_grams')) {
    /**
     * @param array<string,mixed> $r One tbl_jobwork_order_items row
     * @param array<string,bool> $ji_cols Column existence map from SHOW COLUMNS
     */
    function auragold_mfg_jobwork_line_saved_loss_grams(array $r, array $ji_cols): float
    {
        if (!empty($ji_cols['gold_loss_1']) && !empty($ji_cols['loss_wt'])) {
            $g = (float) ($r['gold_loss_1'] ?? 0);
            if (abs($g) > 0.0000001) {
                return round($g, 4);
            }

            return round((float) ($r['loss_wt'] ?? 0), 4);
        }
        if (!empty($ji_cols['gold_loss_1'])) {
            return round((float) ($r['gold_loss_1'] ?? 0), 4);
        }
        if (!empty($ji_cols['loss_wt'])) {
            return round((float) ($r['loss_wt'] ?? 0), 4);
        }

        return 0.0;
    }
}

if (!function_exists('auragold_mfg_jobwork_line_diamond_grams')) {
    /**
     * @param array<string,mixed> $r
     * @param array<string,bool> $ji_cols
     */
    function auragold_mfg_jobwork_line_diamond_grams(array $r, array $ji_cols): float
    {
        if (!empty($ji_cols['diamond_weight']) && !empty($ji_cols['diamond_wt'])) {
            $dw = $r['diamond_weight'] ?? null;
            if ($dw !== null && $dw !== '' && abs((float) $dw) > 0.0000001) {
                return round(max(0.0, (float) $dw), 4);
            }

            return round(max(0.0, (float) ($r['diamond_wt'] ?? 0)), 4);
        }
        if (!empty($ji_cols['diamond_weight'])) {
            return round(max(0.0, (float) ($r['diamond_weight'] ?? 0)), 4);
        }
        if (!empty($ji_cols['diamond_wt'])) {
            return round(max(0.0, (float) ($r['diamond_wt'] ?? 0)), 4);
        }

        return 0.0;
    }
}

if (!function_exists('auragold_mfg_jobwork_line_original_metal_grams')) {
    /**
     * @param array<string,mixed> $r
     * @param array<string,bool> $ji_cols
     */
    function auragold_mfg_jobwork_line_original_metal_grams(array $r, array $ji_cols): float
    {
        $n = 0.0;
        if (!empty($ji_cols['net_weight'])) {
            $n = (float) ($r['net_weight'] ?? 0);
        }
        if ($n > 0.0000001) {
            return round($n, 4);
        }
        $g = 0.0;
        if (!empty($ji_cols['gross_weight'])) {
            $g = (float) ($r['gross_weight'] ?? 0);
        }
        if ($g > 0.0000001) {
            return round($g, 4);
        }

        return 0.0;
    }
}

if (!function_exists('auragold_mfg_jobwork_line_fallback_total_metal_loss_diamond')) {
    /**
     * Legacy display when final_weight is not set: metal − loss + diamond (each from row once).
     *
     * @param array<string,mixed> $r
     * @param array<string,bool> $ji_cols
     */
    function auragold_mfg_jobwork_line_fallback_total_metal_loss_diamond(array $r, array $ji_cols): float
    {
        $metal = auragold_mfg_jobwork_line_original_metal_grams($r, $ji_cols);
        $lo = auragold_mfg_jobwork_line_saved_loss_grams($r, $ji_cols);
        if (!is_finite($lo) || $lo < 0) {
            $lo = 0.0;
        }
        $d = auragold_mfg_jobwork_line_diamond_grams($r, $ji_cols);

        return round(max(0.0, $metal - $lo + $d), 4);
    }
}

if (!function_exists('auragold_mfg_jobwork_line_display_wt')) {
    /**
     * Display total from scalar fields: final_weight wins when > 0; else metal − loss + diamond.
     *
     * @param float|string|null $f final_weight
     * @param float|string|null $n net_weight
     * @param float|string|null $g gross_weight
     * @param float|string|null $d diamond grams (already resolved to one column)
     * @param float|string|null $lossSaved loss grams (gold_loss_1 / loss_wt resolved)
     */
    function auragold_mfg_jobwork_line_display_wt($f, $n, $g, $d, $lossSaved): float
    {
        $fv = (float) $f;
        if (is_finite($fv) && $fv > 0.0000001) {
            return round($fv, 4);
        }
        $lo = ($lossSaved !== null && $lossSaved !== '') ? (float) $lossSaved : 0.0;
        if (!is_finite($lo) || $lo < 0) {
            $lo = 0.0;
        }
        $dv = (float) $d;
        if (!is_finite($dv) || $dv < 0) {
            $dv = 0.0;
        }
        $nv = (float) $n;
        $gv = (float) $g;
        $metal = 0.0;
        if ($nv > 0.0000001) {
            $metal = $nv;
        } elseif ($gv > 0.0000001) {
            $metal = $gv;
        }

        return round(max(0.0, $metal - $lo + $dv), 4);
    }
}

if (!function_exists('auragold_mfg_jobwork_line_calculated_total_wt')) {
    /**
     * One row’s display total for cards / modal JSON: final_weight first, else fallback formula.
     *
     * @param array<string,mixed> $r
     * @param array<string,bool> $ji_cols
     */
    function auragold_mfg_jobwork_line_calculated_total_wt(array $r, array $ji_cols): float
    {
        $f = 0.0;
        if (!empty($ji_cols['final_weight'])) {
            $f = (float) ($r['final_weight'] ?? 0);
        }
        $fallback = auragold_mfg_jobwork_line_fallback_total_metal_loss_diamond($r, $ji_cols);
        if ($f > 0.0000001) {
            /* Saved final_weight can lag after diamond issues; use formula when it yields a higher carrying total. */
            if ($fallback > $f + 0.0001) {
                return $fallback;
            }

            return round($f, 4);
        }

        return $fallback;
    }
}

if (!function_exists('auragold_mfg_jobwork_line_metal_after_loss_grams')) {
    /**
     * @param array<string,mixed> $r
     * @param array<string,bool> $ji_cols
     */
    function auragold_mfg_jobwork_line_metal_after_loss_grams(array $r, array $ji_cols): float
    {
        $metal = auragold_mfg_jobwork_line_original_metal_grams($r, $ji_cols);
        $lo = auragold_mfg_jobwork_line_saved_loss_grams($r, $ji_cols);
        if (!is_finite($lo) || $lo < 0) {
            $lo = 0.0;
        }

        return round(max(0.0, $metal - $lo), 4);
    }
}

if (!function_exists('auragold_mfg_jobwork_line_queue_display_loss')) {
    /**
     * Loss grams for Jobwork Queue modal JSON: saved columns only.
     *
     * @param array<string,mixed> $r
     * @param array<string,bool> $ji_cols
     */
    function auragold_mfg_jobwork_line_queue_display_loss(array $r, array $ji_cols, float $displayTotal): float
    {
        unset($displayTotal);

        return auragold_mfg_jobwork_line_saved_loss_grams($r, $ji_cols);
    }
}
