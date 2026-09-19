<?php
// Inspect the working company DB seen in the user's session (goldmatrix_gm_1).
$conn = mysqli_connect('localhost', 'root', '', 'goldmatrix_gm_1');
if (!$conn) { die('connect failed: ' . mysqli_connect_error() . "\n"); }
$tables = [
    'carat' => "SELECT id, name, purity, status FROM tbl_carat LIMIT 30",
    'dashboard_rates' => "SELECT * FROM tbl_dashboard_metal_rates LIMIT 30",
];
foreach ($tables as $label => $sql) {
    echo "== $label ==\n";
    $r = mysqli_query($conn, $sql);
    if (!$r) { echo 'ERR: ' . mysqli_error($conn) . "\n"; continue; }
    while ($row = mysqli_fetch_assoc($r)) {
        echo json_encode($row), "\n";
    }
}
$r = mysqli_query($conn, "SHOW DATABASES LIKE 'goldmatrix%'");
echo "== dbs ==\n";
while ($row = mysqli_fetch_row($r)) { echo $row[0], "\n"; }
