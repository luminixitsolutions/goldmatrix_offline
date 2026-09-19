-- Fix DNS typo in stored WhiteBooks host (apisandbbox -> apisandbox).
-- Run once on deployments that hit "Could not resolve host: apisandbbox.whitebooks.in".

UPDATE tbl_ewaybill_api_settings
SET setting_value = REPLACE(setting_value, 'apisandbbox', 'apisandbox')
WHERE setting_value LIKE '%apisandbbox%';
