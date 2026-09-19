<?php
declare(strict_types=1);

/**
 * Build detailed e-Way Bill inventory from official OpenAPI and append/update docs.
 */
$ewayPath = 'C:/laragon/www/goldmatrix/tmp_wb_eway.json';
$docsPath = 'C:/laragon/www/goldmatrix/docs/whitebooks-gst-api-integration.md';

// Refresh OpenAPI from official source
$ch = curl_init('https://whitebooks.in/openapi/eway.json');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);
$data = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($data === false || $code !== 200) {
    fwrite(STDERR, "Failed to download eway OpenAPI http={$code}\n");
    if (!is_file($ewayPath)) {
        exit(1);
    }
    echo "Using cached {$ewayPath}\n";
} else {
    file_put_contents($ewayPath, $data);
    echo "Downloaded eway OpenAPI bytes=" . strlen($data) . "\n";
}

$spec = json_decode((string) file_get_contents($ewayPath), true);
if (!is_array($spec)) {
    fwrite(STDERR, "Bad eway OpenAPI JSON\n");
    exit(1);
}

function resolve_ref(array $spec, string $ref): ?array
{
    if (!str_starts_with($ref, '#/')) {
        return null;
    }
    $parts = explode('/', substr($ref, 2));
    $cur = $spec;
    foreach ($parts as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) {
            return null;
        }
        $cur = $cur[$p];
    }
    return is_array($cur) ? $cur : null;
}

function schema_summary(array $spec, $schema, int $depth = 0): string
{
    if (!is_array($schema) || $depth > 4) {
        return '';
    }
    if (!empty($schema['$ref'])) {
        $resolved = resolve_ref($spec, (string) $schema['$ref']);
        $name = basename((string) $schema['$ref']);
        if ($resolved) {
            return $name . ': ' . schema_summary($spec, $resolved, $depth + 1);
        }
        return $name;
    }
    if (!empty($schema['allOf']) && is_array($schema['allOf'])) {
        $parts = [];
        foreach ($schema['allOf'] as $s) {
            $parts[] = schema_summary($spec, $s, $depth + 1);
        }
        return implode(' + ', array_filter($parts));
    }
    $type = (string) ($schema['type'] ?? 'object');
    if ($type === 'array') {
        $items = schema_summary($spec, $schema['items'] ?? [], $depth + 1);
        return 'array<' . ($items !== '' ? $items : 'any') . '>';
    }
    if ($type === 'object' || isset($schema['properties'])) {
        $props = [];
        $required = array_flip(array_map('strval', $schema['required'] ?? []));
        foreach ($schema['properties'] ?? [] as $k => $p) {
            if (!is_array($p)) {
                continue;
            }
            $pt = (string) ($p['type'] ?? (!empty($p['$ref']) ? 'ref' : 'any'));
            if (!empty($p['$ref'])) {
                $pt = basename((string) $p['$ref']);
            }
            $star = isset($required[(string) $k]) ? '*' : '';
            $props[] = $k . $star . ':' . $pt;
        }
        $max = 40;
        $shown = array_slice($props, 0, $max);
        $more = count($props) > $max ? (' …+' . (count($props) - $max)) : '';
        return '{' . implode(', ', $shown) . $more . '}';
    }
    $fmt = isset($schema['format']) ? ('/' . $schema['format']) : '';
    $enum = '';
    if (!empty($schema['enum']) && is_array($schema['enum'])) {
        $enum = ' enum=' . implode('|', array_slice(array_map('strval', $schema['enum']), 0, 12));
    }
    return $type . $fmt . $enum;
}

function example_from_schema(array $spec, $schema, int $depth = 0)
{
    if (!is_array($schema) || $depth > 5) {
        return null;
    }
    if (array_key_exists('example', $schema)) {
        return $schema['example'];
    }
    if (!empty($schema['$ref'])) {
        $resolved = resolve_ref($spec, (string) $schema['$ref']);
        return $resolved ? example_from_schema($spec, $resolved, $depth + 1) : null;
    }
    if (!empty($schema['allOf'][0])) {
        return example_from_schema($spec, $schema['allOf'][0], $depth + 1);
    }
    $type = (string) ($schema['type'] ?? '');
    if ($type === 'object' || isset($schema['properties'])) {
        $out = [];
        foreach ($schema['properties'] ?? [] as $k => $p) {
            if (!is_array($p)) {
                continue;
            }
            $v = example_from_schema($spec, $p, $depth + 1);
            if ($v !== null) {
                $out[$k] = $v;
            } elseif (!empty($p['type'])) {
                $out[$k] = match ($p['type']) {
                    'integer', 'number' => 0,
                    'boolean' => false,
                    'array' => [],
                    default => '',
                };
            }
        }
        return $out;
    }
    if ($type === 'array') {
        $item = example_from_schema($spec, $schema['items'] ?? [], $depth + 1);
        return $item === null ? [] : [$item];
    }
    if (!empty($schema['enum'][0])) {
        return $schema['enum'][0];
    }
    return null;
}

function collect_validation_rules(array $spec, $schema, string $prefix = '', int $depth = 0): array
{
    $rules = [];
    if (!is_array($schema) || $depth > 4) {
        return $rules;
    }
    if (!empty($schema['$ref'])) {
        $resolved = resolve_ref($spec, (string) $schema['$ref']);
        return $resolved ? collect_validation_rules($spec, $resolved, $prefix, $depth + 1) : $rules;
    }
    foreach ($schema['required'] ?? [] as $req) {
        $rules[] = ($prefix !== '' ? $prefix . '.' : '') . $req . ' is required';
    }
    foreach ($schema['properties'] ?? [] as $k => $p) {
        if (!is_array($p)) {
            continue;
        }
        $path = ($prefix !== '' ? $prefix . '.' : '') . $k;
        if (isset($p['maxLength'])) {
            $rules[] = $path . ' maxLength=' . $p['maxLength'];
        }
        if (isset($p['minLength'])) {
            $rules[] = $path . ' minLength=' . $p['minLength'];
        }
        if (isset($p['pattern'])) {
            $rules[] = $path . ' pattern=' . $p['pattern'];
        }
        if (isset($p['maximum'])) {
            $rules[] = $path . ' maximum=' . $p['maximum'];
        }
        if (isset($p['minimum'])) {
            $rules[] = $path . ' minimum=' . $p['minimum'];
        }
        if (!empty($p['enum'])) {
            $rules[] = $path . ' enum=' . implode('|', array_map('strval', $p['enum']));
        }
        if (($p['type'] ?? '') === 'object' || !empty($p['$ref']) || !empty($p['properties'])) {
            $rules = array_merge($rules, collect_validation_rules($spec, $p, $path, $depth + 1));
        }
        if (($p['type'] ?? '') === 'array' && !empty($p['items'])) {
            $rules = array_merge($rules, collect_validation_rules($spec, $p['items'], $path . '[]', $depth + 1));
        }
    }
    return $rules;
}

function esc_md(string $s): string
{
    return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], $s);
}

function map_service(string $summary, string $path, string $method): array
{
    $blob = strtolower($summary . ' ' . $path);
    if (str_contains($blob, 'authenticate') || str_contains($path, '/authenticate')) {
        return ['EWayBillAuthenticationService', 'authenticate', 'ewaybill.authentication.manage'];
    }
    if (str_contains($blob, 'genewaybill') || (str_contains($blob, 'generate') && str_contains($blob, 'eway') && !str_contains($blob, 'consolidat') && !str_contains($blob, 'multi'))) {
        return ['EWayBillGenerationService', 'generate', 'ewaybill.generate'];
    }
    if (str_contains($blob, 'updatevehicle') || str_contains($blob, 'part-b') || str_contains($blob, 'partb') || str_contains($blob, 'vehicle number')) {
        return ['EWayBillUpdateService', 'updatePartB', 'ewaybill.update_part_b'];
    }
    if (str_contains($blob, 'updatetransporter') || str_contains($blob, 'update transporter')) {
        return ['EWayBillUpdateService', 'updateTransporter', 'ewaybill.update_transporter'];
    }
    if (str_contains($blob, 'extend') && str_contains($blob, 'valid')) {
        return ['EWayBillUpdateService', 'extendValidity', 'ewaybill.extend_validity'];
    }
    if (str_contains($blob, 'cancel')) {
        return ['EWayBillCancellationService', 'cancel', 'ewaybill.cancel'];
    }
    if (str_contains($blob, 'reject')) {
        return ['EWayBillCancellationService', 'reject', 'ewaybill.reject'];
    }
    if (str_contains($blob, 'consolidat')) {
        if (str_contains($blob, 'regen')) {
            return ['ConsolidatedEWayBillService', 'regenerate', 'ewaybill.consolidated.regenerate'];
        }
        if (str_contains($blob, 'get') || str_contains($blob, 'gettrip') || $method === 'GET') {
            return ['ConsolidatedEWayBillService', 'get', 'ewaybill.view'];
        }
        return ['ConsolidatedEWayBillService', 'generate', 'ewaybill.consolidated.create'];
    }
    if (str_contains($blob, 'multivehicle') || str_contains($blob, 'multi-vehicle') || str_contains($blob, 'multi vehicle')) {
        return ['MultiVehicleMovementService', 'manage', 'ewaybill.multi_vehicle.manage'];
    }
    if (str_contains($blob, 'closure') || str_contains($blob, 'close')) {
        return ['MultiVehicleMovementService', 'close', 'ewaybill.multi_vehicle.manage'];
    }
    if (str_contains($blob, 'gstin')) {
        return ['EWayBillMasterDataService', 'getGstinDetails', 'ewaybill.lookup.gstin'];
    }
    if (str_contains($blob, 'transin') || str_contains($blob, 'transporter detail')) {
        return ['EWayBillMasterDataService', 'getTransporterDetails', 'ewaybill.lookup.transporter'];
    }
    if (str_contains($blob, 'hsn')) {
        return ['EWayBillMasterDataService', 'getHsnDetails', 'ewaybill.lookup.hsn'];
    }
    if (str_contains($blob, 'error')) {
        return ['EWayBillMasterDataService', 'getErrorList', 'ewaybill.api_logs.view'];
    }
    return ['EWayBillQueryService', 'query', 'ewaybill.reports.view'];
}

function existing_impl_status(string $path, string $summary): string
{
    $p = strtolower($path . ' ' . $summary);
    if (str_contains($p, 'authenticate')) {
        return 'Legacy implemented (`ewaybill_authenticate` in includes/ewaybill_api_helper.php) - to be wrapped by shared WhiteBooks client later';
    }
    if (str_contains($p, 'genewaybill') || (str_contains($p, 'generate') && str_contains($p, 'eway') && !str_contains($p, 'consolidat'))) {
        return 'Legacy implemented for sale-invoice/POS (`ewaybill_generate_from_sale_invoice`, ajax/generate-ewaybill.php) - full module UI pending';
    }
    if (str_contains($p, 'getewaybill') && str_contains($p, 'consigner')) {
        return 'Partial legacy support (fetch by consigner helpers exist) - inventory pending full service wrap';
    }
    return 'Pending implementation (documented in OpenAPI only; do not invent fields)';
}

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
        $headers = [];
        $query = [];
        $pathParams = [];
        foreach ($op['parameters'] ?? [] as $p) {
            $name = (string) ($p['name'] ?? '');
            $in = (string) ($p['in'] ?? '');
            $req = !empty($p['required']);
            $desc = trim(strip_tags((string) ($p['description'] ?? '')));
            $schemaBits = '';
            if (!empty($p['schema'])) {
                $schemaBits = schema_summary($spec, $p['schema']);
            }
            $item = $name . ($req ? '*' : '') . ($schemaBits !== '' ? " ({$schemaBits})" : '') . ($desc !== '' ? " — {$desc}" : '');
            if ($in === 'header') {
                $headers[] = $item;
            } elseif ($in === 'query') {
                $query[] = $item;
            } elseif ($in === 'path') {
                $pathParams[] = $item;
            }
        }

        $bodySummary = 'No requestBody in OpenAPI';
        $bodyExample = null;
        $validation = [];
        $contentTypes = [];
        if (!empty($op['requestBody']['content']) && is_array($op['requestBody']['content'])) {
            foreach ($op['requestBody']['content'] as $ct => $c) {
                $contentTypes[] = $ct;
                $sch = $c['schema'] ?? null;
                if (is_array($sch)) {
                    $bodySummary = ($op['requestBody']['required'] ?? false ? 'required ' : 'optional ') . $ct . ' ' . schema_summary($spec, $sch);
                    $bodyExample = example_from_schema($spec, $sch);
                    $validation = collect_validation_rules($spec, $sch);
                    break;
                }
            }
        }

        $successExample = null;
        $errorExample = null;
        $responseCodes = [];
        foreach ($op['responses'] ?? [] as $rcode => $resp) {
            $responseCodes[] = (string) $rcode;
            if (!is_array($resp)) {
                continue;
            }
            foreach ($resp['content'] ?? [] as $ct => $c) {
                $ex = null;
                if (isset($c['example'])) {
                    $ex = $c['example'];
                } elseif (isset($c['examples']) && is_array($c['examples'])) {
                    $first = reset($c['examples']);
                    $ex = is_array($first) ? ($first['value'] ?? null) : null;
                } elseif (!empty($c['schema'])) {
                    $ex = example_from_schema($spec, $c['schema']);
                }
                if ($ex === null) {
                    continue;
                }
                if ((string) $rcode === '200' || (string) $rcode === '201') {
                    $successExample = $ex;
                } else {
                    $errorExample = $ex;
                }
            }
        }

        $summary = (string) ($op['summary'] ?? '');
        $opId = (string) ($op['operationId'] ?? '');
        $desc = trim(strip_tags((string) ($op['description'] ?? '')));
        $tag = (string) (($op['tags'][0] ?? 'e-Way Bill'));
        [$svc, $methodName, $perm] = map_service($summary !== '' ? $summary : $opId, (string) $path, strtoupper((string) $method));

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $opId !== '' ? $opId : ($summary !== '' ? $summary : $path)));
        $slug = trim((string) $slug, '-');
        $route = 'ajax/whitebooks/ewaybill/' . $slug . '.php';
        $controller = 'EWayBill/' . $svc . 'Controller (planned thin ajax wrapper)';

        // Encryption notes from description / known NIC+WhiteBooks pattern
        $encBlob = strtolower($desc . ' ' . $summary . ' ' . implode(' ', $headers));
        $encRules = 'Not separately declared as request-body encryption in this OpenAPI operation.';
        if (preg_match('/encrypt|decrypt|sek|aes|rek/', $encBlob)) {
            $encRules = 'OpenAPI/description mentions encryption-related terms - follow exact WhiteBooks/NIC rules from portal docs when implementing; do not invent algorithms.';
        }
        if (str_contains(strtolower(implode(' ', $headers)), 'authtoken') || str_contains(strtolower($path), 'authenticate')) {
            $encRules .= ' Auth uses `authtoken` (and related session fields such as SEK when returned by authenticate) per WhiteBooks e-Way headers; store encrypted at rest in app.';
        }

        $authReq = 'GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation';
        if (str_contains(strtolower($path), 'authenticate')) {
            $authReq = 'Pre-auth: email/username/password/GSTIN + client_id/client_secret/ip_address (exact header/query names per OpenAPI)';
        } else {
            $authReq .= '; requires prior Authentication token (`authtoken` header) when listed';
        }

        $ops[] = [
            'tag' => $tag,
            'name' => $summary !== '' ? $summary : ($opId !== '' ? $opId : strtoupper($method) . ' ' . $path),
            'operationId' => $opId,
            'path' => (string) $path,
            'method' => strtoupper((string) $method),
            'description' => substr($desc, 0, 500),
            'headers' => $headers,
            'query' => $query,
            'path_params' => $pathParams,
            'body_summary' => $bodySummary,
            'body_example' => $bodyExample,
            'validation' => array_slice($validation, 0, 60),
            'encryption' => $encRules,
            'success_example' => $successExample,
            'error_example' => $errorExample,
            'response_codes' => $responseCodes,
            'auth' => $authReq,
            'service' => $svc . '::' . $methodName . '()',
            'controller' => $controller,
            'route' => $route,
            'permission' => $perm,
            'status' => existing_impl_status((string) $path, $summary),
        ];
    }
}

usort($ops, static function ($a, $b) {
    return [$a['tag'], $a['path'], $a['method']] <=> [$b['tag'], $b['path'], $b['method']];
});

file_put_contents(
    'C:/laragon/www/goldmatrix/tmp_wb_eway_detailed.json',
    json_encode([
        'fetched_at' => gmdate('c'),
        'source' => 'https://whitebooks.in/openapi/eway.json',
        'portal' => 'https://developer.whitebooks.in/ewaybillapis',
        'info' => $spec['info'] ?? [],
        'servers' => $spec['servers'] ?? [],
        'securitySchemes' => $spec['components']['securitySchemes'] ?? [],
        'op_count' => count($ops),
        'operations' => $ops,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

// Build markdown section
$md = [];
$md[] = '';
$md[] = '---';
$md[] = '';
$md[] = '## 11. e-Way Bill API — Phase 1 inventory (official OpenAPI)';
$md[] = '';
$md[] = '> **Updated:** ' . gmdate('Y-m-d H:i:s') . ' UTC';
$md[] = '>';
$md[] = '> **Authoritative source:** https://whitebooks.in/openapi/eway.json (E-WAYBILL-API)';
$md[] = '>';
$md[] = '> **Portal:** https://developer.whitebooks.in/ewaybillapis (login-gated HTML in this environment; inventory built from the published OpenAPI contract, which lists the same operation set as the portal tags).';
$md[] = '>';
$md[] = '> **Architecture note:** Shared `includes/WhiteBooks/` HTTP client / GST session tables are **planned** (GST Phase 1 doc only). Current runtime e-Way code remains in `includes/ewaybill_api_helper.php` + `tbl_ewaybill_api_*`. New e-Way services must **reuse** the shared client once created — do not fork a second disconnected stack. Do **not** break sale-invoice / POS generate flows.';
$md[] = '';
$md[] = '### 11.1 Existing project + GST architecture reuse audit';
$md[] = '';
$md[] = '| Item | Current state | Reuse plan |';
$md[] = '|------|---------------|------------|';
$md[] = '| `includes/WhiteBooks/*` shared client | **Not created yet** (GST Phase 1 inventory only) | Create once; e-Way + GST services both depend on it |';
$md[] = '| Legacy e-Way HTTP/auth | `includes/ewaybill_api_helper.php`, `config/ewaybill_config.php` | Wrap behind `EWayBillAuthenticationService` / generation services; keep endpoints exact |';
$md[] = '| Credentials storage | `tbl_ewaybill_api_settings`, file defaults, optional `*.local.php`, env `WHITEBOOKS_*` | Migrate secrets to encrypted credential store shared with GST; **production disabled until configured** |';
$md[] = '| Tokens | `tbl_ewaybill_api_tokens` (`auth_token`, `sek`, expiry) per GSTIN/username | Keep GSTIN-wise rows; encrypt at rest when shared crypto helper lands |';
$md[] = '| Request logs | `tbl_ewaybill_api_logs`, `tbl_ewaybill_generate_logs`, `logs/eway_*.log` | Continue + also write masked rows to shared `tbl_gst_api_requests` when that table exists |';
$md[] = '| Sale invoice UX | Checkbox generate on save + regenerate AJAX | Keep; later call generation service instead of duplicating curl |';
$md[] = '| Queues | None | Same future job runner as GST (no auto-retry of irreversible ops) |';
$md[] = '| Permissions today | `ewaybill_api_settings`, `ewaybill_authentication` | Extend with `ewaybill.*` keys listed below (Phase 7) |';
$md[] = '';
$md[] = '### 11.2 Servers (from OpenAPI)';
$md[] = '';
foreach ($spec['servers'] ?? [] as $srv) {
    $md[] = '- **' . esc_md((string) ($srv['description'] ?? 'server')) . ':** `' . esc_md((string) ($srv['url'] ?? '')) . '`';
}
if (empty($spec['servers'])) {
    $md[] = '- Sandbox (existing app default pattern): `https://apisandbox.whitebooks.in/ewaybillapi/v1.03/...`';
    $md[] = '- Production: `https://api.whitebooks.in/ewaybillapi/v1.03/...` (enable only when explicitly configured)';
}
$md[] = '';
$md[] = 'OpenAPI `info`: **' . esc_md((string) ($spec['info']['title'] ?? 'E-WAYBILL-API')) . '** version **' . esc_md((string) ($spec['info']['version'] ?? '')) . '**.';
$md[] = '';
$md[] = '**Operation count in official spec: ' . count($ops) . '**';
$md[] = '';
$md[] = '### 11.3 Planned modular layout (under existing framework)';
$md[] = '';
$md[] = '```text';
$md[] = 'includes/WhiteBooks/                 # shared (GST + e-Way)';
$md[] = '  Http/WhiteBooksHttpClient.php';
$md[] = '  Support/ (masking, idempotency, response normalizer)';
$md[] = 'includes/WhiteBooks/EWayBill/';
$md[] = '  DTOs/';
$md[] = '  Services/';
$md[] = '    EWayBillAuthenticationService.php';
$md[] = '    EWayBillGenerationService.php';
$md[] = '    EWayBillUpdateService.php';
$md[] = '    EWayBillCancellationService.php';
$md[] = '    EWayBillQueryService.php';
$md[] = '    ConsolidatedEWayBillService.php';
$md[] = '    MultiVehicleMovementService.php';
$md[] = '    EWayBillMasterDataService.php';
$md[] = '  Support/ (validators, mappers from sale invoice)';
$md[] = 'ajax/whitebooks/ewaybill/*.php       # thin JSON endpoints';
$md[] = 'sql/whitebooks_eway_*.sql            # tbl_* schema scripts';
$md[] = '```';
$md[] = '';
$md[] = '### 11.4 Planned / existing database mapping';
$md[] = '';
$md[] = '| Planned domain table | Notes vs existing schema |';
$md[] = '|----------------------|--------------------------|';
$md[] = '| Credentials / sessions / generic API requests | Prefer shared `tbl_gst_api_*` once created; until then keep `tbl_ewaybill_api_settings|tokens|logs` |';
$md[] = '| `tbl_eway_bills` (or extend `tbl_sale_invoices` eway_* columns) | Sale invoices already store `eway_bill_no`, dates, status — full document store table planned for standalone generate UI |';
$md[] = '| `tbl_eway_bill_items` | New when standalone generate stores line items |';
$md[] = '| `tbl_eway_bill_vehicle_history` | New for Part-B history |';
$md[] = '| `tbl_consolidated_eway_bills` + items | New |';
$md[] = '| `tbl_eway_bill_actions` | New action audit; can mirror masked payloads like `tbl_ewaybill_api_logs` |';
$md[] = '| `tbl_eway_bill_multi_vehicle_movements` | New |';
$md[] = '';
$md[] = '**No new tables created in this Phase 1 inventory step.**';
$md[] = '';
$md[] = '### 11.5 Authentication (exact OpenAPI operations)';
$md[] = '';
$md[] = 'See the Authentication row(s) in the operation tables below. Existing helper `ewaybill_authenticate()` already calls WhiteBooks authenticate and stores token/SEK. New work must use the **same endpoint/headers as OpenAPI**, keep sandbox default, and never expose tokens to JS.';
$md[] = '';
$md[] = '### 11.6 Complete operation inventory';
$md[] = '';
$md[] = 'Request/response JSON examples below are taken from OpenAPI `example` / schema examples when present. If OpenAPI omits examples, the field shows `Not provided in OpenAPI` — **do not invent payloads**.';
$md[] = '';

foreach ($ops as $i => $op) {
    $n = $i + 1;
    $md[] = '#### 11.6.' . $n . ' ' . esc_md($op['name']);
    $md[] = '';
    $md[] = '| Field | Value |';
    $md[] = '|-------|-------|';
    $md[] = '| Operation name | ' . esc_md($op['name']) . ' |';
    $md[] = '| Portal / OpenAPI tag | ' . esc_md($op['tag']) . ' |';
    $md[] = '| operationId | `' . esc_md($op['operationId'] !== '' ? $op['operationId'] : '-') . '` |';
    $md[] = '| WhiteBooks endpoint | `' . esc_md($op['path']) . '` |';
    $md[] = '| HTTP method | `' . esc_md($op['method']) . '` |';
    $md[] = '| Required authentication | ' . esc_md($op['auth']) . ' |';
    $md[] = '| Required headers | ' . esc_md($op['headers'] === [] ? '-' : implode('; ', $op['headers'])) . ' |';
    $md[] = '| Query parameters | ' . esc_md($op['query'] === [] ? '-' : implode('; ', $op['query'])) . ' |';
    $md[] = '| Path parameters | ' . esc_md($op['path_params'] === [] ? '-' : implode('; ', $op['path_params'])) . ' |';
    $md[] = '| Request body | ' . esc_md($op['body_summary']) . ' |';
    $md[] = '| Field validation rules | ' . esc_md($op['validation'] === [] ? 'See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output' : implode('; ', $op['validation'])) . ' |';
    $md[] = '| Encryption/decryption rules | ' . esc_md($op['encryption']) . ' |';
    $md[] = '| Response HTTP codes in OpenAPI | ' . esc_md(implode(', ', $op['response_codes'] ?: ['-'])) . ' |';
    $md[] = '| Application service | `' . esc_md($op['service']) . '` |';
    $md[] = '| Application controller | ' . esc_md($op['controller']) . ' |';
    $md[] = '| Internal route | `' . esc_md($op['route']) . '` |';
    $md[] = '| Permission | `' . esc_md($op['permission']) . '` |';
    $md[] = '| Implementation status | ' . esc_md($op['status']) . ' |';
    $md[] = '';
    if ($op['description'] !== '') {
        $md[] = 'Description (from OpenAPI): ' . esc_md($op['description']);
        $md[] = '';
    }
    $md[] = '**Request example**';
    $md[] = '';
    $md[] = '```json';
    if ($op['body_example'] === null) {
        $md[] = 'Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).';
    } else {
        $md[] = json_encode($op['body_example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    $md[] = '```';
    $md[] = '';
    $md[] = '**Success response example**';
    $md[] = '';
    $md[] = '```json';
    if ($op['success_example'] === null) {
        $md[] = 'Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.';
    } else {
        $md[] = json_encode($op['success_example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    $md[] = '```';
    $md[] = '';
    $md[] = '**Error response example**';
    $md[] = '';
    $md[] = '```json';
    if ($op['error_example'] === null) {
        $md[] = 'Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.';
    } else {
        $md[] = json_encode($op['error_example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    $md[] = '```';
    $md[] = '';
}

$md[] = '### 11.7 Coverage checklist vs requested portal operations';
$md[] = '';
$checklist = [
    'Authentication API' => 'authenticate',
    'Generate E-Way Bill' => 'genewaybill',
    'Update PART-B / Vehicle Number' => 'vehicle',
    'Generate Consolidated E-Way Bill' => 'cewb',
    'Cancel E-Way Bill' => 'cancel',
    'Reject E-Way Bill' => 'reject',
    'Update Transporter' => 'transporter',
    'Extend Validity' => 'extend',
    'Regenerate Consolidated' => 'regen',
    'Get E-Way Bill Details' => 'getewaybill',
    'Transporter by Date' => 'transporter',
    'Transporter by State' => 'state',
    'Transporter by GSTIN' => 'gstin',
    'Transporter assigned date report' => 'assigned',
    'Get by Date' => 'bydate',
    'Rejected by Others' => 'reject',
    'By Parties' => 'part',
    'Get Consolidated' => 'trip',
    'By Consigner' => 'consigner',
    'Get Error List' => 'error',
    'Get GSTIN Details' => 'gstindetails',
    'Get Transin Details' => 'transin',
    'Get HSN Details' => 'hsn',
    'Initiate Multi-Vehicle' => 'multi',
    'Add Multi Vehicles' => 'multi',
    'Change Multi Vehicles' => 'multi',
    'Closure' => 'clos',
];
$md[] = '| Requested capability | Matched OpenAPI operations (by path/summary contains) |';
$md[] = '|----------------------|-------------------------------------------------------|';
foreach ($checklist as $label => $needle) {
    $hits = [];
    foreach ($ops as $op) {
        $blob = strtolower($op['path'] . ' ' . $op['name'] . ' ' . $op['operationId']);
        if (str_contains($blob, strtolower($needle))) {
            $hits[] = '`' . $op['method'] . ' ' . $op['path'] . '`';
        }
    }
    $md[] = '| ' . esc_md($label) . ' | ' . esc_md($hits === [] ? 'No path matched this keyword in OpenAPI — verify on portal Expand Operations before implementing' : implode('; ', array_unique($hits))) . ' |';
}
$md[] = '';
$md[] = '### 11.8 Phase gate';
$md[] = '';
$md[] = '- Inventory completed for **' . count($ops) . '** official e-Way OpenAPI operations.';
$md[] = '- **No** new placeholder ajax endpoints, DTOs, or controllers created in this step.';
$md[] = '- **No** schema migrations applied.';
$md[] = '- Next allowed step (Phase 2 of e-Way plan): shared HTTP client (if still missing) + Authentication wrap + Generate/Get details — using **only** paths/fields from this inventory and sandbox credentials.';
$md[] = '';

$section = implode("\n", $md) . "\n";

$existing = is_file($docsPath) ? (string) file_get_contents($docsPath) : '';
$marker = '## 11. e-Way Bill API';
if (str_contains($existing, $marker)) {
    $pos = strpos($existing, '---' . "\n\n" . $marker);
    if ($pos === false) {
        $pos = strpos($existing, $marker);
        // include preceding --- if present
        if ($pos !== false && $pos > 5 && substr($existing, $pos - 5, 5) === "---\n\n") {
            $pos = $pos - 5;
        }
    }
    if ($pos !== false) {
        $existing = substr($existing, 0, $pos);
    }
    $existing = rtrim($existing) . "\n" . $section;
} else {
    $existing = rtrim($existing) . "\n" . $section;
}

file_put_contents($docsPath, $existing);
echo 'Updated ' . $docsPath . ' bytes=' . strlen($existing) . "\n";
echo 'Operations documented: ' . count($ops) . "\n";
foreach ($ops as $op) {
    echo $op['method'] . ' ' . $op['path'] . ' | ' . $op['name'] . "\n";
}
