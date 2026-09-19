<?php
/**
 * Catalogue DB config — standalone (does NOT use project config.php).
 * Edit these values for your local / production database.
 */
return [
    /* MySQL connection */
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_pass' => '',
    'db_name' => 'goldmatrix_gm_1',
    'db_charset' => 'utf8mb4',

    /* Public site base (for product image URLs under /uploads) */
    'site_url' => 'http://localhost/goldmatrix/',

    /* Branding */
    'app_name' => 'GoldMatrix',
    'catalogue_title' => 'Jewellery Catalogue',
    'catalogue_tagline' => 'Discover crafted pieces in gold, silver, platinum & more.',

    /* Listing */
    'per_page' => 48,
    'max_items' => 500,
];
