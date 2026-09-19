$path = 'd:\laragon\www\goldmatrix\config.php'
$text = [System.IO.File]::ReadAllText($path)

$rx = [regex]'getList\(\s*(")(SELECT\s+[^"]*?LIKE\s+''\$prefix_esc%''[^"]*?)\1(\s*\.\s*\$[a-zA-Z_][\w]*)?'
$count = 0

$evaluator = {
  param($m)
  $sql = $m.Groups[2].Value
  $extra = $m.Groups[3].Value
  if ($m.Value -match 'auragold_doc_series_active_sql') {
    return $m.Value
  }
  $tm = [regex]::Match($sql, 'FROM\s+(tbl_[a-z0-9_]+)\s+WHERE', 'IgnoreCase')
  if (-not $tm.Success) {
    return $m.Value
  }
  $table = $tm.Groups[1].Value
  $script:count++
  return ('getList("' + $sql + '" . auragold_doc_series_active_sql($conn, ''' + $table + ''')' + $extra)
}

$newText = $rx.Replace($text, $evaluator)
[System.IO.File]::WriteAllText($path, $newText)
Write-Host "injected $count"
