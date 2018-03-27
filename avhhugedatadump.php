<?php

require_once('pgpdoconnect.php');
require_once('dwchugearchive.php');

$units = [];
$select = "SELECT \"CoreID\" FROM core WHERE \"collectionCode\"='NSW'";
$query = $pgdb->query($select);
$result = $query->fetchAll(5);
if ($result) {
    foreach ($result as $row) {
        $units[] = $row->CoreID;
    }
}


$load = new DwcArchive($pgdb);

date_default_timezone_set('Australia/Melbourne');
$i = 0;
$n = count($units);
echo date('Y-m-d H:i:s') . "\n";
while ($i < $n) {
    $j = $i + 1000;
    $in = implode(',', array_slice($units, $i, 1000));
    $load->getCoreData($in);
    $load->getDeterminationHistory($in);
    echo date('Y-m-d H:i:s') . ': ' . $i . "\n";
    $i = $j;
}
$load->zipItUp('NSW_2017-02-23');




?>