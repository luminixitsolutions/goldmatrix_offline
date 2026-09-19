-- Align stored IP(header) with WhiteBooks sandbox portal default (0.0.0.0).
UPDATE tbl_ewaybill_api_settings
SET setting_value = '0.0.0.0'
WHERE setting_key = 'ip_address';
