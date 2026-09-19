-- Fix invalid seller GSTIN (e.g. 29AAGCB1286Q000 → valid 15-char format).
-- Run once after deploying admin/config/ewaybill_config.php (EWAY_GSTIN).

-- 1) Clear/update cached GSTIN in Admin → e-Way Bill API settings (overrides file when non-empty)
INSERT INTO tbl_ewaybill_api_settings (setting_key, setting_value, updated_at)
VALUES ('gstin', '29AAGCB1286Q1Z5', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

-- 2) Branch master GSTIN column is `gst_no` (not gstin). Replace YOUR_BRANCH_ID with your row id.
-- UPDATE tbl_branches SET gst_no = '29AAGCB1286Q1Z5' WHERE id = YOUR_BRANCH_ID;
