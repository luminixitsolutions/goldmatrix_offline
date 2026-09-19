# WhiteBooks GST API Integration - Phase 1 Audit & Inventory

> **Status:** Phase 1 complete - inventory prepared. **Do not treat service methods / application routes as implemented**; they are planned mappings only.
>
> **Generated:** 2026-08-05 08:59:48 UTC
>
> **Authoritative sources used:**
> - OpenAPI index: https://whitebooks.in/openapi/index.json
> - GST OpenAPI: https://whitebooks.in/openapi/gst.json (281 operations)
> - e-Invoice OpenAPI: https://whitebooks.in/openapi/einvoice.json (11 operations)
> - e-Way Bill OpenAPI: https://whitebooks.in/openapi/eway.json (27 operations)
> - Marketing / quickstart: https://whitebooks.in/docs/gst/ and https://whitebooks.in/api/gst-api-for-developers/
>
> **Portal note:** https://developer.whitebooks.in/gstapis returned a minimal/login-gated page from this environment (no expandable operation HTML without authenticated browser session). The machine-readable OpenAPI specs above are the official published contract and match the module list shown on the WhiteBooks GST API portal tags.

---

## 1. Existing project audit (GoldMatrix)

| Area | Finding |
|------|---------|
| Technology | Custom PHP + MySQLi jewellery ERP (not Laravel). Practical floor **PHP 8.1+** (PhpSpreadsheet ^5.5). |
| Entry / config | Root pages + `config.php` bootstrap; `$project = local\|prod`; DB via env `DB_HOST`, `AURAGOLD_REGISTRY_DB`, `AURAGOLD_BOOTSTRAP_USER`, `AURAGOLD_BOOTSTRAP_PASS`. |
| Front-end | jQuery, Bootstrap Material / Shreerang, DataTables, SweetAlert, Select2, ApexCharts under `assets/`. |
| Composer | `phpoffice/phpspreadsheet`, `dompdf/dompdf`, `phpmailer/phpmailer`. **No Guzzle**. |
| Auth | Session login via `index.php` -> `login_submit.php` -> `includes/login_authenticate.php`; gate `includes/auragold_require_login.php`. Roles/permissions: `tbl_users`, `tbl_roles`, `tbl_user_permission_grants`, `includes/permission_*`. |
| DB conventions | `tbl_*` tables; `$conn` working branch DB, `$conn_master` registry; helpers `getList` / `getRecord` / `esc`. Schema via `sql/*.sql` + runtime `SHOW COLUMNS` / `CREATE IF NOT EXISTS` (no Laravel migrator). |
| Existing WhiteBooks | **e-Way Bill already integrated** (`config/ewaybill_config.php`, `includes/ewaybill_api_helper.php`, `ewaybill-api-settings.php`, `tbl_ewaybill_api_settings|tokens|logs`). |
| Existing GST in app | Local calculation (`includes/auragold-gst.php`) + **local report builders** GSTR-1/3B/2B/9 (`gst-reports.php`, `includes/auragold_gst_report_data.php`) - **no GST return filing API client yet**. |
| e-Invoice / IRN | **Not implemented** in codebase. |
| HTTP client | cURL wrappers in e-way helpers; no shared HTTP abstraction. |
| Logging | `logs/eway_*.log`, `tbl_ewaybill_api_logs`, `tbl_user_activity_*`, PHP `error_log`. |
| Queues | **None** for API work (jobwork "queue" is manufacturing, not async workers). |
| sale-invoice.php | GST lines + **e-Way generate on save** (`ajax/save-sale-invoice.php`, `ajax/generate-ewaybill.php`). Natural future hook for IRN. |

### Coding conventions to follow in later phases

- Keep controllers/pages thin; put WhiteBooks logic under `includes/WhiteBooks/` (or `includes/whitebooks/`) as PHP classes/services - **not** one mega-controller.
- Expose JSON via `ajax/whitebooks/...` following existing ajax session + JSON response style.
- Prefer env + DB settings + optional `*.local.php` over hardcoding (improve on current e-way file defaults which still contain sandbox secrets).
- Reuse permission tree keys in `includes/sidebar_permission_tree_data.php` / `permission_definitions.php`.
- Do **not** break existing e-Way Bill paths.

---

## 2. WhiteBooks published API surfaces

| Spec | Base servers (from OpenAPI) | Operation count |
|------|----------------------------|-----------------|
| GST-API | Production `https://api.whitebooks.in` / Sandbox `https://apisandbox.whitebooks.in` | 281 |
| E-INVOICE-API | (see `einvoice` servers in OpenAPI) | 11 |
| E-WAYBILL-API | (see `eway` servers in OpenAPI; already used via `.../ewaybillapi/v1.03/...`) | 27 |

Marketing pages sometimes show paths under `/gst` or `/api/v1/...`. **Implementation must use the exact `paths` entries from the OpenAPI JSON** (copied into the inventory tables below), not invented aliases.

### GST OpenAPI module tags (matches portal groups)

| Module tag | Operations in OpenAPI |
|------------|----------------------|
| ALL | 13 |
| Authentication | 5 |
| CMP | 5 |
| GSTR | 2 |
| GSTR1 | 30 |
| GSTR1A | 29 |
| GSTR2A | 12 |
| GSTR2B | 3 |
| GSTR2X | 4 |
| GSTR3B | 15 |
| GSTR4 | 23 |
| GSTR4A | 5 |
| GSTR4Annual | 9 |
| GSTR5 | 21 |
| GSTR6 | 16 |
| GSTR6A | 4 |
| GSTR7 | 6 |
| GSTR8 | 7 |
| GSTR9 | 8 |
| GSTR9A | 5 |
| GSTR9C | 7 |
| IMS | 8 |
| ITC03 | 7 |
| ITC04 | 6 |
| Ledger | 11 |
| Notices | 2 |
| Payment | 4 |
| Public | 5 |
| SPIKE | 4 |
| e-Invoice | 5 |

---

## 3. Credentials, headers, authentication (from docs + existing e-Way pattern)

### Planned environment variables (no secrets in repo)

Exact WhiteBooks credential **field names** must match the portal/OpenAPI header parameter names when wiring the client. Planned app env keys:

```env
WHITEBOOKS_ENVIRONMENT=sandbox
WHITEBOOKS_BASE_URL=https://apisandbox.whitebooks.in
WHITEBOOKS_CLIENT_ID=
WHITEBOOKS_CLIENT_SECRET=
WHITEBOOKS_EMAIL=
WHITEBOOKS_IP_ADDRESS=
WHITEBOOKS_TIMEOUT=60
WHITEBOOKS_VERIFY_SSL=true
WHITEBOOKS_RETRY_COUNT=3
WHITEBOOKS_LOG_ENABLED=true
# Per-GSTIN taxpayer credentials stored encrypted in DB - not env:
# gst_username, encrypted password/secrets, GSTIN
```

**Do not** put passwords, OTPs, auth tokens, SEK, or client secrets in frontend JS, Git, or unencrypted DB columns.

### Existing e-Way auth pattern (already in app)

- Authenticate against WhiteBooks NIC-style e-Way endpoints with headers such as `ip_address`, `client_id`, `client_secret`, username/password/GSTIN as configured.
- Persist `authtoken` / SEK-style fields in `tbl_ewaybill_api_tokens`.
- GST filing APIs under OpenAPI tag **Authentication** are separate operations (see inventory) - implement exactly those paths; do not assume e-Way authenticate URL works for GSTR.

### Authentication operations (GST OpenAPI tag `Authentication`)

| Operation | Method | Endpoint | Headers | Query/Path | Body | OTP? |
|-----------|--------|----------|---------|------------|------|------|
| Request for OTP | GET | `/authentication/otprequest` | gst_username*, state_cd*, ip_address*, client_id*, client_secret* | Q: email* / P: - | - | Yes (OTP related) |
| Request for Authorization Token | GET | `/authentication/authtoken` | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | Q: email*, otp* / P: - | - | Yes (OTP related) |
| Request for extension of authorization token | GET | `/authentication/refreshtoken` | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | Q: email* / P: - | - | No (not indicated in OpenAPI summary/params) |
| Request for logout | GET | `/authentication/logout` | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | Q: email* / P: - | - | No (not indicated in OpenAPI summary/params) |
| Initiate OTP for EVC | GET | `/authentication/otpforevc` | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | Q: email*, gstin*, pan*, form_type* / P: - | - | Yes (OTP related) |

### Documented GST auth flow (from OpenAPI Authentication tag)

1. **OTP request** - `GET /authentication/otprequest` (headers: `gst_username`, `state_cd`, `ip_address`, `client_id`, `client_secret`; query: `email`).
2. **Auth token** - `GET /authentication/authtoken` (adds `txn` header; query includes `otp`).
3. **Refresh** - `GET /authentication/refreshtoken`.
4. **Logout** - `GET /authentication/logout`.
5. **EVC OTP** - `GET /authentication/otpforevc` (query: `gstin`, `pan`, `form_type`).

Subsequent taxpayer APIs typically require GSP headers (`client_id`, `client_secret`, `ip_address`, `gst_username`, `state_cd`, `txn`) plus GSTIN / return period query params as listed per operation. Store auth tokens encrypted and scoped per GSTIN so sessions do not overwrite each other.

---

## 4. Cross-cutting classification (derived from OpenAPI names - refine with live sandbox)

| Classification | How identified in this inventory |
|----------------|----------------------------------|
| OTP-related | Operation summary/path/params contain `otp` (see OTP column). |
| Needs GSTIN / username / ret_period / fy / ARN / ref id | Listed in Headers / Query / Path / Body columns when present in OpenAPI parameters. |
| Synchronous | Most GET/PUT/POST return JSON immediately per typical GSP design; **not asserted** without response schema walkthrough per op. |
| Async / poll | Ops mentioning status, reference, track, retstatus flagged in Notes. |
| Upload JSON/file | `requestBody` content types in Body column (`application/json`, multipart, etc.). |
| Download | Response content types / "download" in summary - confirm from response schema before implementing file storage. |

---

## 5. Planned modular architecture (adjusted to this codebase)

Laravel-style folders will be adapted to GoldMatrix conventions:

```text
includes/WhiteBooks/
  Contracts/WhiteBooksClientInterface.php
  Http/WhiteBooksHttpClient.php
  Exceptions/...
  Support/ (masking, GSTIN/period validators, response normalizer)
  Services/ (one class per OpenAPI tag)
ajax/whitebooks/{module}/{operation}.php   # thin JSON endpoints
sql/whitebooks_*.sql                       # schema scripts
config/whitebooks_config.php               # non-secret defaults only
```

Admin UI pages and permissions are **planned for Phase 6** - not created in Phase 1.

### Planned tables (names TBD to match `tbl_*` convention)

- `tbl_gst_api_credentials` (encrypted secrets, GSTIN, environment)
- `tbl_gst_api_sessions` (encrypted tokens, expiry, GSTIN-scoped)
- `tbl_gst_api_requests` (masked payloads, status, reference ids)
- `tbl_gst_api_files`
- `tbl_gst_api_webhook_logs` (if webhooks confirmed in docs)
- `tbl_gst_api_audit_logs`

Reuse `tbl_branches.gst_no`, `tbl_users`, and existing activity logger where appropriate. Keep e-Way tables separate.

---

## 6. Full API inventory - GST OpenAPI

Columns: Module | Operation | Endpoint | Method | Headers | Parameters | Request | Response | Auth | OTP | Status | Service method | Application route

**Request / Response examples:** OpenAPI component schemas are large; examples are deferred to per-module Phase docs once sandbox credentials are available. Body column shows declared content-type / schema ref from the official spec.

### Module: `Public` (5 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| Public | Search Taxpayer | `/public/search` | GET | client_id*, client_secret* | path: -; query: email*, gstin* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GSP client credentials | No (not indicated in OpenAPI summary/params) | Pending implementation | `PublicService::searchtaxpayer()` | `ajax/whitebooks/public/searchtaxpayer.php` |
| Public | View and Track Returns | `/public/rettrack` | GET | client_id*, client_secret* | path: -; query: gstin*, fy*, type, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Status/poll (async follow-up)) | GSP client credentials | No (not indicated in OpenAPI summary/params) | Pending implementation | `PublicService::getviewandtrackreturns()` | `ajax/whitebooks/public/getviewandtrackreturns.php` |
| Public | Get Preference | `/public/pref` | GET | state_cd*, ip_address*, client_id*, client_secret* | path: -; query: gstin*, fy*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Status/poll (async follow-up)) | GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `PublicService::getpreference()` | `ajax/whitebooks/public/getpreference.php` |
| Public | Unregistered Applicants API | `/public/unregistered-applicants` | GET | ip_address*, client_id*, client_secret* | path: -; query: uid*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `PublicService::unregisteredapplicants()` | `ajax/whitebooks/public/unregisteredapplicants.php` |
| Public | Unregistered Applicants Validation | `/public/unregistered-applicants-validation` | GET | ip_address*, client_id*, client_secret* | path: -; query: uid*, ecomEmail, mobile, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `PublicService::unregisteredapplicantsvalidation()` | `ajax/whitebooks/public/unregisteredapplicantsvalidation.php` |

### Module: `Authentication` (5 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| Authentication | Request for OTP | `/authentication/otprequest` | GET | gst_username*, state_cd*, ip_address*, client_id*, client_secret* | path: -; query: email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | Yes (OTP related) | Pending implementation | `AuthenticationService::otprequest()` | `ajax/whitebooks/authentication/otprequest.php` |
| Authentication | Request for Authorization Token | `/authentication/authtoken` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, otp* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | Yes (OTP related) | Pending implementation | `AuthenticationService::authtoken()` | `ajax/whitebooks/authentication/authtoken.php` |
| Authentication | Request for extension of authorization token | `/authentication/refreshtoken` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `AuthenticationService::refreshtoken()` | `ajax/whitebooks/authentication/refreshtoken.php` |
| Authentication | Request for logout | `/authentication/logout` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `AuthenticationService::logout()` | `ajax/whitebooks/authentication/logout.php` |
| Authentication | Initiate OTP for EVC | `/authentication/otpforevc` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, pan*, form_type* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | Yes (OTP related) | Pending implementation | `AuthenticationService::otpforevc()` | `ajax/whitebooks/authentication/otpforevc.php` |

### Module: `GSTR` (2 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR | Get Return Status | `/gstr/retstatus` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, returnperiod*, refid*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Status/poll (async follow-up)) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTRService::getreturnstatus()` | `ajax/whitebooks/gstr/getreturnstatus.php` |
| GSTR | View and Track Returns | `/gstr/rettrack` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, returnperiod*, type, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Status/poll (async follow-up)) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTRService::getviewandtrackreturns()` | `ajax/whitebooks/gstr/getviewandtrackreturns.php` |

### Module: `GSTR1` (30 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR1 | Get GSTR1 Summary | `/gstr1/retsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email*, smrytyp | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getgstr1summary()` | `ajax/whitebooks/gstr1/getgstr1summary.php` |
| GSTR1 | Get GSTR1 Document Issued | `/gstr1/dociss` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getdocissued()` | `ajax/whitebooks/gstr1/getdocissued.php` |
| GSTR1 | Get CDNRA Invoices | `/gstr1/cdnra` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: actionrequired, gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getcdnrainvoices()` | `ajax/whitebooks/gstr1/getcdnrainvoices.php` |
| GSTR1 | Get B2CL Invoices | `/gstr1/b2cl` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: statecd, gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getb2clinvoices()` | `ajax/whitebooks/gstr1/getb2clinvoices.php` |
| GSTR1 | Get B2BA Invoices | `/gstr1/b2ba` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, actionrequired, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getb2bainvoices()` | `ajax/whitebooks/gstr1/getb2bainvoices.php` |
| GSTR1 | Get ATA | `/gstr1/ata` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getata()` | `ajax/whitebooks/gstr1/getata.php` |
| GSTR1 | Get TXP | `/gstr1/txp` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::gettxp()` | `ajax/whitebooks/gstr1/gettxp.php` |
| GSTR1 | Get Supplier ECO invoices | `/gstr1/supeco` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, subsection, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getsupplierecoinvoices()` | `ajax/whitebooks/gstr1/getsupplierecoinvoices.php` |
| GSTR1 | Get Supplier ECOA invoices | `/gstr1/supecoa` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, subsection, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getsupplierecoainvoices()` | `ajax/whitebooks/gstr1/getsupplierecoainvoices.php` |
| GSTR1 | Get ECOM Invoices | `/gstr1/ecom` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, rtin, subsection, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getecominvoices()` | `ajax/whitebooks/gstr1/getecominvoices.php` |
| GSTR1 | Get ECOMA Invoices | `/gstr1/ecoma` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, rtin, subsection, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getecomainvoices()` | `ajax/whitebooks/gstr1/getecomainvoices.php` |
| GSTR1 | Save GSTR1 data | `/gstr1/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::savegstr1()` | `ajax/whitebooks/gstr1/savegstr1.php` |
| GSTR1 | Get B2B Invoices | `/gstr1/b2b` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, actionrequired, gstin*, retperiod*, ctin, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getb2binvoices()` | `ajax/whitebooks/gstr1/getb2binvoices.php` |
| GSTR1 | Get B2CLA Invoices | `/gstr1/b2cla` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: statecd, gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getb2clainvoices()` | `ajax/whitebooks/gstr1/getb2clainvoices.php` |
| GSTR1 | Get B2CSA Invoices | `/gstr1/b2csa` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getb2csainvoices()` | `ajax/whitebooks/gstr1/getb2csainvoices.php` |
| GSTR1 | Get CDNR Invoices | `/gstr1/cdnr` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, actionrequired, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getcdnrinvoices()` | `ajax/whitebooks/gstr1/getcdnrinvoices.php` |
| GSTR1 | Get AT | `/gstr1/at` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getat()` | `ajax/whitebooks/gstr1/getat.php` |
| GSTR1 | Get EXPA | `/gstr1/expa` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getexpa()` | `ajax/whitebooks/gstr1/getexpa.php` |
| GSTR1 | Get B2CS Invoices | `/gstr1/b2cs` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getb2csinvoices()` | `ajax/whitebooks/gstr1/getb2csinvoices.php` |
| GSTR1 | Get EXP | `/gstr1/exp` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getexp()` | `ajax/whitebooks/gstr1/getexp.php` |
| GSTR1 | Get Nil Rated Supplies | `/gstr1/nil` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getnilratedsupplies()` | `ajax/whitebooks/gstr1/getnilratedsupplies.php` |
| GSTR1 | Get HSN Summary details | `/gstr1/hsnsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::gethsnsummarydetails()` | `ajax/whitebooks/gstr1/gethsnsummarydetails.php` |
| GSTR1 | Validate the HSN Summary details | `/gstr1/validatehsnsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::validatehsnsum()` | `ajax/whitebooks/gstr1/validatehsnsum.php` |
| GSTR1 | API call for getting all Credit/ Debit Notes issued to un-registered persons for a return period. | `/gstr1/cdnur` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getcdnur()` | `ajax/whitebooks/gstr1/getcdnur.php` |
| GSTR1 | API call for getting all amended Credit/ Debit Notes issued to un-registered persons for a return period. | `/gstr1/cdnura` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::getcdnura()` | `ajax/whitebooks/gstr1/getcdnura.php` |
| GSTR1 | Reset GSTR1 Data | `/gstr1/reset` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::resetgstr()` | `ajax/whitebooks/gstr1/resetgstr.php` |
| GSTR1 | File GSTR1 data | `/gstr1/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::filegstr()` | `ajax/whitebooks/gstr1/filegstr.php` |
| GSTR1 | File GSTR1 data | `/gstr1/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::filegstrbyevc()` | `ajax/whitebooks/gstr1/filegstrbyevc.php` |
| GSTR1 | Get TXPA | `/gstr1/txpa` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::gettxpa()` | `ajax/whitebooks/gstr1/gettxpa.php` |
| GSTR1 | Get E-Invoices | `/gstr1/einvoice` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, sec*, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1Service::geteinvoices()` | `ajax/whitebooks/gstr1/geteinvoices.php` |

### Module: `GSTR1A` (29 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR1A | Get GSTR1A Summary | `/gstr1a/retsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getgstr1asummary()` | `ajax/whitebooks/gstr1a/getgstr1asummary.php` |
| GSTR1A | Get GSTR1A Document Issued | `/gstr1a/dociss` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getdocissued1a()` | `ajax/whitebooks/gstr1a/getdocissued1a.php` |
| GSTR1A | Get CDNRA Invoices | `/gstr1a/cdnra` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getcdnra1ainvoices()` | `ajax/whitebooks/gstr1a/getcdnra1ainvoices.php` |
| GSTR1A | Get B2CL Invoices | `/gstr1a/b2cl` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getb2cl1ainvoices()` | `ajax/whitebooks/gstr1a/getb2cl1ainvoices.php` |
| GSTR1A | Get B2BA Invoices | `/gstr1a/b2ba` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getb2ba1ainvoices()` | `ajax/whitebooks/gstr1a/getb2ba1ainvoices.php` |
| GSTR1A | Get ATA | `/gstr1a/ata` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getata1a()` | `ajax/whitebooks/gstr1a/getata1a.php` |
| GSTR1A | Get TXP | `/gstr1a/txp` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::gettxp1a()` | `ajax/whitebooks/gstr1a/gettxp1a.php` |
| GSTR1A | Get Supplier ECO invoices | `/gstr1a/supeco` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, subsection, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getsuppliereco1ainvoices()` | `ajax/whitebooks/gstr1a/getsuppliereco1ainvoices.php` |
| GSTR1A | Get Supplier ECOA invoices | `/gstr1a/supecoa` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, subsection | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getsupplierecoa1ainvoices()` | `ajax/whitebooks/gstr1a/getsupplierecoa1ainvoices.php` |
| GSTR1A | Get ECOM Invoices | `/gstr1a/ecom` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, rtin, subsection, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getecom1ainvoices()` | `ajax/whitebooks/gstr1a/getecom1ainvoices.php` |
| GSTR1A | Get ECOMA Invoices | `/gstr1a/ecoma` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, rtin, subsection | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getecoma1ainvoices()` | `ajax/whitebooks/gstr1a/getecoma1ainvoices.php` |
| GSTR1A | Save GSTR1A data | `/gstr1a/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::savegstr1a()` | `ajax/whitebooks/gstr1a/savegstr1a.php` |
| GSTR1A | Get B2B Invoices | `/gstr1a/b2b` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getb2b1ainvoices()` | `ajax/whitebooks/gstr1a/getb2b1ainvoices.php` |
| GSTR1A | Get B2CLA Invoices | `/gstr1a/b2cla` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getb2cla1ainvoices()` | `ajax/whitebooks/gstr1a/getb2cla1ainvoices.php` |
| GSTR1A | Get B2CSA Invoices | `/gstr1a/b2csa` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getb2csa1ainvoices()` | `ajax/whitebooks/gstr1a/getb2csa1ainvoices.php` |
| GSTR1A | Get CDNR Invoices | `/gstr1a/cdnr` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getcdnr1ainvoices()` | `ajax/whitebooks/gstr1a/getcdnr1ainvoices.php` |
| GSTR1A | Get AT | `/gstr1a/at` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getat1a()` | `ajax/whitebooks/gstr1a/getat1a.php` |
| GSTR1A | Get EXPA | `/gstr1a/expa` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getexpa1a()` | `ajax/whitebooks/gstr1a/getexpa1a.php` |
| GSTR1A | Get B2CS Invoices | `/gstr1a/b2cs` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getb2cs1ainvoices()` | `ajax/whitebooks/gstr1a/getb2cs1ainvoices.php` |
| GSTR1A | Get EXP | `/gstr1a/exp` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getexp1a()` | `ajax/whitebooks/gstr1a/getexp1a.php` |
| GSTR1A | Get Nil Rated Supplies | `/gstr1a/nil` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getnilratedsupplies1a()` | `ajax/whitebooks/gstr1a/getnilratedsupplies1a.php` |
| GSTR1A | Get HSN Summary details | `/gstr1a/hsnsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::gethsnsummarydetails1a()` | `ajax/whitebooks/gstr1a/gethsnsummarydetails1a.php` |
| GSTR1A | API call for getting all Credit/ Debit Notes issued to un-registered persons for a return period. | `/gstr1a/cdnur` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getcdnur1a()` | `ajax/whitebooks/gstr1a/getcdnur1a.php` |
| GSTR1A | API call for getting all amended Credit/ Debit Notes issued to un-registered persons for a return period. | `/gstr1a/cdnura` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::getcdnura1a()` | `ajax/whitebooks/gstr1a/getcdnura1a.php` |
| GSTR1A | Reset GSTR1A Data | `/gstr1a/reset` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::resetgstr1a()` | `ajax/whitebooks/gstr1a/resetgstr1a.php` |
| GSTR1A | File GSTR1A data | `/gstr1a/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::filegstr1a()` | `ajax/whitebooks/gstr1a/filegstr1a.php` |
| GSTR1A | File GSTR1A data | `/gstr1a/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::filegstrbyevc1a()` | `ajax/whitebooks/gstr1a/filegstrbyevc1a.php` |
| GSTR1A | Proceed To File | `/gstr1a/proceedfile` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email*, isNil | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::proceedtofile()` | `ajax/whitebooks/gstr1a/proceedtofile.php` |
| GSTR1A | Get TXPA | `/gstr1a/txpa` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR1AService::gettxpa1a()` | `ajax/whitebooks/gstr1a/gettxpa1a.php` |

### Module: `GSTR2A` (12 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR2A | Get B2B Invoices | `/gstr2a/b2b` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, ctin, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2AService::getb2binvoices()` | `ajax/whitebooks/gstr2a/getb2binvoices.php` |
| GSTR2A | Get B2BA Invoices | `/gstr2a/b2ba` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2AService::getb2bainvoices()` | `ajax/whitebooks/gstr2a/getb2bainvoices.php` |
| GSTR2A | CDN Invoice | `/gstr2a/cdn` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, ctin, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2AService::getcdninvoices()` | `ajax/whitebooks/gstr2a/getcdninvoices.php` |
| GSTR2A | Get CDNA Invoices | `/gstr2a/cdna` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, ctin, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2AService::getcdnainvoices()` | `ajax/whitebooks/gstr2a/getcdnainvoices.php` |
| GSTR2A | Get IMPG | `/gstr2a/impg` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, fromtime, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2AService::getimpg()` | `ajax/whitebooks/gstr2a/getimpg.php` |
| GSTR2A | Get IMPGSEZ | `/gstr2a/impgsez` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, fromtime, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2AService::getimpgsez()` | `ajax/whitebooks/gstr2a/getimpgsez.php` |
| GSTR2A | Get TCS | `/gstr2a/tcs` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2AService::gettcs()` | `ajax/whitebooks/gstr2a/gettcs.php` |
| GSTR2A | Get TDS Credit | `/gstr2a/tds` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2AService::gettdsinvoices()` | `ajax/whitebooks/gstr2a/gettdsinvoices.php` |
| GSTR2A | Get AMDHIST | `/gstr2a/amdhist` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, portcode*, benumber*, bedate*, email*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2AService::getamdhist()` | `ajax/whitebooks/gstr2a/getamdhist.php` |
| GSTR2A | Get ECOM Invoices | `/gstr2a/ecom` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, ctin, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2AService::getecominvoices()` | `ajax/whitebooks/gstr2a/getecominvoices.php` |
| GSTR2A | Get ECOMA Invoices | `/gstr2a/ecoma` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2AService::getecomainvoices()` | `ajax/whitebooks/gstr2a/getecomainvoices.php` |
| GSTR2A | Get ISD Details | `/gstr2a/isd` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2AService::getisddetails()` | `ajax/whitebooks/gstr2a/getisddetails.php` |

### Module: `GSTR2B` (3 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR2B | Get All Details | `/gstr2b/all` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, rtnprd*, filenum, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2BService::getalldetails()` | `ajax/whitebooks/gstr2b/getalldetails.php` |
| GSTR2B | Generate 2B on demand | `/gstr2b/gen2b` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2BService::generate2bondemand()` | `ajax/whitebooks/gstr2b/generate2bondemand.php` |
| GSTR2B | Get 2B generate status | `/gstr2b/get2b` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, int_tran_id*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2BService::get2bstatus()` | `ajax/whitebooks/gstr2b/get2bstatus.php` |

### Module: `GSTR2X` (4 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR2X | Save GSTR2X data | `/gstr2x/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2XService::savegstr()` | `ajax/whitebooks/gstr2x/savegstr.php` |
| GSTR2X | File GSTR2X data | `/gstr2x/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2XService::filegstr()` | `ajax/whitebooks/gstr2x/filegstr.php` |
| GSTR2X | File GSTR2X data | `/gstr2x/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2XService::filegstr2xbyevc()` | `ajax/whitebooks/gstr2x/filegstr2xbyevc.php` |
| GSTR2X | Get TDS | `/gstr2x/tdstcs` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email*, fromtime*, rectype* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR2XService::gettds()` | `ajax/whitebooks/gstr2x/gettds.php` |

### Module: `GSTR3B` (15 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR3B | Get GSTR3B Summary | `/gstr3b/retsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::getgstrsummary()` | `ajax/whitebooks/gstr3b/getgstrsummary.php` |
| GSTR3B | Get GSTR1 auto calculated liability details | `/gstr3b/autoliab` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::getgstr1autocalculatedliabilitydetails()` | `ajax/whitebooks/gstr3b/getgstr1autocalculatedliabilitydetails.php` |
| GSTR3B | Save GSTR3B data | `/gstr3b/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::savegstr()` | `ajax/whitebooks/gstr3b/savegstr.php` |
| GSTR3B | Offset Liability GSTR3B Data | `/gstr3b/retoffset` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::offsetliabilitygstr3b()` | `ajax/whitebooks/gstr3b/offsetliabilitygstr3b.php` |
| GSTR3B | Save past liability breakup GSTR3B Data | `/gstr3b/liabilitybreakup` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::savepastliabilitybreakupgstr3b()` | `ajax/whitebooks/gstr3b/savepastliabilitybreakupgstr3b.php` |
| GSTR3B | File GSTR3B data | `/gstr3b/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan* | See OpenAPI requestBody: required application/json | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::filegstr()` | `ajax/whitebooks/gstr3b/filegstr.php` |
| GSTR3B | File GSTR3B data | `/gstr3b/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::filegstrbyevc()` | `ajax/whitebooks/gstr3b/filegstrbyevc.php` |
| GSTR3B | validate GSTR3B against auto calculated values | `/gstr3b/validateautocalculatedata` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::validategstr3bautocalculatedvalues()` | `ajax/whitebooks/gstr3b/validategstr3bautocalculatedvalues.php` |
| GSTR3B | Recompute System Calulated Interest | `/gstr3b/cmpint` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::reComputeinterest()` | `ajax/whitebooks/gstr3b/re-computeinterest.php` |
| GSTR3B | Get system calculated interest | `/gstr3b/syscalcintrst` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::getsystemcalculatedinterest()` | `ajax/whitebooks/gstr3b/getsystemcalculatedinterest.php` |
| GSTR3B | Get ITC Reversal Balance | `/gstr3b/closingbal` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::getitcreversalbalance()` | `ajax/whitebooks/gstr3b/getitcreversalbalance.php` |
| GSTR3B | Get Opening Balance For Credit Reversal and Re-claimed Details | `/gstr3b/openingbal` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::getopeningbalance()` | `ajax/whitebooks/gstr3b/getopeningbalance.php` |
| GSTR3B | Get latest closing balance | `/gstr3b/rcmclosingbal` | GET | ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::getclosingbalance()` | `ajax/whitebooks/gstr3b/getclosingbalance.php` |
| GSTR3B | Get RCM opening balance | `/gstr3b/rcmopeningbal` | GET | ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::getopeningbalance()` | `ajax/whitebooks/gstr3b/getopeningbalance.php` |
| GSTR3B | Submit RCM opening balance | `/gstr3b/savercmopnbal` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR3BService::submitopeningbalance()` | `ajax/whitebooks/gstr3b/submitopeningbalance.php` |

### Module: `GSTR4` (23 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR4 | Save GSTR4 data | `/gstr4/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::savegstr()` | `ajax/whitebooks/gstr4/savegstr.php` |
| GSTR4 | Get B2B Invoices | `/gstr4/b2b` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, actionrequired, gstin*, retperiod*, ctin, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::getb2binvoices()` | `ajax/whitebooks/gstr4/getb2binvoices.php` |
| GSTR4 | Get B2B Unregistered Invoice | `/gstr4/b2bur` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::getb2bunregisteredinvoice()` | `ajax/whitebooks/gstr4/getb2bunregisteredinvoice.php` |
| GSTR4 | Get IMPS | `/gstr4/imps` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::getimps()` | `ajax/whitebooks/gstr4/getimps.php` |
| GSTR4 | Get CDNR Invoices | `/gstr4/cdnr` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, actionrequired, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::getcdninvoices()` | `ajax/whitebooks/gstr4/getcdninvoices.php` |
| GSTR4 | API call for getting all Credit/ Debit Notes issued to un-registered persons for a return period. | `/gstr4/cdnur` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::getcdnur()` | `ajax/whitebooks/gstr4/getcdnur.php` |
| GSTR4 | API call for getting invoices for Tax on Outward Supplies for a return period.  | `/gstr4/txos` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::gettaxonourwardsuppliesinvoice()` | `ajax/whitebooks/gstr4/gettaxonourwardsuppliesinvoice.php` |
| GSTR4 | Get AT | `/gstr4/at` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::getat()` | `ajax/whitebooks/gstr4/getat.php` |
| GSTR4 | Get TXP | `/gstr4/txp` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::gettxp()` | `ajax/whitebooks/gstr4/gettxp.php` |
| GSTR4 | Get GSTR1 Summary | `/gstr4/retsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::getgstrsummary()` | `ajax/whitebooks/gstr4/getgstrsummary.php` |
| GSTR4 | Submit GSTR4 data | `/gstr4/retsubmit` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::submitgstr()` | `ajax/whitebooks/gstr4/submitgstr.php` |
| GSTR4 | Get TDS | `/gstr4/tds` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::gettds()` | `ajax/whitebooks/gstr4/gettds.php` |
| GSTR4 | File GSTR4 data | `/gstr4/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::filegstr()` | `ajax/whitebooks/gstr4/filegstr.php` |
| GSTR4 | File GSTR4 data | `/gstr4/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::filegstrbyevc()` | `ajax/whitebooks/gstr4/filegstrbyevc.php` |
| GSTR4 | API call for offsetting the liabilities and interest from GSTR4 | `/gstr4/retoffset` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::setoffliability()` | `ajax/whitebooks/gstr4/setoffliability.php` |
| GSTR4 | Get B2BA Invoices | `/gstr4/b2ba` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, actionrequired, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::getb2bainvoices()` | `ajax/whitebooks/gstr4/getb2bainvoices.php` |
| GSTR4 | Get B2BUR Amendment | `/gstr4/b2bura` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::getb2burainvoices()` | `ajax/whitebooks/gstr4/getb2burainvoices.php` |
| GSTR4 | Get Imports of Services Amendment | `/gstr4/impsa` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::getimpsa()` | `ajax/whitebooks/gstr4/getimpsa.php` |
| GSTR4 | Get CDNR Amendment | `/gstr4/cdnra` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, actionrequired | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::getcdnrainvoices()` | `ajax/whitebooks/gstr4/getcdnrainvoices.php` |
| GSTR4 | Get CDNUR Amendment | `/gstr4/cdnura` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::getcdnura()` | `ajax/whitebooks/gstr4/getcdnura.php` |
| GSTR4 | Get TXOS Amendment | `/gstr4/txosa` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::gettxosainvoice()` | `ajax/whitebooks/gstr4/gettxosainvoice.php` |
| GSTR4 | Get Advances Paid Amendment | `/gstr4/ata` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::getata()` | `ajax/whitebooks/gstr4/getata.php` |
| GSTR4 | Get Advances Adjusted Amendment | `/gstr4/txpa` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4Service::gettxpa()` | `ajax/whitebooks/gstr4/gettxpa.php` |

### Module: `GSTR4A` (5 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR4A | Get B2B Invoices | `/gstr4a/b2b` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AService::getb2binvoices()` | `ajax/whitebooks/gstr4a/getb2binvoices.php` |
| GSTR4A | Get B2BA Invoices | `/gstr4a/b2ba` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AService::getb2bainvoices()` | `ajax/whitebooks/gstr4a/getb2bainvoices.php` |
| GSTR4A | CDNR Invoice | `/gstr4a/cdnr` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AService::getcdnrinvoices()` | `ajax/whitebooks/gstr4a/getcdnrinvoices.php` |
| GSTR4A | CDNRA Invoice | `/gstr4a/cdnra` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AService::getcdnrainvoices()` | `ajax/whitebooks/gstr4a/getcdnrainvoices.php` |
| GSTR4A | TDS Details | `/gstr4a/tds` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AService::gettdsdetails()` | `ajax/whitebooks/gstr4a/gettdsdetails.php` |

### Module: `GSTR4Annual` (9 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR4Annual | Save GSTR4Annual data | `/gstr4annual/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AnnualService::savegstr()` | `ajax/whitebooks/gstr4annual/savegstr.php` |
| GSTR4Annual | Get tdscmp Invoices | `/gstr4annual/tdscmp` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AnnualService::getgstr4annualtdscmpinvoices()` | `ajax/whitebooks/gstr4annual/getgstr4annualtdscmpinvoices.php` |
| GSTR4Annual | Get txios Invoices | `/gstr4annual/txios` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AnnualService::getgstr4annualtxiosinvoices()` | `ajax/whitebooks/gstr4annual/getgstr4annualtxiosinvoices.php` |
| GSTR4Annual | Get validate turnover for GSTR4 Annual form | `/gstr4annual/validateturnover` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AnnualService::getgstr4annualvalidateturnover()` | `ajax/whitebooks/gstr4annual/getgstr4annualvalidateturnover.php` |
| GSTR4Annual | Get validate the liability provided in table 6 for GSTR4 Annual form | `/gstr4annual/validateliability` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AnnualService::getgstr4annualvalidateliability()` | `ajax/whitebooks/gstr4annual/getgstr4annualvalidateliability.php` |
| GSTR4Annual | Get autopopulated data for table 4A/4B of GSTR4 Annual form | `/gstr4annual/autopop` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AnnualService::getgstr4annualautopop()` | `ajax/whitebooks/gstr4annual/getgstr4annualautopop.php` |
| GSTR4Annual | Get summary Invoices | `/gstr4annual/getsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AnnualService::getgstr4annualsummaryinvoices()` | `ajax/whitebooks/gstr4annual/getgstr4annualsummaryinvoices.php` |
| GSTR4Annual | File GSTR4Annual data | `/gstr4annual/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AnnualService::filegstr()` | `ajax/whitebooks/gstr4annual/filegstr.php` |
| GSTR4Annual | File GSTR4 Annual data | `/gstr4annual/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR4AnnualService::fileevcgstr()` | `ajax/whitebooks/gstr4annual/fileevcgstr.php` |

### Module: `GSTR5` (21 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR5 | Get B2B Invoices | `/gstr5/b2b` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getb2binvoices()` | `ajax/whitebooks/gstr5/getb2binvoices.php` |
| GSTR5 | Get B2BA Invoices | `/gstr5/b2ba` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getb2bainvoices()` | `ajax/whitebooks/gstr5/getb2bainvoices.php` |
| GSTR5 | Get CDNR Invoices | `/gstr5/cdn` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getcdninvoices()` | `ajax/whitebooks/gstr5/getcdninvoices.php` |
| GSTR5 | Get CDNRA Invoices | `/gstr5/cdna` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getcdnainvoices()` | `ajax/whitebooks/gstr5/getcdnainvoices.php` |
| GSTR5 | Get IMPG Invoices | `/gstr5/impg` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getimpg()` | `ajax/whitebooks/gstr5/getimpg.php` |
| GSTR5 | Get IMPGA Invoices | `/gstr5/impga` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getimpga()` | `ajax/whitebooks/gstr5/getimpga.php` |
| GSTR5 | Get IMPS Invoices | `/gstr5/imps` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getimps()` | `ajax/whitebooks/gstr5/getimps.php` |
| GSTR5 | Get IMPSA Invoices | `/gstr5/impsa` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getimpsa()` | `ajax/whitebooks/gstr5/getimpsa.php` |
| GSTR5 | Save GSTR5 data | `/gstr5/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::savegstr()` | `ajax/whitebooks/gstr5/savegstr.php` |
| GSTR5 | Get CDNA Unregistered | `/gstr5/cdnura` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getcdnura()` | `ajax/whitebooks/gstr5/getcdnura.php` |
| GSTR5 | Get GSTR5 Summary | `/gstr5/retsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getgstrsummary()` | `ajax/whitebooks/gstr5/getgstrsummary.php` |
| GSTR5 | Submit | `/gstr5/retsubmit` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::submitgstr()` | `ajax/whitebooks/gstr5/submitgstr.php` |
| GSTR5 | File GSTR5 | `/gstr5/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::filegstr()` | `ajax/whitebooks/gstr5/filegstr.php` |
| GSTR5 | File GSTR5 data | `/gstr5/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::filegstrbyevc()` | `ajax/whitebooks/gstr5/filegstrbyevc.php` |
| GSTR5 | Get B2CL Invoices | `/gstr5/b2cl` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: statecd, gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getb2clinvoices()` | `ajax/whitebooks/gstr5/getb2clinvoices.php` |
| GSTR5 | Get B2CS Invoices | `/gstr5/b2cs` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: statecd, gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getb2csinvoices()` | `ajax/whitebooks/gstr5/getb2csinvoices.php` |
| GSTR5 | Get CDN Unregistered | `/gstr5/cdnur` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getcdnur()` | `ajax/whitebooks/gstr5/getcdnur.php` |
| GSTR5 | Get Tax Liab | `/gstr5/ttxli` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getttxli()` | `ajax/whitebooks/gstr5/getttxli.php` |
| GSTR5 |  Offset Liability GSTR5 data | `/gstr5/retoffset` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::offsetliabilitygstr3b()` | `ajax/whitebooks/gstr5/offsetliabilitygstr3b.php` |
| GSTR5 | Get B2CLA Invoices | `/gstr5/b2cla` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: statecd, gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getb2clainvoices()` | `ajax/whitebooks/gstr5/getb2clainvoices.php` |
| GSTR5 | Get B2CSA Invoices | `/gstr5/b2csa` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: statecd, gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR5Service::getb2csainvoices()` | `ajax/whitebooks/gstr5/getb2csainvoices.php` |

### Module: `GSTR6` (16 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR6 | Get B2B Invoices | `/gstr6/b2b` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, actionrequired, gstin*, retperiod*, ctin, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::getb2binvoices()` | `ajax/whitebooks/gstr6/getb2binvoices.php` |
| GSTR6 | GSTR6 Calculate R6 | `/gstr6/retcal` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::calculater6()` | `ajax/whitebooks/gstr6/calculater6.php` |
| GSTR6 | Save GSTR6 Cross ITC details | `/gstr6/savecrossitcdetails` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::savecrossgstr()` | `ajax/whitebooks/gstr6/savecrossgstr.php` |
| GSTR6 |  Offset Liability GSTR6 Late fee | `/gstr6/retoffsetlatefee` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::offsetliability()` | `ajax/whitebooks/gstr6/offsetliability.php` |
| GSTR6 | Save GSTR6 data | `/gstr6/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::savegstr()` | `ajax/whitebooks/gstr6/savegstr.php` |
| GSTR6 | Get CDNR Invoices | `/gstr6/cdn` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, actionrequired, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::getcdninvoices()` | `ajax/whitebooks/gstr6/getcdninvoices.php` |
| GSTR6 | Get ISD Details | `/gstr6/isd` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::getisddetails()` | `ajax/whitebooks/gstr6/getisddetails.php` |
| GSTR6 | Get GSTR6 Summary | `/gstr6/retsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email*, fnl* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::getgstrsummary()` | `ajax/whitebooks/gstr6/getgstrsummary.php` |
| GSTR6 | Submit GSTR6 data | `/gstr6/retsubmit` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::submitgstr()` | `ajax/whitebooks/gstr6/submitgstr.php` |
| GSTR6 | File GSTR6 data | `/gstr6/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::filegstr()` | `ajax/whitebooks/gstr6/filegstr.php` |
| GSTR6 | File GSTR6 data | `/gstr6/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::filegstrbyevc()` | `ajax/whitebooks/gstr6/filegstrbyevc.php` |
| GSTR6 | Get Late Fee | `/gstr6/latefee` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::getlatefee()` | `ajax/whitebooks/gstr6/getlatefee.php` |
| GSTR6 | Get ITC Details | `/gstr6/itc` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::getitcdetails()` | `ajax/whitebooks/gstr6/getitcdetails.php` |
| GSTR6 | Get B2BA Invoices | `/gstr6/b2ba` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, actionrequired, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::getb2bainvoices()` | `ajax/whitebooks/gstr6/getb2bainvoices.php` |
| GSTR6 | Get CDNRA Invoices | `/gstr6/cdna` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: actionrequired, gstin*, retperiod*, email*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::getcdnainvoices()` | `ajax/whitebooks/gstr6/getcdnainvoices.php` |
| GSTR6 | Get Amendment ISD Details | `/gstr6/isda` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, ctin, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6Service::getisdainvoices()` | `ajax/whitebooks/gstr6/getisdainvoices.php` |

### Module: `GSTR6A` (4 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR6A | Get B2B Invoices | `/gstr6a/b2b` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, actionrequired, gstin*, retperiod*, ctin, fromtime | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6AService::getb2binvoices()` | `ajax/whitebooks/gstr6a/getb2binvoices.php` |
| GSTR6A | Get CDNR Invoices | `/gstr6a/cdn` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, actionrequired, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6AService::getcdninvoices()` | `ajax/whitebooks/gstr6a/getcdninvoices.php` |
| GSTR6A | Get B2BA Invoices | `/gstr6a/b2ba` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, actionrequired, gstin*, retperiod*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6AService::getb2bainvoices()` | `ajax/whitebooks/gstr6a/getb2bainvoices.php` |
| GSTR6A | Get CDNRA Invoices | `/gstr6a/cdna` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: actionrequired, gstin*, retperiod*, email*, ctin | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR6AService::getcdnainvoices()` | `ajax/whitebooks/gstr6a/getcdnainvoices.php` |

### Module: `GSTR7` (6 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR7 | File GSTR7 | `/gstr7/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR7Service::filegstr()` | `ajax/whitebooks/gstr7/filegstr.php` |
| GSTR7 | File GSTR7 data | `/gstr7/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR7Service::filegstrbyevc()` | `ajax/whitebooks/gstr7/filegstrbyevc.php` |
| GSTR7 | Save GSTR7 | `/gstr7/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR7Service::savegstr()` | `ajax/whitebooks/gstr7/savegstr.php` |
| GSTR7 | Get GSTR7 Summary | `/gstr7/retsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR7Service::getgstrsummary()` | `ajax/whitebooks/gstr7/getgstrsummary.php` |
| GSTR7 | Get TDS details | `/gstr7/tds` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email*, fromtime*, rectype* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR7Service::gettdsdetails()` | `ajax/whitebooks/gstr7/gettdsdetails.php` |
| GSTR7 | Get TDS Checksum | `/gstr7/tdschecksum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email*, fromtime*, rectype* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR7Service::gettdschecksum()` | `ajax/whitebooks/gstr7/gettdschecksum.php` |

### Module: `GSTR8` (7 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR8 | Get TCS Invoices | `/gstr8/tcs` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, fromtime, rettype | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR8Service::gettcsinvoice()` | `ajax/whitebooks/gstr8/gettcsinvoice.php` |
| GSTR8 | Get GSTR8 Summary | `/gstr8/retsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, fnl* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR8Service::getgstrsummary()` | `ajax/whitebooks/gstr8/getgstrsummary.php` |
| GSTR8 | Save GSTR8 data | `/gstr8/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR8Service::savegstr()` | `ajax/whitebooks/gstr8/savegstr.php` |
| GSTR8 | File GSTR8 data | `/gstr8/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR8Service::filegstr()` | `ajax/whitebooks/gstr8/filegstr.php` |
| GSTR8 | File GSTR8 data | `/gstr8/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR8Service::filegstrbyevc()` | `ajax/whitebooks/gstr8/filegstrbyevc.php` |
| GSTR8 | Get Checksum | `/gstr8/checksum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email*, fromtime, rettype | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR8Service::getchecksum()` | `ajax/whitebooks/gstr8/getchecksum.php` |
| GSTR8 | Get EID | `/gstr8/eid` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email*, fromtime, rettype | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR8Service::geteid()` | `ajax/whitebooks/gstr8/geteid.php` |

### Module: `GSTR9` (8 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR9 | File GSTR9 | `/gstr9/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9Service::filegstr()` | `ajax/whitebooks/gstr9/filegstr.php` |
| GSTR9 | File GSTR9 data | `/gstr9/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9Service::filegstrbyevc()` | `ajax/whitebooks/gstr9/filegstrbyevc.php` |
| GSTR9 | Save GSTR9 | `/gstr9/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9Service::savegstr()` | `ajax/whitebooks/gstr9/savegstr.php` |
| GSTR9 | Get GSTR9 Details | `/gstr9/getdet` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9Service::getgstrdetails()` | `ajax/whitebooks/gstr9/getgstrdetails.php` |
| GSTR9 | Get GSTR9 Auto Calculated Details | `/gstr9/getautocal` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9Service::getautocalculateddetails()` | `ajax/whitebooks/gstr9/getautocalculateddetails.php` |
| GSTR9 | Create 8A Data API | `/gstr9/create8adetails` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9Service::create8adataapi()` | `ajax/whitebooks/gstr9/create8adataapi.php` |
| GSTR9 | Get GSTR9 8A Details | `/gstr9/get8adetails` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, fy*, token*, docid, email*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9Service::getgstr98adetails()` | `ajax/whitebooks/gstr9/getgstr98adetails.php` |
| GSTR9 | Get R1R1A HSN Details | `/gstr9/getHsndetails` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, fy*, email*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9Service::getgstr9r1r1adetails()` | `ajax/whitebooks/gstr9/getgstr9r1r1adetails.php` |

### Module: `GSTR9A` (5 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR9A | File GSTR9A | `/gstr9a/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9AService::filegstr()` | `ajax/whitebooks/gstr9a/filegstr.php` |
| GSTR9A | File GSTR9A data | `/gstr9a/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9AService::filegstrbyevc()` | `ajax/whitebooks/gstr9a/filegstrbyevc.php` |
| GSTR9A | Save GSTR9A | `/gstr9a/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9AService::savegstr()` | `ajax/whitebooks/gstr9a/savegstr.php` |
| GSTR9A | Get GSTR9A Details | `/gstr9a/getdet` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9AService::getgstrdetails()` | `ajax/whitebooks/gstr9a/getgstrdetails.php` |
| GSTR9A | Get GSTR9A Auto Calculated Details | `/gstr9a/getautocal` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9AService::getautocalculateddetails()` | `ajax/whitebooks/gstr9a/getautocalculateddetails.php` |

### Module: `GSTR9C` (7 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| GSTR9C | File GSTR9C | `/gstr9c/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9CService::filegstr()` | `ajax/whitebooks/gstr9c/filegstr.php` |
| GSTR9C | File GSTR9C data | `/gstr9c/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9CService::filegstrbyevc()` | `ajax/whitebooks/gstr9c/filegstrbyevc.php` |
| GSTR9C | Save GSTR9C | `/gstr9c/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9CService::savegstr()` | `ajax/whitebooks/gstr9c/savegstr.php` |
| GSTR9C | Get GSTR9C Summary | `/gstr9c/retsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9CService::getgstrsummary()` | `ajax/whitebooks/gstr9c/getgstrsummary.php` |
| GSTR9C | Get 9 Records for GSTR9C | `/gstr9c/getrecds` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9CService::getrecordsfor9c()` | `ajax/whitebooks/gstr9c/getrecordsfor9c.php` |
| GSTR9C | Hash Generator | `/gstr9c/genhash` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9CService::hashgenerator()` | `ajax/whitebooks/gstr9c/hashgenerator.php` |
| GSTR9C | Generate Certificate | `/gstr9c/gencert` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `GSTR9CService::generatecertificate()` | `ajax/whitebooks/gstr9c/generatecertificate.php` |

### Module: `SPIKE` (4 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| SPIKE | Save SPIKE data | `/spike/spikesave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `SPIKEService::savespike()` | `ajax/whitebooks/spike/savespike.php` |
| SPIKE | Get DRC01C/DRC01B Data | `/spike/rtncomplist` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, refid*, retperiod*, status, frmtyp* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Status/poll (async follow-up)) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `SPIKEService::getdrc01cDrc01b()` | `ajax/whitebooks/spike/getdrc01c-drc01b.php` |
| SPIKE | get mismatch in GSTR1 and GSTR3B | `/spike/rtncompsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, refid*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `SPIKEService::getmismatch()` | `ajax/whitebooks/spike/getmismatch.php` |
| SPIKE | File SPIKE data | `/spike/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `SPIKEService::filespike()` | `ajax/whitebooks/spike/filespike.php` |

### Module: `ALL` (13 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| ALL | Save User Masters | `/all/savemasters` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ALLService::saveusermasters()` | `ajax/whitebooks/all/saveusermasters.php` |
| ALL | Get User Masters | `/all/getmasters` | GET | ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ALLService::getusermasters()` | `ajax/whitebooks/all/getusermasters.php` |
| ALL | Get File Details | `/all/filedet` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, returnperiod*, token*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ALLService::getfiledetails()` | `ajax/whitebooks/all/getfiledetails.php` |
| ALL | Get Large File Details | `/all/largefile` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: url*, ek*, gstin*, returnperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ALLService::getlargefiledetails()` | `ajax/whitebooks/all/getlargefiledetails.php` |
| ALL | Late Fee | `/all/latefee` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, rettype*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ALLService::latefee()` | `ajax/whitebooks/all/latefee.php` |
| ALL | Get additional late fee breakup | `/all/latefeebreakup` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, rettype*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ALLService::latefeebreakup()` | `ajax/whitebooks/all/latefeebreakup.php` |
| ALL | Document Download | `/all/docdwld` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, id*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ALLService::documentdownload()` | `ajax/whitebooks/all/documentdownload.php` |
| ALL | Proceed To File | `/all/proceedfile` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, type*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ALLService::proceedtofile()` | `ajax/whitebooks/all/proceedtofile.php` |
| ALL | New Proceed To File(GSTR1,GSTR5,GSTR6) | `/all/newproceedfile` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, type*, isNil, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ALLService::newproceedtofile()` | `ajax/whitebooks/all/newproceedtofile.php` |
| ALL | Document Upload | `/all/docupld` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ALLService::documentupload()` | `ajax/whitebooks/all/documentupload.php` |
| ALL | Get Return Status | `/all/newretstatus` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, returnperiod*, refid*, rettype*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Status/poll (async follow-up)) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ALLService::getnewreturnstatus()` | `ajax/whitebooks/all/getnewreturnstatus.php` |
| ALL | Save Preference | `/all/savepref` | PUT | gstin*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Status/poll (async follow-up)) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ALLService::savepreference()` | `ajax/whitebooks/all/savepreference.php` |
| ALL | Get Preference | `/all/getpref` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, fy*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Status/poll (async follow-up)) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ALLService::getpreference()` | `ajax/whitebooks/all/getpreference.php` |

### Module: `IMS` (8 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| IMS | Save IMS Data | `/ims/save` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `IMSService::saveims()` | `ajax/whitebooks/ims/saveims.php` |
| IMS | Reset IMS Data | `/ims/reset` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `IMSService::resetims()` | `ajax/whitebooks/ims/resetims.php` |
| IMS | Get IMS Status | `/ims/status` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, int_tran_id*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `IMSService::getimsstatus()` | `ajax/whitebooks/ims/getimsstatus.php` |
| IMS | Get IMS data | `/ims/invoices` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, section*, email*, status | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `IMSService::getimssummary()` | `ajax/whitebooks/ims/getimssummary.php` |
| IMS | Get IMS Count data | `/ims/invoicescount` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, goodsType*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `IMSService::getimscountsummary()` | `ajax/whitebooks/ims/getimscountsummary.php` |
| IMS | Get Supplier IMS Invoices data | `/ims/supplierInvoices` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, email*, retperiod*, section*, rtnTyp* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `IMSService::getsupplierimsinvoices()` | `ajax/whitebooks/ims/getsupplierimsinvoices.php` |
| IMS | Get Added Back Liability Records | `/ims/rejectedInvoices` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, email*, retperiod*, section | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `IMSService::getaddedbackliabilityrecords()` | `ajax/whitebooks/ims/getaddedbackliabilityrecords.php` |
| IMS | Get IMS data | `/ims/getfile` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, token*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `IMSService::getimsfilesummary()` | `ajax/whitebooks/ims/getimsfilesummary.php` |

### Module: `ITC03` (7 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| ITC03 | Save ITC03 data | `/itc03/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, type* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ITC03Service::saveitc03()` | `ajax/whitebooks/itc03/saveitc03.php` |
| ITC03 | Get ITC03 Summary | `/itc03/retsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email*, type*, arnnum* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ITC03Service::getitc03summary()` | `ajax/whitebooks/itc03/getitc03summary.php` |
| ITC03 | File ITC03 data | `/itc03/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, type* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ITC03Service::fileitc03()` | `ajax/whitebooks/itc03/fileitc03.php` |
| ITC03 | File ITC03 data | `/itc03/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp*, type* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ITC03Service::filegstr()` | `ajax/whitebooks/itc03/filegstr.php` |
| ITC03 | Get ITC03 Invoices | `/itc03/close` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, type* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ITC03Service::close()` | `ajax/whitebooks/itc03/close.php` |
| ITC03 | Get ITC03 Invoices | `/itc03/getinv` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod*, type*, arnnum* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ITC03Service::getinvoices()` | `ajax/whitebooks/itc03/getinvoices.php` |
| ITC03 |  Offset Liability ITC03 data | `/itc03/retoffset` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, type* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ITC03Service::offsetliability()` | `ajax/whitebooks/itc03/offsetliability.php` |

### Module: `ITC04` (6 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| ITC04 | Save ITC04 data | `/itc04/retsave` | PUT | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ITC04Service::saveitc04()` | `ajax/whitebooks/itc04/saveitc04.php` |
| ITC04 | Get ITC04 Summary | `/itc04/retsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ITC04Service::getitc04summary()` | `ajax/whitebooks/itc04/getitc04summary.php` |
| ITC04 | File ITC04 data | `/itc04/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ITC04Service::fileitc04()` | `ajax/whitebooks/itc04/fileitc04.php` |
| ITC04 | File ITC04 data | `/itc04/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ITC04Service::filegstr()` | `ajax/whitebooks/itc04/filegstr.php` |
| ITC04 | Validate TurnOver ITC04 data | `/itc04/validateturnover` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ITC04Service::validateitc04turnover()` | `ajax/whitebooks/itc04/validateitc04turnover.php` |
| ITC04 | Get ITC04 Invoices | `/itc04/getinv` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `ITC04Service::getinvoices()` | `ajax/whitebooks/itc04/getinvoices.php` |

### Module: `CMP` (5 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| CMP | Get CMP | `/cmp/getcmp` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `CMPService::getcmp()` | `ajax/whitebooks/cmp/getcmp.php` |
| CMP | Save CMP | `/cmp/savecmp` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `CMPService::savecmp()` | `ajax/whitebooks/cmp/savecmp.php` |
| CMP | File CMP data | `/cmp/retfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `CMPService::filecmp()` | `ajax/whitebooks/cmp/filecmp.php` |
| CMP | File CMP data | `/cmp/retevcfile` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, pan*, evcotp* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `CMPService::filecmpbyevc()` | `ajax/whitebooks/cmp/filecmpbyevc.php` |
| CMP | Get validate turnover for CMP | `/cmp/validateturnover` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, retperiod* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `CMPService::getvalidateturnover()` | `ajax/whitebooks/cmp/getvalidateturnover.php` |

### Module: `Payment` (4 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| Payment | Generate Challan | `/payment/generateChallan` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `PaymentService::generateChallan()` | `ajax/whitebooks/payment/generate-challan.php` |
| Payment | Get Challan History | `/payment/chllnlst` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, fromdate*, todate*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `PaymentService::getchallanhistory()` | `ajax/whitebooks/payment/getchallanhistory.php` |
| Payment | Get Challan Summary | `/payment/chllnsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, cpin*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `PaymentService::getchallansummary()` | `ajax/whitebooks/payment/getchallansummary.php` |
| Payment | Validate Challan Reason | `/payment/validatechlnrsn` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `PaymentService::validateChallanReason()` | `ajax/whitebooks/payment/validate-challan-reason.php` |

### Module: `Ledger` (11 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| Ledger | Cash Ledger Details | `/ledgers/cashdtl` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, frdt*, todt*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `LedgerService::getcashledgerdetails()` | `ajax/whitebooks/ledger/getcashledgerdetails.php` |
| Ledger | ITC Ledger Details | `/ledgers/itc` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, frdt*, todt*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `LedgerService::getitcledgerdetails()` | `ajax/whitebooks/ledger/getitcledgerdetails.php` |
| Ledger | Get Liability Ledger Details | `/ledgers/tax` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, frdt*, todt*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `LedgerService::getliabilityledgerdetails()` | `ajax/whitebooks/ledger/getliabilityledgerdetails.php` |
| Ledger | Get Cash ITC Balance | `/ledgers/bal` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `LedgerService::getcashitcbalance()` | `ajax/whitebooks/ledger/getcashitcbalance.php` |
| Ledger | Get RetLiab Balance | `/ledgers/taxpayable` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, retperiod*, rettype*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `LedgerService::getretliabbalance()` | `ajax/whitebooks/ledger/getretliabbalance.php` |
| Ledger | Get Block Unblock | `/ledgers/itcblocktrandetls` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, frdt*, todt*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `LedgerService::getblockunblockdetails()` | `ajax/whitebooks/ledger/getblockunblockdetails.php` |
| Ledger | Get Credit Reversal and Re-claimed Details | `/ledgers/revrclmdtl` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, frdt*, todt*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `LedgerService::getcreditreversalandreclaimeddetails()` | `ajax/whitebooks/ledger/getcreditreversalandreclaimeddetails.php` |
| Ledger | Get NegativeLiability Details of GSTR3B | `/ledgers/negliabstmt` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, frdt*, todt*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `LedgerService::getnegativeliabilitydetailsofgstr3b()` | `ajax/whitebooks/ledger/getnegativeliabilitydetailsofgstr3b.php` |
| Ledger | Get RCM Statement | `/ledgers/rcmldg` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, frdt*, todt*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `LedgerService::getrcmstatement()` | `ajax/whitebooks/ledger/getrcmstatement.php` |
| Ledger | Utilize Cash | `/ledgers/utlcsh` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `LedgerService::utilizecash()` | `ajax/whitebooks/ledger/utilizecash.php` |
| Ledger | Utilize ITC | `/ledgers/utlitc` | POST | gstin*, ret_period*, gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email* | See OpenAPI requestBody: required application/json type=string | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation | `LedgerService::utilizeitc()` | `ajax/whitebooks/ledger/utilizeitc.php` |

### Module: `e-Invoice` (5 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| e-Invoice | Get E-Invice HSN Summary | `/gst/einvoice/hsnsum` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, ret_period* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `eInvoiceService::gethsnsum()` | `ajax/whitebooks/e-invoice/gethsnsum.php` |
| e-Invoice | Get list of IRNs | `/gst/einvoice/irnlist` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, rstinflag, suptyp*, rtnprd* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `eInvoiceService::getlistofirns()` | `ajax/whitebooks/e-invoice/getlistofirns.php` |
| e-Invoice | Get list of IRN Jsons | `/gst/einvoice/irnjsons` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, rstinflag, suptyp*, rtnprd* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `eInvoiceService::getlistofirnjsons()` | `ajax/whitebooks/e-invoice/getlistofirnjsons.php` |
| e-Invoice | Get file URL | `/gst/einvoice/filedtl` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, rtnprd*, token* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Often async (returns reference / requires status check) - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `eInvoiceService::getfileurl()` | `ajax/whitebooks/e-invoice/getfileurl.php` |
| e-Invoice | Get Json for IRN | `/gst/einvoice/irndtl` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: email*, gstin*, irn* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `eInvoiceService::getjsonforirn()` | `ajax/whitebooks/e-invoice/getjsonforirn.php` |

### Module: `Notices` (2 operations)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| Notices | Get List Of Notices | `/notices/noticelist` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, date*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Typically synchronous JSON response - confirm per response schema) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `NoticesService::getlistofnotices()` | `ajax/whitebooks/notices/getlistofnotices.php` |
| Notices | Get Notice Details | `/notices/noticedetails` | GET | gst_username*, state_cd*, ip_address*, txn*, client_id*, client_secret* | path: -; query: gstin*, refId*, email* | No requestBody in OpenAPI | HTTP 404/500 - see OpenAPI responses for schema (Status/poll (async follow-up)) | GST session (username / auth-token); GSP client credentials; ip_address header | No (not indicated in OpenAPI summary/params) | Pending implementation | `NoticesService::getnoticedetails()` | `ajax/whitebooks/notices/getnoticedetails.php` |

---

## 7. Related published specs (separate products)

### e-Invoice OpenAPI inventory

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| Authentication | Authentication Request | `/einvoice/authenticate` | GET | username*, password*, ip_address*, client_id*, client_secret*, gstin* | path: -; query: email* | No requestBody in OpenAPI | HTTP 404/500 | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation (separate from existing e-Way) | `EInvoiceService::authentication()` | `ajax/whitebooks/einvoice/authentication.php` |
| Get GSTN Details | Get GSTN Details | `/einvoice/type/GSTNDETAILS/version/V1_03` | GET | ip_address*, client_id*, client_secret*, username*, auth-token*, gstin* | path: -; query: param1*, email* | No requestBody in OpenAPI | HTTP 200/404/500 | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation (separate from existing e-Way) | `EInvoiceService::getGstnDetails()` | `ajax/whitebooks/einvoice/get-gstn-details.php` |
| Get Sync GSTIN From CP | Get Sync GSTIN From CP | `/einvoice/type/SYNC_GSTIN_FROMCP/version/V1_03` | GET | ip_address*, client_id*, client_secret*, username*, auth-token*, gstin* | path: -; query: param1*, email* | No requestBody in OpenAPI | HTTP 200/404/500 | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation (separate from existing e-Way) | `EInvoiceService::getSyncGstinFromCp()` | `ajax/whitebooks/einvoice/get-sync-gstin-from-cp.php` |
| Get EInvoice Details | Get EInvoice Details | `/einvoice/type/GETIRN/version/V1_03` | GET | ip_address*, client_id*, client_secret*, username*, auth-token*, gstin* | path: -; query: param1*, email*, supplier_gstn, irp | No requestBody in OpenAPI | HTTP 404/500 | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation (separate from existing e-Way) | `EInvoiceService::gstIrn()` | `ajax/whitebooks/einvoice/gst-irn.php` |
| Get IRN details by Doc Details | Get IRN details by Doc Details | `/einvoice/type/GETIRNBYDOCDETAILS/version/V1_03` | GET | docnum*, docdate*, ip_address*, client_id*, client_secret*, username*, auth-token*, gstin* | path: -; query: param1*, supplier_gstn, irp, email* | No requestBody in OpenAPI | HTTP 404/500 | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation (separate from existing e-Way) | `EInvoiceService::getIrnDetailsByDocDetails()` | `ajax/whitebooks/einvoice/get-irn-details-by-doc-details.php` |
| Get Rejected IRNs | Get Rejected IRNs | `/einvoice/type/GETREJECTEDIRNS/version/V1_03` | GET | ip_address*, client_id*, client_secret*, username*, auth-token*, gstin* | path: -; query: param1*, supplier_gstn, email* | No requestBody in OpenAPI | HTTP 404/500 | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation (separate from existing e-Way) | `EInvoiceService::getRejectedIrns()` | `ajax/whitebooks/einvoice/get-rejected-irns.php` |
| Cancel IRN | Cancel IRN | `/einvoice/type/CANCEL/version/V1_03` | POST | ip_address*, client_id*, client_secret*, username*, auth-token*, gstin* | path: -; query: email*, irp | See OpenAPI requestBody: required application/json ref=UserBasicDetails | HTTP 404/500 | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation (separate from existing e-Way) | `EInvoiceService::cancelIrnPost()` | `ajax/whitebooks/einvoice/cancel-irn-post.php` |
| Generate Ewaybill | Generate Ewaybill | `/einvoice/type/GENERATE_EWAYBILL/version/V1_03` | POST | ip_address*, client_id*, client_secret*, username*, auth-token*, gstin* | path: -; query: email*, irp | See OpenAPI requestBody: required application/json ref=GernerateEwayBill | HTTP 404/500 | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation (separate from existing e-Way) | `EInvoiceService::generateEwaybill()` | `ajax/whitebooks/einvoice/generate-ewaybill.php` |
| Get Ewaybill Details by IRN | Get Ewaybill Details by IRN | `/einvoice/type/GETEWAYBILLIRN/version/V1_03` | GET | ip_address*, client_id*, client_secret*, username*, auth-token*, gstin* | path: -; query: param1*, supplier_gstn, irp, email* | No requestBody in OpenAPI | HTTP 200/404/500 | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation (separate from existing e-Way) | `EInvoiceService::getEwaybillDetailsByIrn()` | `ajax/whitebooks/einvoice/get-ewaybill-details-by-irn.php` |
| Generate IRN | Generate IRN | `/einvoice/type/GENERATE/version/V1_03` | POST | ip_address*, client_id*, client_secret*, username*, auth-token*, gstin* | path: -; query: email* | See OpenAPI requestBody: required application/json ref=body | HTTP 404/500 | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation (separate from existing e-Way) | `EInvoiceService::generateIrnPost()` | `ajax/whitebooks/einvoice/generate-irn-post.php` |
| Get B2C QR Code Details | Get B2C QR Code Details | `/einvoice/qrcode` | GET | ip_address*, client_id*, client_secret*, username*, sgstin*, docno*, docdate*, totinvval*, upiid, bankaccno*, bankifsccode*, accountholdername*, igstamount*, cgstamount*, sgstamount*, cessamount* | path: -; query: email* | No requestBody in OpenAPI | HTTP 200/404/500 | GST session (username / auth-token); GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Pending implementation (separate from existing e-Way) | `EInvoiceService::getB2cQrCodeDetails()` | `ajax/whitebooks/einvoice/get-b2c-qr-code-details.php` |

### e-Way Bill OpenAPI inventory (existing GoldMatrix integration covers generate/auth; expand carefully)

| Module | Operation name | Endpoint | HTTP method | Required headers | Required parameters | Request example | Response example | Authentication requirement | OTP requirement | Status | Application service method | Application route |
|--------|----------------|----------|-------------|------------------|---------------------|-----------------|------------------|----------------------------|-----------------|--------|----------------------------|-------------------|
| Authentication | Authentication Request | `/ewaybillapi/v1.03/authenticate` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, username*, password*, irp | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Partially implemented (see ewaybill_* helpers) | `EwayService::authenticationRequest()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Generate Eway Bill | Generate Eway Bill | `/ewaybillapi/v1.03/ewayapi/genewaybill` | POST | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, irp | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Partially implemented (see ewaybill_* helpers) | `EwayService::generateEwayBill()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Update PART-B/Vehicle Number | Update PART-B/Vehicle Number | `/ewaybillapi/v1.03/ewayapi/vehewb` | POST | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, irp | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::updatePartBVehicleNumber()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Generate Consolidated Ewaybill | Generate Consolidated Ewaybill | `/ewaybillapi/v1.03/ewayapi/gencewb` | POST | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::generateConsolidatedEwaybill()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Cancel E-Way bill | Cancel E-Way bill | `/ewaybillapi/v1.03/ewayapi/canewb` | POST | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, irp | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::cancelEWayBill()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Reject EwayBill | Reject EwayBill | `/ewaybillapi/v1.03/ewayapi/rejewb` | POST | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::rejectEwaybill()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Update Transporter | Update Transporter | `/ewaybillapi/v1.03/ewayapi/updatetransporter` | POST | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, irp | See OpenAPI requestBody: optional application/json type=object | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::updateTransporter()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Extend Validity of E-Way Bill | Extend Validity of E-Way Bill | `/ewaybillapi/v1.03/ewayapi/extendvalidity` | POST | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::extendValidityOfEWayBill()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Regenerate Consolidated E-Way Bill - Api | Regenerate Consolidated E-Way Bill - Api | `/ewaybillapi/v1.03/ewayapi/regentripsheet` | POST | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::regenerateConsolidatedEWayBillApi()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Get EwayBill Details | Get EwayBill Details | `/ewaybillapi/v1.03/ewayapi/getewaybill` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, ewbNo* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getEwaybillDetails()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Get EWay bill for transporter by Date | Get eway bill assigned to you (requesting GSTIN) for a transportation – Particular Date | `/ewaybillapi/v1.03/ewayapi/getewaybillsfortransporter` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, date* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getEwayBillAssignedToYouRequestingGstinForATransportationParticularDate()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Get EwayBill Report By Transporter assigned Date | Get eway bill assigned to you (requesting GSTIN) for a transportation - Particular Date | `/ewaybillapi/v1.03/ewayapi/getewaybillreportbytransporterassigneddate` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, date*, stateCode* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getEwayBillAssignedToYouRequestingGstinForATransportationParticularDate()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Get Eway Bills By Date | Get eway bills assigned to you (requesting GSTIN) for a Particular Date | `/ewaybillapi/v1.03/ewayapi/getewaybillsbydate` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, date* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getEwayBillsAssignedToYouRequestingGstinForAParticularDate()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Get Eway Bills Rejected By Others | Get eway bills rejected by others for a Particular Date | `/ewaybillapi/v1.03/ewayapi/getewaybillsrejectedbyothers` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, date* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getEwayBillsRejectedByOthersForAParticularDate()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Get EwayBills For Transporter By Gstin | Get eway bill assigned to you (requesting GSTIN) for transportation – Particular GSTIN and Date | `/ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbygstin` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, Gen_gstin*, date* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getEwayBillAssignedToYouRequestingGstinForTransportationParticularGstinAndDate()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Get EwayBills For Transporter By State | Get eway bill assigned to you (requesting GSTIN) for transportation – Particular State and Date | `/ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbystate` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, stateCode*, date* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getEwayBillAssignedToYouRequestingGstinForTransportationParticularStateAndDate()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Get Eway bills by parties | Get e-way bills generated on you (requesting GSTIN) by other parties | `/ewaybillapi/v1.03/ewayapi/getewaybillsofotherparty` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, date* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getEWayBillsGeneratedOnYouRequestingGstinByOtherParties()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Get consolidated e-way bill | Get consolidated e-way bill | `/ewaybillapi/v1.03/ewayapi/gettripsheet` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, tripSheetNo* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getConsolidatedEWayBill()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Get EwayBill by Consigner | Get e-way bill details for a Document Type and Document number. | `/ewaybillapi/v1.03/ewayapi/getewaybillgeneratedbyconsigner` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, docType*, docNo* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getEWayBillDetailsForADocumentTypeAndDocumentNumber()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| GET Transin details | GET Transin details | `/ewaybillapi/v1.03/ewayapi/gettransporterdetails` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, trn_no* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getTransinDetails()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Initiate Multi Vehicle Movement | Initiate Multi Vehicle Movement | `/ewaybillapi/v1.03/ewayapi/initmulti` | POST | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::initiateMultiVehicleMovement()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Add Multi Vehicles | Add Multi Vehicles | `/ewaybillapi/v1.03/ewayapi/addmulti` | POST | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::addMultiVehicles()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Change Multi Vehicles | Change Multi Vehicles | `/ewaybillapi/v1.03/ewayapi/updtmulti` | POST | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::changeMultiVehicles()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Get Error List | Get Error List | `/ewaybillapi/v1.03/ewayapi/geterrorlist` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getErrorList()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Get GSTIN details | Get GSTIN details | `/ewaybillapi/v1.03/ewayapi/getgstindetails` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, GSTIN* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getGstinDetails()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| GET HSN details | GET HSN details | `/ewaybillapi/v1.03/ewayapi/gethsndetailsbyhsncode` | GET | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email*, hsncode* | No requestBody in OpenAPI | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::getHsnDetails()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |
| Closure e-Way Bill | Closure e-Way Bill | `/ewaybillapi/v1.03/ewayapi/clsewb` | POST | ip_address*, client_id*, client_secret*, gstin* | path: -; query: email* | See OpenAPI requestBody: required application/json type=object | HTTP 404/500 | GSP client credentials; ip_address header; GSTIN header | No (not indicated in OpenAPI summary/params) | Documented in OpenAPI - not necessarily wired in app | `EwayService::closureEWayBill()` | existing e-way pages / ajax OR future `ajax/whitebooks/eway/...` |

---

## 8. Phase gate - what is intentionally NOT done yet

- No new service classes, controllers, routes, or placeholder stubs for the 281 GST operations.
- No database tables created.
- No admin UI menu.
- No hardcoded API keys.
- Request/response **payload examples** will be filled from sandbox captures and OpenAPI component schemas in Phase 2+ module docs.
- Authenticated walkthrough of https://developer.whitebooks.in/gstapis "Expand Operations" should be done with your WhiteBooks developer login to cross-check any portal-only notes not present in OpenAPI.

## 9. Recommended next step (Phase 2 kickoff)

1. Create WhiteBooks developer sandbox credentials (client_id / client_secret) - store only via env/DB encryption.
2. Implement `WhiteBooksHttpClient` + Authentication service **only**, against OpenAPI Authentication + Public tags.
3. Add `tbl_gst_api_*` schema scripts matching GoldMatrix conventions.
4. Then implement modules in the order you specified (Public -> GSTR -> GSTR1 -> ...).

## 10. Local artifacts used to build this inventory

| File | Purpose |
|------|---------|
| `tools/_download_wb_openapi.php` | Downloads official OpenAPI JSON |
| `tools/_parse_wb_openapi.php` | Parses specs to `tmp_wb_inventory.json` |
| `tools/_generate_wb_integration_doc.php` | Generates this markdown |
| `tmp_wb_gst.json` / `tmp_wb_einvoice.json` / `tmp_wb_eway.json` | Cached official specs (public; no app secrets) |
| `tmp_wb_inventory.json` | Intermediate machine inventory |

---

## 11. e-Way Bill API — Phase 1 inventory (official OpenAPI)

> **Updated:** 2026-08-05 09:11:48 UTC
>
> **Authoritative source:** https://whitebooks.in/openapi/eway.json (E-WAYBILL-API)
>
> **Portal:** https://developer.whitebooks.in/ewaybillapis (login-gated HTML in this environment; inventory built from the published OpenAPI contract, which lists the same operation set as the portal tags).
>
> **Architecture note:** Shared `includes/WhiteBooks/` HTTP client / GST session tables are **planned** (GST Phase 1 doc only). Current runtime e-Way code remains in `includes/ewaybill_api_helper.php` + `tbl_ewaybill_api_*`. New e-Way services must **reuse** the shared client once created — do not fork a second disconnected stack. Do **not** break sale-invoice / POS generate flows.

### 11.1 Existing project + GST architecture reuse audit

| Item | Current state | Reuse plan |
|------|---------------|------------|
| `includes/WhiteBooks/*` shared client | **Not created yet** (GST Phase 1 inventory only) | Create once; e-Way + GST services both depend on it |
| Legacy e-Way HTTP/auth | `includes/ewaybill_api_helper.php`, `config/ewaybill_config.php` | Wrap behind `EWayBillAuthenticationService` / generation services; keep endpoints exact |
| Credentials storage | `tbl_ewaybill_api_settings`, file defaults, optional `*.local.php`, env `WHITEBOOKS_*` | Migrate secrets to encrypted credential store shared with GST; **production disabled until configured** |
| Tokens | `tbl_ewaybill_api_tokens` (`auth_token`, `sek`, expiry) per GSTIN/username | Keep GSTIN-wise rows; encrypt at rest when shared crypto helper lands |
| Request logs | `tbl_ewaybill_api_logs`, `tbl_ewaybill_generate_logs`, `logs/eway_*.log` | Continue + also write masked rows to shared `tbl_gst_api_requests` when that table exists |
| Sale invoice UX | Checkbox generate on save + regenerate AJAX | Keep; later call generation service instead of duplicating curl |
| Queues | None | Same future job runner as GST (no auto-retry of irreversible ops) |
| Permissions today | `ewaybill_api_settings`, `ewaybill_authentication` | Extend with `ewaybill.*` keys listed below (Phase 7) |

### 11.2 Servers (from OpenAPI)

- **Production:** `https://api.whitebooks.in`
- **Sandbox:** `https://apisandbox.whitebooks.in`

OpenAPI `info`: **E-WAYBILL-API** version **1.0**.

**Operation count in official spec: 27**

### 11.3 Planned modular layout (under existing framework)

```text
includes/WhiteBooks/                 # shared (GST + e-Way)
  Http/WhiteBooksHttpClient.php
  Support/ (masking, idempotency, response normalizer)
includes/WhiteBooks/EWayBill/
  DTOs/
  Services/
    EWayBillAuthenticationService.php
    EWayBillGenerationService.php
    EWayBillUpdateService.php
    EWayBillCancellationService.php
    EWayBillQueryService.php
    ConsolidatedEWayBillService.php
    MultiVehicleMovementService.php
    EWayBillMasterDataService.php
  Support/ (validators, mappers from sale invoice)
ajax/whitebooks/ewaybill/*.php       # thin JSON endpoints
sql/whitebooks_eway_*.sql            # tbl_* schema scripts
```

### 11.4 Planned / existing database mapping

| Planned domain table | Notes vs existing schema |
|----------------------|--------------------------|
| Credentials / sessions / generic API requests | Prefer shared `tbl_gst_api_*` once created; until then keep `tbl_ewaybill_api_settings|tokens|logs` |
| `tbl_eway_bills` (or extend `tbl_sale_invoices` eway_* columns) | Sale invoices already store `eway_bill_no`, dates, status — full document store table planned for standalone generate UI |
| `tbl_eway_bill_items` | New when standalone generate stores line items |
| `tbl_eway_bill_vehicle_history` | New for Part-B history |
| `tbl_consolidated_eway_bills` + items | New |
| `tbl_eway_bill_actions` | New action audit; can mirror masked payloads like `tbl_ewaybill_api_logs` |
| `tbl_eway_bill_multi_vehicle_movements` | New |

**No new tables created in this Phase 1 inventory step.**

### 11.5 Authentication (exact OpenAPI operations)

See the Authentication row(s) in the operation tables below. Existing helper `ewaybill_authenticate()` already calls WhiteBooks authenticate and stores token/SEK. New work must use the **same endpoint/headers as OpenAPI**, keep sandbox default, and never expose tokens to JS.

### 11.6 Complete operation inventory

Request/response JSON examples below are taken from OpenAPI `example` / schema examples when present. If OpenAPI omits examples, the field shows `Not provided in OpenAPI` — **do not invent payloads**.

#### 11.6.1 Add Multi Vehicles

| Field | Value |
|-------|-------|
| Operation name | Add Multi Vehicles |
| Portal / OpenAPI tag | Add Multi Vehicles |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/addmulti` |
| HTTP method | `POST` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email |
| Path parameters | - |
| Request body | required application/json {ewbNo*:number, vehicleNo*:string, groupNo*:String, transDocNo*:string, transDocDate*:string, quantity*:integer} |
| Field validation rules | ewbNo is required; groupNo is required; quantity is required; transDocDate is required; transDocNo is required; vehicleNo is required; transDocNo maxLength=15; transDocDate pattern=[0-3][0-9]/[0-1][0-9]/[2][0][1-2][0-9] |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `MultiVehicleMovementService::manage()` |
| Application controller | EWayBill/MultiVehicleMovementServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/add-multi-vehicles.php` |
| Permission | `ewaybill.multi_vehicle.manage` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Add Multi Vehicles

**Request example**

```json
{
    "ewbNo": 381009282489,
    "vehicleNo": "ABC1234",
    "groupNo": "1",
    "transDocNo": "12",
    "transDocDate": "11/12/2024",
    "quantity": 1
}
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.2 Authentication Request

| Field | Value |
|-------|-------|
| Operation name | Authentication Request |
| Portal / OpenAPI tag | Authentication |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/authenticate` |
| HTTP method | `GET` |
| Required authentication | Pre-auth: email/username/password/GSTIN + client_id/client_secret/ip_address (exact header/query names per OpenAPI) |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; username* (string) — User Name; password* (string) — Password; irp (string) — e-WayBill Server Type(NIC1/NIC2) |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. Auth uses `authtoken` (and related session fields such as SEK when returned by authenticate) per WhiteBooks e-Way headers; store encrypted at rest in app. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillAuthenticationService::authenticate()` |
| Application controller | EWayBill/EWayBillAuthenticationServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/authentication-request.php` |
| Permission | `ewaybill.authentication.manage` |
| Implementation status | Legacy implemented (`ewaybill_authenticate` in includes/ewaybill_api_helper.php) - to be wrapped by shared WhiteBooks client later |

Description (from OpenAPI): Authentication Request

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.3 Cancel E-Way bill

| Field | Value |
|-------|-------|
| Operation name | Cancel E-Way bill |
| Portal / OpenAPI tag | Cancel E-Way bill |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/canewb` |
| HTTP method | `POST` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; irp (string) — e-WayBill Server Type(NIC1/NIC2) |
| Path parameters | - |
| Request body | required application/json {ewbNo*:number, cancelRsnCode*:number, cancelRmrk:string} |
| Field validation rules | cancelRsnCode is required; ewbNo is required |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillCancellationService::cancel()` |
| Application controller | EWayBill/EWayBillCancellationServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/cancel-e-way-bill.php` |
| Permission | `ewaybill.cancel` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Cancel E-Way bill

**Request example**

```json
{
    "ewbNo": 381009282489,
    "cancelRsnCode": 2,
    "cancelRmrk": "Order Cancelled"
}
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.4 Change Multi Vehicles

| Field | Value |
|-------|-------|
| Operation name | Change Multi Vehicles |
| Portal / OpenAPI tag | Change Multi Vehicles |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/updtmulti` |
| HTTP method | `POST` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email |
| Path parameters | - |
| Request body | required application/json {ewbNo*:number, groupNo*:number, oldvehicleNo*:string, newVehicleNo*:string, oldTranNo*:string, newTranNo*:string, fromPlace*:string, fromState*:integer, reasonCode*:string, reasonRem*:string} |
| Field validation rules | ewbNo is required; fromPlace is required; fromState is required; groupNo is required; newTranNo is required; newVehicleNo is required; oldTranNo is required; oldvehicleNo is required; reasonCode is required; reasonRem is required; oldvehicleNo maxLength=15; newVehicleNo maxLength=15; oldTranNo maxLength=15; newTranNo maxLength=15; fromPlace maxLength=50; fromState maximum=2; reasonCode maxLength=1; reasonCode minLength=1; reasonRem maxLength=50 |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `MultiVehicleMovementService::manage()` |
| Application controller | EWayBill/MultiVehicleMovementServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/change-multi-vehicles.php` |
| Permission | `ewaybill.multi_vehicle.manage` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Change Multi Vehicles

**Request example**

```json
{
    "ewbNo": 381009282489,
    "groupNo": 1,
    "oldvehicleNo": "ABC1234",
    "newVehicleNo": "ABC4321",
    "oldTranNo": "sumitra/06/2020",
    "newTranNo": "sumitra/06/2021",
    "fromPlace": "FRAZER TOWN",
    "fromState": 29,
    "reasonCode": "1",
    "reasonRem": "Due to Break Down"
}
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.5 Closure e-Way Bill

| Field | Value |
|-------|-------|
| Operation name | Closure e-Way Bill |
| Portal / OpenAPI tag | Closure e-Way Bill |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/clsewb` |
| HTTP method | `POST` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email |
| Path parameters | - |
| Request body | required application/json {ewbNo*:number, closureDate*:Date, remarks*:string} |
| Field validation rules | ewbNo is required; closureDate is required; remarks is required; remarks maxLength=50 |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `MultiVehicleMovementService::close()` |
| Application controller | EWayBill/MultiVehicleMovementServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/closure-e-way-bill.php` |
| Permission | `ewaybill.multi_vehicle.manage` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Closure e-Way Bill

**Request example**

```json
{
    "ewbNo": 381009282489,
    "closureDate": "21/05/2026",
    "remarks": "Closed the order"
}
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.6 Extend Validity of E-Way Bill

| Field | Value |
|-------|-------|
| Operation name | Extend Validity of E-Way Bill |
| Portal / OpenAPI tag | Extend Validity of E-Way Bill |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/extendvalidity` |
| HTTP method | `POST` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email |
| Path parameters | - |
| Request body | required application/json {ewbNo*:number, vehicleNo:string, fromPlace*:string, fromState*:integer, remainingDistance*:number, transDocNo:string, transDocDate:string, transMode:string, extnRsnCode*:number, extnRemarks*:string, fromPincode*:number, consignmentStatus:string, transitType:string, addressLine1:string, addressLine2:string, addressLine3:string} |
| Field validation rules | ewbNo is required; extnRemarks is required; extnRsnCode is required; fromPincode is required; fromPlace is required; fromState is required; remainingDistance is required; fromPlace maxLength=50; fromState maximum=99; transDocNo maxLength=15; transDocDate pattern=[0-3][0-9]/[0-1][0-9]/[2][0][1-2][0-9] |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillUpdateService::extendValidity()` |
| Application controller | EWayBill/EWayBillUpdateServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/extend-validity-of-e-way-bill.php` |
| Permission | `ewaybill.extend_validity` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Extend Validity of E-Way Bill

**Request example**

```json
{
    "ewbNo": 381009282489,
    "vehicleNo": "ABC1234",
    "fromPlace": "FRAZER TOWN",
    "fromState": 29,
    "remainingDistance": 50,
    "transDocNo": "12",
    "transDocDate": "11/12/2024",
    "transMode": "1",
    "extnRsnCode": 1,
    "extnRemarks": "Nature Calamity",
    "fromPincode": 560001,
    "consignmentStatus": "M",
    "transitType": "",
    "addressLine1": "",
    "addressLine2": "",
    "addressLine3": ""
}
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.7 GET HSN details

| Field | Value |
|-------|-------|
| Operation name | GET HSN details |
| Portal / OpenAPI tag | GET HSN details |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/gethsndetailsbyhsncode` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; hsncode* (string) — hsncode for which the details are required |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillMasterDataService::getHsnDetails()` |
| Application controller | EWayBill/EWayBillMasterDataServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-hsn-details.php` |
| Permission | `ewaybill.lookup.hsn` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): GET HSN details

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.8 GET Transin details

| Field | Value |
|-------|-------|
| Operation name | GET Transin details |
| Portal / OpenAPI tag | GET Transin details |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/gettransporterdetails` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; trn_no* (string) — Transporter GSTIN  or Transin for which the details are required |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillMasterDataService::getTransporterDetails()` |
| Application controller | EWayBill/EWayBillMasterDataServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-transin-details.php` |
| Permission | `ewaybill.lookup.transporter` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): GET Transin details

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.9 Generate Consolidated Ewaybill

| Field | Value |
|-------|-------|
| Operation name | Generate Consolidated Ewaybill |
| Portal / OpenAPI tag | Generate Consolidated Ewaybill |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/gencewb` |
| HTTP method | `POST` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email |
| Path parameters | - |
| Request body | required application/json {fromPlace*:string, fromState*:number, vehicleNo:string, transMode*:string, transDocNo:string, transDocDate:string, tripSheetEwbBills*:array} |
| Field validation rules | fromPlace is required; fromState is required; transMode is required; tripSheetEwbBills is required; fromPlace maxLength=50; transMode enum=1\|2\|3\|4; transDocNo maxLength=15; transDocDate pattern=[0-3][0-9]/[0-1][0-9]/[2][0][1-2][0-9]; tripSheetEwbBills[].ewbNo is required |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `ConsolidatedEWayBillService::generate()` |
| Application controller | EWayBill/ConsolidatedEWayBillServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/generate-consolidated-ewaybill.php` |
| Permission | `ewaybill.consolidated.create` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Generate Consolidated Ewaybill

**Request example**

```json
{
    "fromPlace": "FRAZER TOWN",
    "fromState": 5,
    "vehicleNo": "ABC1234",
    "transMode": "1",
    "transDocNo": "12",
    "transDocDate": "20/02/2024",
    "tripSheetEwbBills": [
        {
            "ewbNo": 381009282489
        }
    ]
}
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.10 Generate Eway Bill

| Field | Value |
|-------|-------|
| Operation name | Generate Eway Bill |
| Portal / OpenAPI tag | Generate Eway Bill |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/genewaybill` |
| HTTP method | `POST` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; irp (string) — e-WayBill Server Type(NIC1/NIC2) |
| Path parameters | - |
| Request body | required application/json {supplyType*:string, subSupplyType*:string, subSupplyDesc:string, docType*:string, docNo*:string, docDate*:string, fromGstin*:string, fromTrdName:string, fromAddr1:string, fromAddr2:string, fromPlace:string, actFromStateCode*:integer, fromPincode*:integer, fromStateCode*:integer, toGstin*:string, toTrdName:string, toAddr1:string, toAddr2:string, toPlace:string, toPincode*:integer, actToStateCode*:integer, toStateCode*:integer, transactionType*:integer, totalValue:number, cgstValue:number, sgstValue:number, igstValue:number, cessValue:number, cessNonAdvolValue:number, totInvValue*:number, otherValue:number, transMode*:string, transDistance*:string, transporterName:string, transporterId:string, transDocNo:string, transDocDate:string, vehicleNo:string, vehicleType:string, itemList*:array} |
| Field validation rules | actFromStateCode is required; actToStateCode is required; docDate is required; docNo is required; docType is required; fromGstin is required; fromPincode is required; fromStateCode is required; itemList is required; subSupplyType is required; supplyType is required; toGstin is required; toPincode is required; toStateCode is required; totInvValue is required; transDistance is required; transMode is required; transactionType is required; supplyType maxLength=1; supplyType minLength=1; supplyType enum=O\|I; subSupplyDesc maxLength=20; docType enum=INV\|CHL\|BIL\|BOE\|CNT\|OTH; docNo maxLength=16; docDate pattern=[0-3][0-9]/[0-1][0-9]/[2][0][1-2][0-9]; fromGstin maxLength=15; fromGstin minLength=3; fromGstin pattern=([0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9][A-Z][0-9\|A-Z])\|(URP); fromTrdName maxLength=100; fromAddr1 maxLength=120; fromAddr2 maxLength=120; fromPlace maxLength=50; actFromStateCode maximum=99; fromPincode maximum=999999; fromPincode minimum=100000; fromStateCode maximum=99; toGstin maxLength=15; toGstin minLength=3; toGstin pattern=([0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9][A-Z][0-9\|A-Z])\|(URP); toTrdName maxLength=100; toAddr1 maxLength=120; toAddr2 maxLength=120; toPlace maxLength=50; actToStateCode maximum=99; toStateCode maximum=99; transactionType maximum=4; transMode enum=1\|2\|3\|4; transporterName maxLength=100; transDocNo maxLength=15; vehicleNo maxLength=10; itemList[].productName maxLength=100; itemList[].productDesc maxLength=100; itemList[].qtyUnit maxLength=3; itemList[].qtyUnit minLength=3 |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillGenerationService::generate()` |
| Application controller | EWayBill/EWayBillGenerationServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/generate-eway-bill.php` |
| Permission | `ewaybill.generate` |
| Implementation status | Legacy implemented for sale-invoice/POS (`ewaybill_generate_from_sale_invoice`, ajax/generate-ewaybill.php) - full module UI pending |

Description (from OpenAPI): Generate Eway Bill

**Request example**

```json
{
    "supplyType": "O",
    "subSupplyType": "1",
    "subSupplyDesc": " ",
    "docType": "INV",
    "docNo": "ebill/12/2024",
    "docDate": "11/12/2024",
    "fromGstin": "29AAGCB1286Q000",
    "fromTrdName": "welton",
    "fromAddr1": "2ND CROSS NO 59  19  A",
    "fromAddr2": "GROUND FLOOR OSBORNE ROAD",
    "fromPlace": "FRAZER TOWN",
    "actFromStateCode": 29,
    "fromPincode": 560001,
    "fromStateCode": 29,
    "toGstin": "05AAACH6188F1ZM",
    "toTrdName": "sthuthya",
    "toAddr1": "Shree Nilaya",
    "toAddr2": "Dasarahosahalli",
    "toPlace": "Beml Nagar",
    "toPincode": 263652,
    "actToStateCode": 5,
    "toStateCode": 5,
    "transactionType": 4,
    "totalValue": 56099,
    "cgstValue": 0,
    "sgstValue": 0,
    "igstValue": 300.67,
    "cessValue": 400.56,
    "cessNonAdvolValue": 400,
    "totInvValue": 57200.23,
    "otherValue": 0,
    "transMode": "1",
    "transDistance": "2487",
    "transporterName": "",
    "transporterId": "05AAACG0904A1ZL",
    "transDocNo": "12",
    "transDocDate": "",
    "vehicleNo": "APR3214",
    "vehicleType": "R",
    "itemList": [
        {
            "productName": "Wheat",
            "productDesc": "Wheat",
            "hsnCode": 33030010,
            "quantity": 4,
            "qtyUnit": "BOX",
            "taxableAmount": 56099,
            "sgstRate": 0,
            "cgstRate": 0,
            "igstRate": 3,
            "cessRate": 0,
            "cessNonadvol": 0
        }
    ]
}
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.11 Get eway bill assigned to you (requesting GSTIN) for a transportation – Particular Date

| Field | Value |
|-------|-------|
| Operation name | Get eway bill assigned to you (requesting GSTIN) for a transportation – Particular Date |
| Portal / OpenAPI tag | Get EWay bill for transporter by Date |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/getewaybillsfortransporter` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; date* (string) — E-way bill generated Date(dd/MM/YYYY) |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillMasterDataService::getGstinDetails()` |
| Application controller | EWayBill/EWayBillMasterDataServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-eway-bill-assigned-to-you-requesting-gstin-for-a-transportation-particular-date.php` |
| Permission | `ewaybill.lookup.gstin` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Get eway bill assigned to you (requesting GSTIN) for a transportation – Particular Date

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.12 Get Error List

| Field | Value |
|-------|-------|
| Operation name | Get Error List |
| Portal / OpenAPI tag | Get Error List |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/geterrorlist` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillMasterDataService::getErrorList()` |
| Application controller | EWayBill/EWayBillMasterDataServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-error-list.php` |
| Permission | `ewaybill.api_logs.view` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Get Error List

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.13 Get eway bills assigned to you (requesting GSTIN) for a Particular Date

| Field | Value |
|-------|-------|
| Operation name | Get eway bills assigned to you (requesting GSTIN) for a Particular Date |
| Portal / OpenAPI tag | Get Eway Bills By Date |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/getewaybillsbydate` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; date* (string) — E-way bill generated Date(dd/MM/YYYY) |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillMasterDataService::getGstinDetails()` |
| Application controller | EWayBill/EWayBillMasterDataServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-eway-bills-assigned-to-you-requesting-gstin-for-a-particular-date.php` |
| Permission | `ewaybill.lookup.gstin` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Get eway bills assigned to you (requesting GSTIN) for a Particular Date

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.14 Get eway bills rejected by others for a Particular Date

| Field | Value |
|-------|-------|
| Operation name | Get eway bills rejected by others for a Particular Date |
| Portal / OpenAPI tag | Get Eway Bills Rejected By Others |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/getewaybillsrejectedbyothers` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; date* (string) — E-way bill generated Date(dd/MM/YYYY) |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillCancellationService::reject()` |
| Application controller | EWayBill/EWayBillCancellationServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-eway-bills-rejected-by-others-for-a-particular-date.php` |
| Permission | `ewaybill.reject` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Get eway bills rejected by others for a Particular Date

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.15 Get e-way bills generated on you (requesting GSTIN) by other parties

| Field | Value |
|-------|-------|
| Operation name | Get e-way bills generated on you (requesting GSTIN) by other parties |
| Portal / OpenAPI tag | Get Eway bills by parties |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/getewaybillsofotherparty` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; date* (string) — E-way bill generated Date(dd/MM/YYYY) |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillGenerationService::generate()` |
| Application controller | EWayBill/EWayBillGenerationServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-e-way-bills-generated-on-you-requesting-gstin-by-other-parties.php` |
| Permission | `ewaybill.generate` |
| Implementation status | Legacy implemented for sale-invoice/POS (`ewaybill_generate_from_sale_invoice`, ajax/generate-ewaybill.php) - full module UI pending |

Description (from OpenAPI): Get e-way bills generated on you (requesting GSTIN) by other parties

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.16 Get EwayBill Details

| Field | Value |
|-------|-------|
| Operation name | Get EwayBill Details |
| Portal / OpenAPI tag | Get EwayBill Details |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/getewaybill` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; ewbNo* (string) — ewbNo |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillQueryService::query()` |
| Application controller | EWayBill/EWayBillQueryServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-ewaybill-details.php` |
| Permission | `ewaybill.reports.view` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Get EwayBill Details

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.17 Get eway bill assigned to you (requesting GSTIN) for a transportation - Particular Date

| Field | Value |
|-------|-------|
| Operation name | Get eway bill assigned to you (requesting GSTIN) for a transportation - Particular Date |
| Portal / OpenAPI tag | Get EwayBill Report By Transporter assigned Date |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/getewaybillreportbytransporterassigneddate` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; date* (string) — E-way bill generated Date(dd/MM/YYYY); stateCode* (string) — State code of the generator of the E-waybill |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillMasterDataService::getGstinDetails()` |
| Application controller | EWayBill/EWayBillMasterDataServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-eway-bill-assigned-to-you-requesting-gstin-for-a-transportation-particular-date.php` |
| Permission | `ewaybill.lookup.gstin` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Get eway bill assigned to you (requesting GSTIN) for a transportation - Particular Date

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.18 Get e-way bill details for a Document Type and Document number.

| Field | Value |
|-------|-------|
| Operation name | Get e-way bill details for a Document Type and Document number. |
| Portal / OpenAPI tag | Get EwayBill by Consigner |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/getewaybillgeneratedbyconsigner` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; docType* (string) — Document Type; docNo* (string) — Document number |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillGenerationService::generate()` |
| Application controller | EWayBill/EWayBillGenerationServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-e-way-bill-details-for-a-document-type-and-document-number.php` |
| Permission | `ewaybill.generate` |
| Implementation status | Legacy implemented for sale-invoice/POS (`ewaybill_generate_from_sale_invoice`, ajax/generate-ewaybill.php) - full module UI pending |

Description (from OpenAPI): Get e-way bill details for a Document Type and Document number.

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.19 Get eway bill assigned to you (requesting GSTIN) for transportation – Particular GSTIN and Date

| Field | Value |
|-------|-------|
| Operation name | Get eway bill assigned to you (requesting GSTIN) for transportation – Particular GSTIN and Date |
| Portal / OpenAPI tag | Get EwayBills For Transporter By Gstin |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbygstin` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; Gen_gstin* (string) — GSTIN of E-way bill generator; date* (string) — E-way bill generated Date(dd/MM/YYYY) |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillMasterDataService::getGstinDetails()` |
| Application controller | EWayBill/EWayBillMasterDataServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-eway-bill-assigned-to-you-requesting-gstin-for-transportation-particular-gstin-and-date.php` |
| Permission | `ewaybill.lookup.gstin` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Get eway bill assigned to you (requesting GSTIN) for transportation – Particular GSTIN and Date

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.20 Get eway bill assigned to you (requesting GSTIN) for transportation – Particular State and Date

| Field | Value |
|-------|-------|
| Operation name | Get eway bill assigned to you (requesting GSTIN) for transportation – Particular State and Date |
| Portal / OpenAPI tag | Get EwayBills For Transporter By State |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbystate` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; stateCode* (string) — State Code of E-way bill generator; date* (string) — E-way bill generated Date(dd/MM/YYYY) |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillMasterDataService::getGstinDetails()` |
| Application controller | EWayBill/EWayBillMasterDataServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-eway-bill-assigned-to-you-requesting-gstin-for-transportation-particular-state-and-date.php` |
| Permission | `ewaybill.lookup.gstin` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Get eway bill assigned to you (requesting GSTIN) for transportation – Particular State and Date

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.21 Get GSTIN details

| Field | Value |
|-------|-------|
| Operation name | Get GSTIN details |
| Portal / OpenAPI tag | Get GSTIN details |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/getgstindetails` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; GSTIN* (string) — GSTIN number for which the details are required |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillMasterDataService::getGstinDetails()` |
| Application controller | EWayBill/EWayBillMasterDataServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-gstin-details.php` |
| Permission | `ewaybill.lookup.gstin` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Get GSTIN details

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.22 Get consolidated e-way bill

| Field | Value |
|-------|-------|
| Operation name | Get consolidated e-way bill |
| Portal / OpenAPI tag | Get consolidated e-way bill |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/gettripsheet` |
| HTTP method | `GET` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; tripSheetNo* (string) — Consolidated E-way bill number |
| Path parameters | - |
| Request body | No requestBody in OpenAPI |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `ConsolidatedEWayBillService::get()` |
| Application controller | EWayBill/ConsolidatedEWayBillServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/get-consolidated-e-way-bill.php` |
| Permission | `ewaybill.view` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Get consolidated e-way bill

**Request example**

```json
Not provided in OpenAPI (use portal "Expand Operations" / downloaded attribute docs with sandbox credentials; do not invent fields).
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.23 Initiate Multi Vehicle Movement

| Field | Value |
|-------|-------|
| Operation name | Initiate Multi Vehicle Movement |
| Portal / OpenAPI tag | Initiate Multi Vehicle Movement |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/initmulti` |
| HTTP method | `POST` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email |
| Path parameters | - |
| Request body | required application/json {ewbNo*:number, fromPlace*:string, fromState*:integer, toPlace*:string, toState*:integer, reasonCode*:string, reasonRem*:string, totalQuantity*:integer, unitCode*:string, transMode*:string} |
| Field validation rules | ewbNo is required; fromPlace is required; fromState is required; reasonCode is required; reasonRem is required; toPlace is required; toState is required; totalQuantity is required; transMode is required; unitCode is required; fromPlace maxLength=50; fromState maximum=2; toPlace maxLength=50; toState maximum=2; reasonCode maxLength=1; reasonCode minLength=1; reasonRem maxLength=50; unitCode maxLength=3 |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `MultiVehicleMovementService::manage()` |
| Application controller | EWayBill/MultiVehicleMovementServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/initiate-multi-vehicle-movement.php` |
| Permission | `ewaybill.multi_vehicle.manage` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Initiate Multi Vehicle Movement

**Request example**

```json
{
    "ewbNo": 381009282489,
    "fromPlace": "FRAZER TOWN",
    "fromState": 29,
    "toPlace": "Beml Nagar",
    "toState": 5,
    "reasonCode": "1",
    "reasonRem": "Due to Break Down",
    "totalQuantity": 1,
    "unitCode": "BOX",
    "transMode": "1"
}
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.24 Regenerate Consolidated E-Way Bill - Api

| Field | Value |
|-------|-------|
| Operation name | Regenerate Consolidated E-Way Bill - Api |
| Portal / OpenAPI tag | Regenerate Consolidated E-Way Bill - Api |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/regentripsheet` |
| HTTP method | `POST` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email |
| Path parameters | - |
| Request body | required application/json {tripSheetNo*:number, vehicleNo:string, fromPlace*:string, fromState*:integer, reasonCode*:string, reasonRem*:string, transDocNo:string, transDocDate:string, transMode*:string} |
| Field validation rules | fromPlace is required; fromState is required; reasonCode is required; reasonRem is required; transMode is required; tripSheetNo is required; fromPlace maxLength=50; fromState maximum=99; reasonCode maxLength=1; reasonCode minLength=1; reasonRem maxLength=50; transDocNo maxLength=15; transDocDate pattern=[0-3][0-9]/[0-1][0-9]/[2][0][1-2][0-9] |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `ConsolidatedEWayBillService::regenerate()` |
| Application controller | EWayBill/ConsolidatedEWayBillServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/regenerate-consolidated-e-way-bill-api.php` |
| Permission | `ewaybill.consolidated.regenerate` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Regenerate Consolidated E-Way Bill - Api

**Request example**

```json
{
    "tripSheetNo": 3010009432,
    "vehicleNo": "ABC1234",
    "fromPlace": "FRAZER TOWN",
    "fromState": 5,
    "reasonCode": "1",
    "reasonRem": "Natural Calamity",
    "transDocNo": "12",
    "transDocDate": "20/02/2024",
    "transMode": "1"
}
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.25 Reject EwayBill

| Field | Value |
|-------|-------|
| Operation name | Reject EwayBill |
| Portal / OpenAPI tag | Reject EwayBill |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/rejewb` |
| HTTP method | `POST` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email |
| Path parameters | - |
| Request body | required application/json {ewbNo*:number} |
| Field validation rules | ewbNo is required |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillCancellationService::reject()` |
| Application controller | EWayBill/EWayBillCancellationServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/reject-ewaybill.php` |
| Permission | `ewaybill.reject` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Reject EwayBill

**Request example**

```json
{
    "ewbNo": 311009282644
}
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.26 Update PART-B/Vehicle Number

| Field | Value |
|-------|-------|
| Operation name | Update PART-B/Vehicle Number |
| Portal / OpenAPI tag | Update PART-B/Vehicle Number |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/vehewb` |
| HTTP method | `POST` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; irp (string) — e-WayBill Server Type(NIC1/NIC2) |
| Path parameters | - |
| Request body | required application/json {ewbNo:number, vehicleNo:string, fromPlace*:string, fromState*:integer, reasonCode*:string, reasonRem*:string, transDocNo:string, transDocDate:string, transMode*:string} |
| Field validation rules | fromPlace is required; fromState is required; reasonCode is required; reasonRem is required; transMode is required; fromPlace maxLength=50; fromState maximum=99; reasonCode maxLength=1; reasonCode minLength=1; reasonRem maxLength=50; transDocNo maxLength=15; transDocDate pattern=[0-3][0-9]/[0-1][0-9]/[2][0][1-2][0-9] |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillUpdateService::updatePartB()` |
| Application controller | EWayBill/EWayBillUpdateServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/update-part-b-vehicle-number.php` |
| Permission | `ewaybill.update_part_b` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Update PART-B/Vehicle Number

**Request example**

```json
{
    "ewbNo": 381009282489,
    "vehicleNo": "ABC1234",
    "fromPlace": "FRAZER TOWN",
    "fromState": 29,
    "reasonCode": "1",
    "reasonRem": "Due to Break Down",
    "transDocNo": "12",
    "transDocDate": "11/12/2024",
    "transMode": "1"
}
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

#### 11.6.27 Update Transporter

| Field | Value |
|-------|-------|
| Operation name | Update Transporter |
| Portal / OpenAPI tag | Update Transporter |
| operationId | `-` |
| WhiteBooks endpoint | `/ewaybillapi/v1.03/ewayapi/updatetransporter` |
| HTTP method | `POST` |
| Required authentication | GSP client credentials headers (`client_id`, `client_secret`, `ip_address`) + GST e-Way username/GSTIN as documented per operation; requires prior Authentication token (`authtoken` header) when listed |
| Required headers | ip_address* (string) — IP Address; client_id* (string) — Client ID; client_secret* (string) — Client Secret; gstin* (string) — GSTIN number |
| Query parameters | email* (string) — User Email; irp (string) — e-WayBill Server Type(NIC1/NIC2) |
| Path parameters | - |
| Request body | optional application/json {ewbNo:number, transporterId:string} |
| Field validation rules | See OpenAPI schema / NIC field limits when implementing; none extracted beyond schema constraints listed in generator output |
| Encryption/decryption rules | Not separately declared as request-body encryption in this OpenAPI operation. |
| Response HTTP codes in OpenAPI | 404, 500 |
| Application service | `EWayBillUpdateService::updateTransporter()` |
| Application controller | EWayBill/EWayBillUpdateServiceController (planned thin ajax wrapper) |
| Internal route | `ajax/whitebooks/ewaybill/update-transporter.php` |
| Permission | `ewaybill.update_transporter` |
| Implementation status | Pending implementation (documented in OpenAPI only; do not invent fields) |

Description (from OpenAPI): Update Transporter

**Request example**

```json
{
    "ewbNo": 381009282489,
    "transporterId": "05AAACG0904A1ZL"
}
```

**Success response example**

```json
Not provided in OpenAPI for 2xx content. Confirm against sandbox response; preserve provider JSON in masked logs.
```

**Error response example**

```json
Not provided in OpenAPI error content examples. Map provider error codes via Get Error List when implementing.
```

### 11.7 Coverage checklist vs requested portal operations

| Requested capability | Matched OpenAPI operations (by path/summary contains) |
|----------------------|-------------------------------------------------------|
| Authentication API | `GET /ewaybillapi/v1.03/authenticate` |
| Generate E-Way Bill | `POST /ewaybillapi/v1.03/ewayapi/genewaybill` |
| Update PART-B / Vehicle Number | `POST /ewaybillapi/v1.03/ewayapi/addmulti`; `POST /ewaybillapi/v1.03/ewayapi/updtmulti`; `POST /ewaybillapi/v1.03/ewayapi/initmulti`; `POST /ewaybillapi/v1.03/ewayapi/vehewb` |
| Generate Consolidated E-Way Bill | `POST /ewaybillapi/v1.03/ewayapi/gencewb` |
| Cancel E-Way Bill | `POST /ewaybillapi/v1.03/ewayapi/canewb` |
| Reject E-Way Bill | `GET /ewaybillapi/v1.03/ewayapi/getewaybillsrejectedbyothers`; `POST /ewaybillapi/v1.03/ewayapi/rejewb` |
| Update Transporter | `GET /ewaybillapi/v1.03/ewayapi/gettransporterdetails`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporter`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillreportbytransporterassigneddate`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbygstin`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbystate`; `POST /ewaybillapi/v1.03/ewayapi/updatetransporter` |
| Extend Validity | `POST /ewaybillapi/v1.03/ewayapi/extendvalidity` |
| Regenerate Consolidated | `POST /ewaybillapi/v1.03/ewayapi/regentripsheet` |
| Get E-Way Bill Details | `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporter`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsbydate`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsrejectedbyothers`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsofotherparty`; `GET /ewaybillapi/v1.03/ewayapi/getewaybill`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillreportbytransporterassigneddate`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillgeneratedbyconsigner`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbygstin`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbystate` |
| Transporter by Date | `GET /ewaybillapi/v1.03/ewayapi/gettransporterdetails`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporter`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillreportbytransporterassigneddate`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbygstin`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbystate`; `POST /ewaybillapi/v1.03/ewayapi/updatetransporter` |
| Transporter by State | `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbystate` |
| Transporter by GSTIN | `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporter`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsbydate`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsofotherparty`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillreportbytransporterassigneddate`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbygstin`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbystate`; `GET /ewaybillapi/v1.03/ewayapi/getgstindetails` |
| Transporter assigned date report | `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporter`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsbydate`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillreportbytransporterassigneddate`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbygstin`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbystate` |
| Get by Date | `GET /ewaybillapi/v1.03/ewayapi/getewaybillsbydate` |
| Rejected by Others | `GET /ewaybillapi/v1.03/ewayapi/getewaybillsrejectedbyothers`; `POST /ewaybillapi/v1.03/ewayapi/rejewb` |
| By Parties | `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporter`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsbydate`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsrejectedbyothers`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsofotherparty`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillreportbytransporterassigneddate`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbygstin`; `GET /ewaybillapi/v1.03/ewayapi/getewaybillsfortransporterbystate`; `POST /ewaybillapi/v1.03/ewayapi/vehewb` |
| Get Consolidated | `GET /ewaybillapi/v1.03/ewayapi/gettripsheet`; `POST /ewaybillapi/v1.03/ewayapi/regentripsheet` |
| By Consigner | `GET /ewaybillapi/v1.03/ewayapi/getewaybillgeneratedbyconsigner` |
| Get Error List | `GET /ewaybillapi/v1.03/ewayapi/geterrorlist` |
| Get GSTIN Details | `GET /ewaybillapi/v1.03/ewayapi/getgstindetails` |
| Get Transin Details | `GET /ewaybillapi/v1.03/ewayapi/gettransporterdetails` |
| Get HSN Details | `GET /ewaybillapi/v1.03/ewayapi/gethsndetailsbyhsncode` |
| Initiate Multi-Vehicle | `POST /ewaybillapi/v1.03/ewayapi/addmulti`; `POST /ewaybillapi/v1.03/ewayapi/updtmulti`; `POST /ewaybillapi/v1.03/ewayapi/initmulti` |
| Add Multi Vehicles | `POST /ewaybillapi/v1.03/ewayapi/addmulti`; `POST /ewaybillapi/v1.03/ewayapi/updtmulti`; `POST /ewaybillapi/v1.03/ewayapi/initmulti` |
| Change Multi Vehicles | `POST /ewaybillapi/v1.03/ewayapi/addmulti`; `POST /ewaybillapi/v1.03/ewayapi/updtmulti`; `POST /ewaybillapi/v1.03/ewayapi/initmulti` |
| Closure | `POST /ewaybillapi/v1.03/ewayapi/clsewb` |

### 11.8 Phase gate

- Inventory completed for **27** official e-Way OpenAPI operations.
- **No** new placeholder ajax endpoints, DTOs, or controllers created in this step.
- **No** schema migrations applied.
- Next allowed step (Phase 2 of e-Way plan): shared HTTP client (if still missing) + Authentication wrap + Generate/Get details — using **only** paths/fields from this inventory and sandbox credentials.

