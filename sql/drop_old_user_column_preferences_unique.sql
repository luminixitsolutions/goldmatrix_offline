-- Run this FIRST if only one tab (e.g. Silver) is being saved in tbl_user_column_preferences.
-- It drops the OLD unique key that does NOT include tab_key, so each tab (Gold, Silver, etc.) can have its own rows.
--
-- 1) List indexes to find the exact name of the unique key on (user_id, page_name, column_key):
--    SHOW INDEX FROM tbl_user_column_preferences WHERE Non_unique = 0;
--
-- 2) Uncomment and run the line below that matches your Key_name (from step 1).
--    Common names: unique_user_page_column, user_page_column, user_id_page_column

-- ALTER TABLE tbl_user_column_preferences DROP INDEX unique_user_page_column;
-- ALTER TABLE tbl_user_column_preferences DROP INDEX user_page_column;
-- ALTER TABLE tbl_user_column_preferences DROP INDEX user_id_page_column;

-- 3) Then run: add_tab_key_to_user_column_preferences.sql (Step 2 only if Step 1 already done)
--    to add the new unique key: (user_id, page_name, tab_key, column_key)
