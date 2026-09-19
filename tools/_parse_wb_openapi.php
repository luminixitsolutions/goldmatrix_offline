<?php
declare(strict_types=1);

function load_spec(string $path): array
{
    $j = json_decode((string) file_get_contents($path), true);
    if (!is_array($j)) {
        throw new RuntimeException('Bad JSON: ' . $path);
    }
    return $j;
}

function summarize_params(array $op): array
{
    $headers = [];
    $query = [];
    $pathParams = [];
    foreach ($op['parameters'] ?? [] as $p) {
        $name = (string) ($p['name'] ?? '');
        $in = (string) ($p['in'] ?? '');
        $item = $name . (!empty($p['required']) ? '*' : '');
        if ($in === 'header') {
            $headers[] = $item;
        } elseif ($in === 'query') {
            $query[] = $item;
        } elseif ($in === 'path') {
            $pathParams[] = $item;
        }
    }
    $body = '';
    if (!empty($op['requestBody'])) {
        $rb = $op['requestBody'];
        $reqBody = !empty($rb['required']);
        $ctypes = array_keys($rb['content'] ?? []);
        $body = ($reqBody ? 'required ' : 'optional ') . implode('|', $ctypes);
        foreach ($rb['content'] ?? [] as $c) {
            if (!empty($c['schema']['$ref'])) {
                $body .= ' ref=' . basename((string) $c['schema']['$ref']);
                break;
            }
            if (!empty($c['schema']['type'])) {
                $body .= ' type=' . $c['schema']['type'];
                break;
            }
        }
    }
    $responses = array_map('strval', array_keys($op['responses'] ?? []));
    return [
        'headers' => $headers,
        'query' => $query,
        'path_params' => $pathParams,
        'body' => $body,
        'responses' => $responses,
        'security' => $op['security'] ?? null,
    ];
}

function extract_ops(array $spec): array
{
    $ops = [];
    foreach ($spec['paths'] ?? [] as $path => $methods) {
        if (!is_array($methods)) {
            continue;
        }
        foreach ($methods as $method => $op) {
            if (!is_array($op)) {
                continue;
            }
            if (!in_array(strtolower((string) $method), ['get', 'post', 'put', 'patch', 'delete'], true)) {
                continue;
            }
            $s = summarize_params($op);
            $ops[] = [
                'module_tags' => $op['tags'] ?? [],
                'operationId' => (string) ($op['operationId'] ?? ''),
                'summary' => (string) ($op['summary'] ?? ''),
                'description' => substr(trim(strip_tags((string) ($op['description'] ?? ''))), 0, 300),
                'path' => (string) $path,
                'method' => strtoupper((string) $method),
                'headers' => $s['headers'],
                'query' => $s['query'],
                'path_params' => $s['path_params'],
                'body' => $s['body'],
                'responses' => $s['responses'],
                'security' => $s['security'],
                'deprecated' => !empty($op['deprecated']),
            ];
        }
    }
    return $ops;
}

$base = 'C:/laragon/www/goldmatrix/';
$gst = load_spec($base . 'tmp_wb_gst.json');
$ei = load_spec($base . 'tmp_wb_einvoice.json');
$ew = load_spec($base . 'tmp_wb_eway.json');

$out = [
    'source' => [
        'openapi_index' => 'https://whitebooks.in/openapi/index.json',
        'gst_spec' => 'https://whitebooks.in/openapi/gst.json',
        'einvoice_spec' => 'https://whitebooks.in/openapi/einvoice.json',
        'eway_spec' => 'https://whitebooks.in/openapi/eway.json',
        'developer_portal' => 'https://developer.whitebooks.in/gstapis',
        'fetched_at' => gmdate('c'),
    ],
    'gst_info' => [
        'title' => $gst['info']['title'] ?? '',
        'version' => $gst['info']['version'] ?? '',
        'servers' => $gst['servers'] ?? [],
        'tags' => array_map(static fn($t) => $t['name'] ?? '', $gst['tags'] ?? []),
        'securitySchemes' => $gst['components']['securitySchemes'] ?? [],
        'security' => $gst['security'] ?? [],
    ],
    'einvoice_info' => [
        'title' => $ei['info']['title'] ?? '',
        'version' => $ei['info']['version'] ?? '',
        'servers' => $ei['servers'] ?? [],
        'tags' => array_map(static fn($t) => $t['name'] ?? '', $ei['tags'] ?? []),
        'securitySchemes' => array_keys($ei['components']['securitySchemes'] ?? []),
    ],
    'eway_info' => [
        'title' => $ew['info']['title'] ?? '',
        'version' => $ew['info']['version'] ?? '',
        'servers' => $ew['servers'] ?? [],
        'tags' => array_map(static fn($t) => $t['name'] ?? '', $ew['tags'] ?? []),
    ],
];

foreach (['gst' => $gst, 'einvoice' => $ei, 'eway' => $ew] as $label => $spec) {
    $ops = extract_ops($spec);
    $out[$label . '_ops'] = $ops;
    $out[$label . '_op_count'] = count($ops);
    $byTag = [];
    foreach ($ops as $o) {
        $tags = $o['module_tags'] ?: ['(untagged)'];
        foreach ($tags as $t) {
            $byTag[$t][] = $o;
        }
    }
    ksort($byTag);
    $out[$label . '_by_tag'] = $byTag;
}

file_put_contents($base . 'tmp_wb_inventory.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo 'GST ops: ' . $out['gst_op_count'] . PHP_EOL;
echo 'EINVOICE ops: ' . $out['einvoice_op_count'] . PHP_EOL;
echo 'EWAY ops: ' . $out['eway_op_count'] . PHP_EOL;
echo 'GST tags:' . PHP_EOL;
foreach ($out['gst_info']['tags'] as $t) {
    echo ' - ' . $t . PHP_EOL;
}
echo 'GST by tag:' . PHP_EOL;
foreach ($out['gst_by_tag'] as $t => $list) {
    echo '  ' . $t . ': ' . count($list) . PHP_EOL;
}
echo 'EINVOICE by tag:' . PHP_EOL;
foreach ($out['einvoice_by_tag'] as $t => $list) {
    echo '  ' . $t . ': ' . count($list) . PHP_EOL;
}
echo 'EWAY by tag:' . PHP_EOL;
foreach ($out['eway_by_tag'] as $t => $list) {
    echo '  ' . $t . ': ' . count($list) . PHP_EOL;
}
echo 'GST servers: ' . json_encode($out['gst_info']['servers']) . PHP_EOL;
echo 'GST securitySchemes keys: ' . implode(',', array_keys($out['gst_info']['securitySchemes'])) . PHP_EOL;
