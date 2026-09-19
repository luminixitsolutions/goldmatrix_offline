# -*- coding: utf-8 -*-
import re

path = r'd:\laragon\www\goldmatrix\config.php'
with open(path, 'r', encoding='utf-8', errors='replace') as f:
    text = f.read()

# Inject active-sql after any getList numbering query that uses LIKE '$prefix_esc%'
# Matches: getList("SELECT ... LIKE '$prefix_esc%'[optional more SQL inside string]");
# and inserts . auragold_doc_series_active_sql($conn, 'TABLE') right after the closing " of that SQL string,
# before any existing . $extra concat.

def find_table(sql_inside):
    m = re.search(r'FROM\s+(tbl_[a-z0-9_]+)\s+WHERE', sql_inside, re.I)
    return m.group(1) if m else None

count = 0
# Find getList(" ... LIKE '$prefix_esc%' ... ")
pattern = re.compile(
    r'getList\(\s*(")(SELECT\s+[^"]*?LIKE\s+\'\$prefix_esc%\'[^"]*?)\1(\s*\.\s*\$[a-zA-Z_][\w]*)?',
    re.S
)

def repl(m):
    global count
    quote = m.group(1)
    sql = m.group(2)
    extra = m.group(3) or ''
    if 'auragold_doc_series_active_sql' in m.group(0):
        return m.group(0)
    table = find_table(sql)
    if not table:
        return m.group(0)
    # Skip unrelated getList that aren't document series (safety)
    count += 1
    return f'getList({quote}{sql}{quote} . auragold_doc_series_active_sql($conn, \'{table}\'){extra}'

new_text, n = pattern.subn(repl, text)
# pattern.subn with function already counts via global; use new_text

with open(path, 'w', encoding='utf-8', newline='') as f:
    f.write(new_text)
print('injected', count)
