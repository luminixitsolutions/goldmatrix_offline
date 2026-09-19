-- Tab-wise column preferences: one row per (user, page, tab, column) so Gold, Silver, and ALL tabs save separately.
-- tab_key values = metal_id from tbl_metal: 1=Gold, 2=Silver, 3=Platinum, 4=Diamond & Stones, etc.
--
-- EASIEST: Run the PHP fix once (finds and drops old unique, adds new one):
--   http://localhost/auragold/admin/sql/fix_tab_wise_column_preferences.php
--
-- OR run SQL manually (in order):

-- Step 0: Drop OLD unique key that does NOT include tab_key (otherwise only one tab's data is kept).
--         To find the index name: SHOW INDEX FROM tbl_user_column_preferences WHERE Non_unique = 0;
--         Uncomment ONE of these (use the Key_name you see):
-- ALTER TABLE tbl_user_column_preferences DROP INDEX unique_user_page_column;
-- ALTER TABLE tbl_user_column_preferences DROP INDEX user_id_page_column;
-- ALTER TABLE tbl_user_column_preferences DROP INDEX user_page_column;

-- Step 1: Add tab_key column (ignore error if already exists)
-- tab_key = metal_id from tbl_metal: 1=Gold, 2=Silver, 3=Platinum, etc.
ALTER TABLE tbl_user_column_preferences 
ADD COLUMN tab_key VARCHAR(50) NOT NULL DEFAULT '' 
COMMENT 'Tab: 1=Gold, 2=Silver, 3=Platinum, etc. (metal_id)';

-- Step 2: Add NEW unique key that INCLUDES tab_key so each tab has its own rows
-- If you get #1061 "Duplicate key name 'unique_user_page_tab_column'" then the index
-- already exists — you are done, skip this step.
-- (Index name in backticks so it is not mistaken for a column name.)
ALTER TABLE tbl_user_column_preferences 
ADD UNIQUE KEY `unique_user_page_tab_column` (user_id, page_name, tab_key, column_key);

-- Step 2a (only if you need to re-run Step 2): Drop the key first, then run Step 2 again.
-- ALTER TABLE tbl_user_column_preferences DROP INDEX `unique_user_page_tab_column`;
