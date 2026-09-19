<?php
/**
 * Cut, Color, Shape, Clarity, Sieve Size, and Size masters for product modal spec dropdowns.
 */
if (!function_exists('auragold_load_product_modal_spec_masters')) {
    function auragold_load_product_modal_spec_masters($conn): void
    {
        global $auragold_masters_cuts, $auragold_masters_colors, $auragold_masters_shapes;
        global $auragold_masters_clarities, $auragold_masters_sieve_sizes, $auragold_masters_sizes;

        if (isset($auragold_masters_cuts) && is_array($auragold_masters_cuts)) {
            return;
        }

        $suffix = static function (string $table) use ($conn): string {
            return function_exists('auragold_master_list_sql_suffix')
                ? auragold_master_list_sql_suffix($conn, $table)
                : '';
        };

        $auragold_masters_cuts = getList(
            'SELECT id, name FROM tbl_cut WHERE status = 1 ' . $suffix('tbl_cut') . ' ORDER BY name ASC'
        );
        $auragold_masters_colors = getList(
            'SELECT id, name FROM tbl_color WHERE status = 1 ' . $suffix('tbl_color') . ' ORDER BY name ASC'
        );
        $auragold_masters_shapes = getList(
            'SELECT id, name FROM tbl_shape WHERE status = 1 ' . $suffix('tbl_shape') . ' ORDER BY name ASC'
        );
        $auragold_masters_clarities = getList(
            'SELECT id, name FROM tbl_clarity WHERE status = 1 ' . $suffix('tbl_clarity') . ' ORDER BY name ASC'
        );
        $auragold_masters_sieve_sizes = getList(
            'SELECT id, name FROM tbl_sieve_size WHERE status = 1 ' . $suffix('tbl_sieve_size') . ' ORDER BY name ASC'
        );
        $auragold_masters_sizes = getList(
            'SELECT id, name FROM tbl_size WHERE status = 1 ' . $suffix('tbl_size') . ' ORDER BY name ASC'
        );

        if (!is_array($auragold_masters_cuts)) {
            $auragold_masters_cuts = [];
        }
        if (!is_array($auragold_masters_colors)) {
            $auragold_masters_colors = [];
        }
        if (!is_array($auragold_masters_shapes)) {
            $auragold_masters_shapes = [];
        }
        if (!is_array($auragold_masters_clarities)) {
            $auragold_masters_clarities = [];
        }
        if (!is_array($auragold_masters_sieve_sizes)) {
            $auragold_masters_sieve_sizes = [];
        }
        if (!is_array($auragold_masters_sizes)) {
            $auragold_masters_sizes = [];
        }
    }
}

if (!function_exists('auragold_echo_product_modal_spec_masters_js')) {
    function auragold_echo_product_modal_spec_masters_js(): void
    {
        global $auragold_masters_cuts, $auragold_masters_colors, $auragold_masters_shapes;
        global $auragold_masters_clarities, $auragold_masters_sieve_sizes, $auragold_masters_sizes;

        $json = static function ($data): string {
            return json_encode(is_array($data) ? $data : []);
        };
        ?>
    window.AURAGOLD_MASTERS_CUTS = <?php echo $json($auragold_masters_cuts ?? []); ?>;
    window.AURAGOLD_MASTERS_COLORS = <?php echo $json($auragold_masters_colors ?? []); ?>;
    window.AURAGOLD_MASTERS_SHAPES = <?php echo $json($auragold_masters_shapes ?? []); ?>;
    window.AURAGOLD_MASTERS_CLARITIES = <?php echo $json($auragold_masters_clarities ?? []); ?>;
    window.AURAGOLD_MASTERS_SIEVE_SIZES = <?php echo $json($auragold_masters_sieve_sizes ?? []); ?>;
    window.AURAGOLD_MASTERS_SIZES = <?php echo $json($auragold_masters_sizes ?? []); ?>;
        <?php
    }
}

if (isset($conn) && $conn) {
    auragold_load_product_modal_spec_masters($conn);
}
