<?php
/**
 * Golden outline icons for summary metal rows (Gold, Silver, Diamond, Gemstone, wallet).
 */
if (!function_exists('auragold_summary_metal_icon')) {
    function auragold_summary_metal_icon(string $kind): string
    {
        static $icons = [
            'gold' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"><path d="M5 16h5v4H5z"/><path d="M9 12h5v4H9z"/><path d="M13 8h5v4h-5z"/><path d="M17 4h4v4h-4z"/></svg>',
            'silver' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"><path d="M6 15c0-2 2-4 6-4s6 2 6 4"/><path d="M8 15v3h8v-3"/><path d="M10 11c0-1.5 1-2.5 2-2.5s2 1 2 2.5"/><path d="M7 18h10"/></svg>',
            'diamond' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 7.5L12 21 4 10.5 12 3z"/><path d="M4 10.5h16"/><path d="M8.5 7.5L12 21l3.5-13.5"/></svg>',
            'gemstone' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4l7 5.5v5c0 3.5-3 6.5-7 8.5-4-2-7-5-7-8.5v-5L12 4z"/><path d="M5 9.5h14"/><path d="M12 4v17.5"/></svg>',
            'wallet' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="12" rx="2"/><path d="M3 10h18"/><path d="M16 14h3"/><circle cx="17.5" cy="14" r="1"/></svg>',
            'amount' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><path d="M12 8v8"/><path d="M9.5 10.5c0-1 1-1.5 2.5-1.5s2.5.5 2.5 1.5-1 1.5-2.5 1.5-2.5.5-2.5 1.5 1 1.5 2.5 1.5 2.5-.5 2.5-1.5"/></svg>',
        ];

        $key = strtolower(trim($kind));
        if (!isset($icons[$key])) {
            return '';
        }

        return '<span class="siw-metal-icon siw-metal-icon--' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true">' . $icons[$key] . '</span>';
    }
}
