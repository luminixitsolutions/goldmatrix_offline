<?php

require_once __DIR__ . '/sidebar_permission_tree_data.php';

/**
 * Grant key namespace for a page (defaults to parent module key).
 */
function auragold_permission_grant_namespace_for_page(array $mod, array $page)
{
    if (!empty($page['grant_namespace'])) {
        return (string) $page['grant_namespace'];
    }
    return (string) $mod['key'];
}

/**
 * Hierarchical menu → pages → actions (view, add, update, delete).
 * Structure is sourced from sidebar.php (see sidebar_permission_tree_data.php).
 * Permission keys: {module}.menu | {namespace}.{page}.{action}
 */
function auragold_permission_tree()
{
    return auragold_sidebar_permission_tree_data();
}

/**
 * @return array<string, bool> all keys default false
 */
function auragold_permission_all_keys_flat()
{
    $keys = [];
    foreach (auragold_permission_tree() as $mod) {
        $keys[$mod['key'] . '.menu'] = false;
        foreach ($mod['pages'] as $p) {
            $ns = auragold_permission_grant_namespace_for_page($mod, $p);
            foreach ($p['actions'] as $act) {
                $keys[$ns . '.' . $p['key'] . '.' . $act] = false;
            }
        }
    }
    return $keys;
}
