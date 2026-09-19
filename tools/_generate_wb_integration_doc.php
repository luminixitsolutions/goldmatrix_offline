<?php
declare(strict_types=1);

$inv = json_decode((string) file_get_contents('C:/laragon/www/goldmatrix/tmp_wb_inventory.json'), true);
if (!is_array($inv)) {
    fwrite(STDERR, "Missing tmp_wb_inventory.json — run tools/_parse_wb_openapi.php first\n");
    exit(1);
}

$gstOps = $inv['gst_ops'] ?? [];
$eiOps = $inv['einvoice_ops'] ?? [];
$ewOps = $inv['eway_ops'] ?? [];

function esc(string $s): string
{
    return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], $s);
}

function fmt_list(array $a): string
{
    return $a === [] ? '-' : esc(implode(', ', $a));
}

function service_method(string $tag, string $opId, string $summary, string $method, string $path): string
{
    if ($opId !== '') {
        $base = preg_replace('/[^a-zA-Z0-9]+/', ' ', $opId);
    } else {
        $base = $summary !== '' ? $summary : ($method . ' ' . $path);
        $base = preg_replace('/[^a-zA-Z0-9]+/', ' ', $base);
    }
    $parts = array_filter(explode(' ', (string) $base));
    $camel = '';
    foreach ($parts as $i => $p) {
        $p = strtolower($p);
        $camel .= $i === 0 ? $p : ucfirst($p);
    }
    if ($camel === '') {
        $camel = 'operation';
    }
    $svc = preg_replace('/[^a-zA-Z0-9]/', '', $tag);
    return $svc . 'Service::' . $camel . '()';
}

function app_route(string $tag, string $opId, string $summary, string $method, string $path): string
{
    $slugTag = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $tag));
    $slugOp = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $opId !== '' ? $opId : ($summary !== '' ? $summary : $path)));
    $slugOp = trim($slugOp, '-');
    return 'ajax/whitebooks/' . $slugTag . '/' . $slugOp . '.php';
}

function auth_requirement(array $op): string
{
    // Prefer explicit security; else infer from headers
    if (is_array($op['security']) && $op['security'] === []) {
        return 'None (public)';
    }
    $hdr = array_map('strtolower', $op['headers'] ?? []);
    $joined = implode(' ', $hdr);
    $needs = [];
    if (str_contains($joined, 'username') || str_contains($joined, 'auth-token') || str_contains($joined, 'authtoken') || str_contains($joined, 'auth_token')) {
        $needs[] = 'GST session (username / auth-token)';
    }
    if (str_contains($joined, 'client_id') || str_contains($joined, 'client-id') || str_contains($joined, 'client_secret')) {
        $needs[] = 'GSP client credentials';
    }
    if (str_contains($joined, 'ip_address') || str_contains($joined, 'ip-address')) {
        $needs[] = 'ip_address header';
    }
    if (str_contains($joined, 'gstin')) {
        $needs[] = 'GSTIN header';
    }
    if ($needs === []) {
        // Default for GST APIs after Public/Auth
        $tags = $op['module_tags'] ?? [];
        if (in_array('Public', $tags, true)) {
            return 'GSP client credentials (typically)';
        }
        if (in_array('Authentication', $tags, true)) {
            return 'GSP client credentials + GST username (see operation)';
        }
        return 'GSP client credentials + GST auth session (typical for taxpayer APIs)';
    }
    return implode('; ', $needs);
}

function otp_requirement(array $op): string
{
    $blob = strtolower(($op['summary'] ?? '') . ' ' . ($op['operationId'] ?? '') . ' ' . ($op['path'] ?? '') . ' ' . ($op['description'] ?? '') . ' ' . implode(' ', $op['query'] ?? []) . ' ' . implode(' ', $op['headers'] ?? []));
    if (preg_match('/\botp\b/', $blob)) {
        return 'Yes (OTP related)';
    }
    return 'No (not indicated in OpenAPI summary/params)';
}

function sync_or_async(array $op): string
{
    $blob = strtolower(($op['summary'] ?? '') . ' ' . ($op['operationId'] ?? '') . ' ' . ($op['path'] ?? '') . ' ' . ($op['description'] ?? ''));
    if (preg_match('/retstatus|getstatus|reference|refid|request.?id|track|poll/', $blob)) {
        return 'Status/poll (async follow-up)';
    }
    if (preg_match('/retsubmit|retfile|file|submit/', $blob)) {
        return 'Often async (returns reference / requires status check) - confirm per response schema';
    }
    return 'Typically synchronous JSON response - confirm per response schema';
}

$lines = [];
$lines[] = '# WhiteBooks GST API Integration - Phase 1 Audit & Inventory';
$lines[] = '';
$lines[] = '> **Status:** Phase 1 complete - inventory prepared. **Do not treat service methods / application routes as implemented**; they are planned mappings only.';
$lines[] = '>';
$lines[] = '> **Generated:** ' . gmdate('Y-m-d H:i:s') . ' UTC';
$lines[] = '>';
$lines[] = '> **Authoritative sources used:**';
$lines[] = '> - OpenAPI index: https://whitebooks.in/openapi/index.json';
$lines[] = '> - GST OpenAPI: https://whitebooks.in/openapi/gst.json (' . (int) ($inv['gst_op_count'] ?? 0) . ' operations)';
$lines[] = '> - e-Invoice OpenAPI: https://whitebooks.in/openapi/einvoice.json (' . (int) ($inv['einvoice_op_count'] ?? 0) . ' operations)';
$lines[] = '> - e-Way Bill OpenAPI: https://whitebooks.in/openapi/eway.json (' . (int) ($inv['eway_op_count'] ?? 0) . ' operations)';
$lines[] = '> - Marketing / quickstart: https://whitebooks.in/docs/gst/ and https://whitebooks.in/api/gst-api-for-developers/';
$lines[] = '>';
$lines[] = '> **Portal note:** https://developer.whitebooks.in/gstapis returned a minimal/login-gated page from this environment (no expandable operation HTML without authenticated browser session). The machine-readable OpenAPI specs above are the official published contract and match the module list shown on the WhiteBooks GST API portal tags.';
$lines[] = '';
$lines[] = '---';
$lines[] = '';
$lines[] = '## 1. Existing project audit (GoldMatrix)';
$lines[] = '';
$lines[] = '| Area | Finding |';
$lines[] = '|------|---------|';
$lines[] = '| Technology | Custom PHP + MySQLi jewellery ERP (not Laravel). Practical floor **PHP 8.1+** (PhpSpreadsheet ^5.5). |';
$lines[] = '| Entry / config | Root pages + `config.php` bootstrap; `$project = local\|prod`; DB via env `DB_HOST`, `AURAGOLD_REGISTRY_DB`, `AURAGOLD_BOOTSTRAP_USER`, `AURAGOLD_BOOTSTRAP_PASS`. |';
$lines[] = '| Front-end | jQuery, Bootstrap Material / Shreerang, DataTables, SweetAlert, Select2, ApexCharts under `assets/`. |';
$lines[] = '| Composer | `phpoffice/phpspreadsheet`, `dompdf/dompdf`, `phpmailer/phpmailer`. **No Guzzle**. |';
$lines[] = '| Auth | Session login via `index.php` -> `login_submit.php` -> `includes/login_authenticate.php`; gate `includes/auragold_require_login.php`. Roles/permissions: `tbl_users`, `tbl_roles`, `tbl_user_permission_grants`, `includes/permission_*`. |';
$lines[] = '| DB conventions | `tbl_*` tables; `$conn` working branch DB, `$conn_master` registry; helpers `getList` / `getRecord` / `esc`. Schema via `sql/*.sql` + runtime `SHOW COLUMNS` / `CREATE IF NOT EXISTS` (no Laravel migrator). |';
$lines[] = '| Existing WhiteBooks | **e-Way Bill already integrated** (`config/ewaybill_config.php`, `includes/ewaybill_api_helper.php`, `ewaybill-api-settings.php`, `tbl_ewaybill_api_settings|tokens|logs`). |';
$lines[] = '| Existing GST in app | Local calculation (`includes/auragold-gst.php`) + **local report builders** GSTR-1/3B/2B/9 (`gst-reports.php`, `includes/auragold_gst_report_data.php`) - **no GST return filing API client yet**. |';
$lines[] = '| e-Invoice / IRN | **Not implemented** in codebase. |';
$lines[] = '| HTTP client | cURL wrappers in e-way helpers; no shared HTTP abstraction. |';
$lines[] = '| Logging | `logs/eway_*.log`, `tbl_ewaybill_api_logs`, `tbl_user_activity_*`, PHP `error_log`. |';
$lines[] = '| Queues | **None** for API work (jobwork "queue" is manufacturing, not async workers). |';
$lines[] = '| sale-invoice.php | GST lines + **e-Way generate on save** (`ajax/save-sale-invoice.php`, `ajax/generate-ewaybill.php`). Natural future hook for IRN. |';
$lines[] = '';
$lines[] = '### Coding conventions to follow in later phases';
$lines[] = '';
$lines[] = '- Keep controllers/pages thin; put WhiteBooks logic under `includes/WhiteBooks/` (or `includes/whitebooks/`) as PHP classes/services - **not** one mega-controller.';
$lines[] = '- Expose JSON via `ajax/whitebooks/...` following existing ajax session + JSON response style.';
$lines[] = '- Prefer env + DB settings + optional `*.local.php` over hardcoding (improve on current e-way file defaults which still contain sandbox secrets).';
$lines[] = '- Reuse permission tree keys in `includes/sidebar_permission_tree_data.php` / `permission_definitions.php`.';
$lines[] = '- Do **not** break existing e-Way Bill paths.';
$lines[] = '';
$lines[] = '---';
$lines[] = '';
$lines[] = '## 2. WhiteBooks published API surfaces';
$lines[] = '';
$lines[] = '| Spec | Base servers (from OpenAPI) | Operation count |';
$lines[] = '|------|----------------------------|-----------------|';
$lines[] = '| GST-API | Production `https://api.whitebooks.in` / Sandbox `https://apisandbox.whitebooks.in` | ' . (int) ($inv['gst_op_count'] ?? 0) . ' |';
$lines[] = '| E-INVOICE-API | (see `einvoice` servers in OpenAPI) | ' . (int) ($inv['einvoice_op_count'] ?? 0) . ' |';
$lines[] = '| E-WAYBILL-API | (see `eway` servers in OpenAPI; already used via `.../ewaybillapi/v1.03/...`) | ' . (int) ($inv['eway_op_count'] ?? 0) . ' |';
$lines[] = '';
$lines[] = 'Marketing pages sometimes show paths under `/gst` or `/api/v1/...`. **Implementation must use the exact `paths` entries from the OpenAPI JSON** (copied into the inventory tables below), not invented aliases.';
$lines[] = '';
$lines[] = '### GST OpenAPI module tags (matches portal groups)';
$lines[] = '';
$lines[] = '| Module tag | Operations in OpenAPI |';
$lines[] = '|------------|----------------------|';
foreach ($inv['gst_by_tag'] ?? [] as $tag => $list) {
    $lines[] = '| ' . esc((string) $tag) . ' | ' . count($list) . ' |';
}
$lines[] = '';
$lines[] = '---';
$lines[] = '';
$lines[] = '## 3. Credentials, headers, authentication (from docs + existing e-Way pattern)';
$lines[] = '';
$lines[] = '### Planned environment variables (no secrets in repo)';
$lines[] = '';
$lines[] = 'Exact WhiteBooks credential **field names** must match the portal/OpenAPI header parameter names when wiring the client. Planned app env keys:';
$lines[] = '';
$lines[] = '```env';
$lines[] = 'WHITEBOOKS_ENVIRONMENT=sandbox';
$lines[] = 'WHITEBOOKS_BASE_URL=https://apisandbox.whitebooks.in';
$lines[] = 'WHITEBOOKS_CLIENT_ID=';
$lines[] = 'WHITEBOOKS_CLIENT_SECRET=';
$lines[] = 'WHITEBOOKS_EMAIL=';
$lines[] = 'WHITEBOOKS_IP_ADDRESS=';
$lines[] = 'WHITEBOOKS_TIMEOUT=60';
$lines[] = 'WHITEBOOKS_VERIFY_SSL=true';
$lines[] = 'WHITEBOOKS_RETRY_COUNT=3';
$lines[] = 'WHITEBOOKS_LOG_ENABLED=true';
$lines[] = '# Per-GSTIN taxpayer credentials stored encrypted in DB - not env:';
$lines[] = '# gst_username, encrypted password/secrets, GSTIN';
$lines[] = '```';
$lines[] = '';
$lines[] = '**Do not** put passwords, OTPs, auth tokens, SEK, or client secrets in frontend JS, Git, or unencrypted DB columns.';
$lines[] = '';
$lines[] = '### Existing e-Way auth pattern (already in app)';
$lines[] = '';
$lines[] = '- Authenticate against WhiteBooks NIC-style e-Way endpoints with headers such as `ip_address`, `client_id`, `client_secret`, username/password/GSTIN as configured.';
$lines[] = '- Persist `authtoken` / SEK-style fields in `tbl_ewaybill_api_tokens`.';
$lines[] = '- GST filing APIs under OpenAPI tag **Authentication** are separate operations (see inventory) - implement exactly those paths; do not assume e-Way authenticate URL works for GSTR.';
$lines[] = '';
$lines[] = '### Authentication operations (GST OpenAPI tag `Authentication`)';
$lines[] = '';
$authOps = $inv['gst_by_tag']['Authentication'] ?? [];
$lines[] = '| Operation | Method | Endpoint | Headers | Query/Path | Body | OTP? |';
$lines[] = '|-----------|--------|----------|---------|------------|------|------|';
foreach ($authOps as $op) {
    $lines[] = '| ' . esc($op['summary'] ?: $op['operationId']) . ' | ' . esc($op['method']) . ' | `' . esc($op['path']) . '` | ' . fmt_list($op['headers']) . ' | Q: ' . fmt_list($op['query']) . ' / P: ' . fmt_list($op['path_params']) . ' | ' . esc($op['body'] ?: '-') . ' | ' . otp_requirement($op) . ' |';
}
$lines[] = '';
$lines[] = '### Documented GST auth flow (from OpenAPI Authentication tag)';
$lines[] = '';
$lines[] = '1. **OTP request** - `GET /authentication/otprequest` (headers: `gst_username`, `state_cd`, `ip_address`, `client_id`, `client_secret`; query: `email`).';
$lines[] = '2. **Auth token** - `GET /authentication/authtoken` (adds `txn` header; query includes `otp`).';
$lines[] = '3. **Refresh** - `GET /authentication/refreshtoken`.';
$lines[] = '4. **Logout** - `GET /authentication/logout`.';
$lines[] = '5. **EVC OTP** - `GET /authentication/otpforevc` (query: `gstin`, `pan`, `form_type`).';
$lines[] = '';
$lines[] = 'Subsequent taxpayer APIs typically require GSP headers (`client_id`, `client_secret`, `ip_address`, `gst_username`, `state_cd`, `txn`) plus GSTIN / return period query params as listed per operation. Store auth tokens encrypted and scoped per GSTIN so sessions do not overwrite each other.';
$lines[] = '';
$lines[] = '---';
$lines[] = '';
$lines[] = '## 4. Cross-cutting classification (derived from OpenAPI names - refine with live sandbox)';
$lines[] = '';
$lines[] = '| Classification | How identified in this inventory |';
$lines[] = '|----------------|----------------------------------|';
$lines[] = '| OTP-related | Operation summary/path/params contain `otp` (see OTP column). |';
$lines[] = '| Needs GSTIN / username / ret_period / fy / ARN / ref id | Listed in Headers / Query / Path / Body columns when present in OpenAPI parameters. |';
$lines[] = '| Synchronous | Most GET/PUT/POST return JSON immediately per typical GSP design; **not asserted** without response schema walkthrough per op. |';
$lines[] = '| Async / poll | Ops mentioning status, reference, track, retstatus flagged in Notes. |';
$lines[] = '| Upload JSON/file | `requestBody` content types in Body column (`application/json`, multipart, etc.). |';
$lines[] = '| Download | Response content types / "download" in summary - confirm from response schema before implementing file storage. |';
$lines[] = '';
$lines[] = '---';
$lines[] = '';
$lines[] = '## 5. Planned modular architecture (adjusted to this codebase)';
$lines[] = '';
$lines[] = 'Laravel-style folders will be adapted to GoldMatrix conventions:';
$lines[] = '';
$lines[] = '```text';
$lines[] = 'includes/WhiteBooks/';
$lines[] = '  Contracts/WhiteBooksClientInterface.php';
$lines[] = '  Http/WhiteBooksHttpClient.php';
$lines[] = '  Exceptions/...';
$lines[] = '  Support/ (masking, GSTIN/period validators, response normalizer)';
$lines[] = '  Services/ (one class per OpenAPI tag)';
$lines[] = 'ajax/whitebooks/{module}/{operation}.php   # thin JSON endpoints';
$lines[] = 'sql/whitebooks_*.sql                       # schema scripts';
$lines[] = 'config/whitebooks_config.php               # non-secret defaults only';
$lines[] = '```';
$lines[] = '';
$lines[] = 'Admin UI pages and permissions are **planned for Phase 6** - not created in Phase 1.';
$lines[] = '';
$lines[] = '### Planned tables (names TBD to match `tbl_*` convention)';
$lines[] = '';
$lines[] = '- `tbl_gst_api_credentials` (encrypted secrets, GSTIN, environment)';
$lines[] = '- `tbl_gst_api_sessions` (encrypted tokens, expiry, GSTIN-scoped)';
$lines[] = '- `tbl_gst_api_requests` (masked payloads, status, reference ids)';
$lines[] = '- `tbl_gst_api_files`';
$lines[] = '- `tbl_gst_api_webhook_logs` (if webhooks confirmed in docs)';
$lines[] = '- `tbl_gst_api_audit_logs`';
$lines[] = '';
$lines[] = 'Reuse `tbl_branches.gst_no`, `tbl_users`, and existing activity logger where appropriate. Keep e-Way tables separate.';
$lines[] = '';
$lines[] = '---';
$lines[] = '';
$lines[] = '## 6. Full API inventory - GST OpenAPI';
$lines[] = '';
$lines[] = 'Columns: Module | Operation | Endpoint | Method | Headers | Parameters | Request | Response | Auth | OTP | Status | Service method | Application route';
$lines[] = '';
$lines[] = '**Request / Response examples:** OpenAPI component schemas are large; examples are deferred to per-module Phase docs once sandbox credentials are available. Body column shows declared content-type / schema ref from the official spec.';
$lines[] = '';

$tagOrder = [
    'Public', 'Authentication', 'GSTR', 'GSTR1', 'GSTR1A', 'GSTR2A', 'GSTR2B', 'GSTR2X', 'GSTR3B',
    'GSTR4', 'GSTR4A', 'GSTR4Annual', 'GSTR5', 'GSTR6', 'GSTR6A', 'GSTR7', 'GSTR8',
    'GSTR9', 'GSTR9A', 'GSTR9C', 'SPIKE', 'ALL', 'IMS', 'ITC03', 'ITC04', 'CMP',
    'Payment', 'Ledger', 'e-Invoice', 'Notices',
];

$byTag = $inv['gst_by_tag'] ?? [];
$emitted = [];
foreach ($tagOrder as $tag) {
    if (empty($byTag[$tag])) {
        $lines[] = '### Module: `' . $tag . '`';
        $lines[] = '';
        $lines[] = '> **Pending documentation:** No operations under this tag were present in the fetched OpenAPI `gst.json` at generation time. Re-fetch https://whitebooks.in/openapi/gst.json after portal updates, or expand operations inside an authenticated https://developer.whitebooks.in/gstapis session and update this inventory before implementing.';
        $lines[] = '';
        continue;
    }
    $emitted[$tag] = true;
    $lines[] = '### Module: `' . $tag . '` (' . count($byTag[$tag]) . ' operations)';
    $lines[] = '';
    $lines[] = '| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |';
    $lines[] = '|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|';
    foreach ($byTag[$tag] as $op) {
        $name = $op['summary'] !== '' ? $op['summary'] : ($op['operationId'] !== '' ? $op['operationId'] : $op['method'] . ' ' . $op['path']);
        $params = 'path: ' . fmt_list($op['path_params']) . '; query: ' . fmt_list($op['query']);
        $reqEx = $op['body'] !== '' ? ('See OpenAPI requestBody: ' . $op['body']) : 'No requestBody in OpenAPI';
        $resEx = 'HTTP ' . implode('/', $op['responses'] ?: ['-']) . ' - see OpenAPI responses for schema';
        $svc = service_method($tag, $op['operationId'], $op['summary'], $op['method'], $op['path']);
        $route = app_route($tag, $op['operationId'], $op['summary'], $op['method'], $op['path']);
        $note = sync_or_async($op);
        $lines[] = '| ' . esc($tag)
            . ' | ' . esc($name)
            . ' | `' . esc($op['path']) . '`'
            . ' | ' . esc($op['method'])
            . ' | ' . fmt_list($op['headers'])
            . ' | ' . esc($params)
            . ' | ' . esc($reqEx)
            . ' | ' . esc($resEx) . ' (' . esc($note) . ')'
            . ' | ' . esc(auth_requirement($op))
            . ' | ' . esc(otp_requirement($op))
            . ' | Pending implementation'
            . ' | `' . esc($svc) . '`'
            . ' | `' . esc($route) . '` |';
    }
    $lines[] = '';
}

foreach ($byTag as $tag => $list) {
    if (!empty($emitted[$tag])) {
        continue;
    }
    $lines[] = '### Module: `' . esc((string) $tag) . '` (extra tag in OpenAPI, ' . count($list) . ' ops)';
    $lines[] = '';
    $lines[] = '| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |';
    $lines[] = '|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|';
    foreach ($list as $op) {
        $name = $op['summary'] !== '' ? $op['summary'] : ($op['operationId'] !== '' ? $op['operationId'] : $op['method'] . ' ' . $op['path']);
        $params = 'path: ' . fmt_list($op['path_params']) . '; query: ' . fmt_list($op['query']);
        $svc = service_method((string) $tag, $op['operationId'], $op['summary'], $op['method'], $op['path']);
        $route = app_route((string) $tag, $op['operationId'], $op['summary'], $op['method'], $op['path']);
        $lines[] = '| ' . esc((string) $tag)
            . ' | ' . esc($name)
            . ' | `' . esc($op['path']) . '`'
            . ' | ' . esc($op['method'])
            . ' | ' . fmt_list($op['headers'])
            . ' | ' . esc($params)
            . ' | ' . esc($op['body'] !== '' ? ('See OpenAPI requestBody: ' . $op['body']) : 'No requestBody in OpenAPI')
            . ' | HTTP ' . esc(implode('/', $op['responses'] ?: ['-']))
            . ' | ' . esc(auth_requirement($op))
            . ' | ' . esc(otp_requirement($op))
            . ' | Pending implementation'
            . ' | `' . esc($svc) . '`'
            . ' | `' . esc($route) . '` |';
    }
    $lines[] = '';
}

$lines[] = '---';
$lines[] = '';
$lines[] = '## 7. Related published specs (separate products)';
$lines[] = '';
$lines[] = '### e-Invoice OpenAPI inventory';
$lines[] = '';
$lines[] = '| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |';
$lines[] = '|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|';
foreach ($eiOps as $op) {
    $tag = ($op['module_tags'][0] ?? 'e-Invoice');
    $name = $op['summary'] !== '' ? $op['summary'] : ($op['operationId'] !== '' ? $op['operationId'] : $op['method'] . ' ' . $op['path']);
    $params = 'path: ' . fmt_list($op['path_params']) . '; query: ' . fmt_list($op['query']);
    $svc = service_method('EInvoice', $op['operationId'], $op['summary'], $op['method'], $op['path']);
    $route = app_route('einvoice', $op['operationId'], $op['summary'], $op['method'], $op['path']);
    $lines[] = '| ' . esc((string) $tag)
        . ' | ' . esc($name)
        . ' | `' . esc($op['path']) . '`'
        . ' | ' . esc($op['method'])
        . ' | ' . fmt_list($op['headers'])
        . ' | ' . esc($params)
        . ' | ' . esc($op['body'] !== '' ? ('See OpenAPI requestBody: ' . $op['body']) : 'No requestBody in OpenAPI')
        . ' | HTTP ' . esc(implode('/', $op['responses'] ?: ['-']))
        . ' | ' . esc(auth_requirement($op))
        . ' | ' . esc(otp_requirement($op))
        . ' | Pending implementation (separate from existing e-Way)'
        . ' | `' . esc($svc) . '`'
        . ' | `' . esc($route) . '` |';
}
$lines[] = '';
$lines[] = '### e-Way Bill OpenAPI inventory (existing GoldMatrix integration covers generate/auth; expand carefully)';
$lines[] = '';
$lines[] = '| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |';
$lines[] = '|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|';
foreach ($ewOps as $op) {
    $tag = ($op['module_tags'][0] ?? 'e-Way');
    $name = $op['summary'] !== '' ? $op['summary'] : ($op['operationId'] !== '' ? $op['operationId'] : $op['method'] . ' ' . $op['path']);
    $params = 'path: ' . fmt_list($op['path_params']) . '; query: ' . fmt_list($op['query']);
    $pathLower = strtolower($op['path']);
    $status = (str_contains($pathLower, 'genewaybill') || str_contains($pathLower, 'authenticate'))
        ? 'Partially implemented (see ewaybill_* helpers)'
        : 'Documented in OpenAPI - not necessarily wired in app';
    $svc = service_method('Eway', $op['operationId'], $op['summary'], $op['method'], $op['path']);
    $route = 'existing e-way pages / ajax OR future `ajax/whitebooks/eway/...`';
    $lines[] = '| ' . esc((string) $tag)
        . ' | ' . esc($name)
        . ' | `' . esc($op['path']) . '`'
        . ' | ' . esc($op['method'])
        . ' | ' . fmt_list($op['headers'])
        . ' | ' . esc($params)
        . ' | ' . esc($op['body'] !== '' ? ('See OpenAPI requestBody: ' . $op['body']) : 'No requestBody in OpenAPI')
        . ' | HTTP ' . esc(implode('/', $op['responses'] ?: ['-']))
        . ' | ' . esc(auth_requirement($op))
        . ' | ' . esc(otp_requirement($op))
        . ' | ' . esc($status)
        . ' | `' . esc($svc) . '`'
        . ' | ' . esc($route) . ' |';
}
$lines[] = '';
$lines[] = '---';
$lines[] = '';
$lines[] = '## 8. Phase gate - what is intentionally NOT done yet';
$lines[] = '';
$lines[] = '- No new service classes, controllers, routes, or placeholder stubs for the 281 GST operations.';
$lines[] = '- No database tables created.';
$lines[] = '- No admin UI menu.';
$lines[] = '- No hardcoded API keys.';
$lines[] = '- Request/response **payload examples** will be filled from sandbox captures and OpenAPI component schemas in Phase 2+ module docs.';
$lines[] = '- Authenticated walkthrough of https://developer.whitebooks.in/gstapis "Expand Operations" should be done with your WhiteBooks developer login to cross-check any portal-only notes not present in OpenAPI.';
$lines[] = '';
$lines[] = '## 9. Recommended next step (Phase 2 kickoff)';
$lines[] = '';
$lines[] = '1. Create WhiteBooks developer sandbox credentials (client_id / client_secret) - store only via env/DB encryption.';
$lines[] = '2. Implement `WhiteBooksHttpClient` + Authentication service **only**, against OpenAPI Authentication + Public tags.';
$lines[] = '3. Add `tbl_gst_api_*` schema scripts matching GoldMatrix conventions.';
$lines[] = '4. Then implement modules in the order you specified (Public -> GSTR -> GSTR1 -> ...).';
$lines[] = '';
$lines[] = '## 10. Local artifacts used to build this inventory';
$lines[] = '';
$lines[] = '| File | Purpose |';
$lines[] = '|------|---------|';
$lines[] = '| `tools/_download_wb_openapi.php` | Downloads official OpenAPI JSON |';
$lines[] = '| `tools/_parse_wb_openapi.php` | Parses specs to `tmp_wb_inventory.json` |';
$lines[] = '| `tools/_generate_wb_integration_doc.php` | Generates this markdown |';
$lines[] = '| `tmp_wb_gst.json` / `tmp_wb_einvoice.json` / `tmp_wb_eway.json` | Cached official specs (public; no app secrets) |';
$lines[] = '| `tmp_wb_inventory.json` | Intermediate machine inventory |';
$lines[] = '';

$dir = 'C:/laragon/www/goldmatrix/docs';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$outPath = $dir . '/whitebooks-gst-api-integration.md';
file_put_contents($outPath, implode("\n", $lines) . "\n");
echo 'Wrote ' . $outPath . ' bytes=' . filesize($outPath) . PHP_EOL;
echo 'GST rows approx: ' . count($gstOps) . PHP_EOL;
