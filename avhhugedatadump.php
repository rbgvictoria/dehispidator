<?php

require_once('pgpdoconnect.php');
require_once('dwchugearchive.php');

$load = new DwcArchive($pgdb);

$providers = [
'MEL',
/*'CANB',
'CNS',
'AD',
'DNA',
'HO',
'NSW',
'PERTH',
'NE',
'MELU',
'LTB',
'JCT',
'WOLL'*/
];

foreach ($providers as $provider) {
    $units = [];
    $select = "SELECT \"CoreID\" FROM core WHERE \"institutionCode\"='$provider'";
    $query = $pgdb->query($select);
    $result = $query->fetchAll(5);
    if ($result) {
        foreach ($result as $row) {
            $units[] = $row->CoreID;
        }
    }

    date_default_timezone_set('Australia/Melbourne');
    $i = 0;
    $n = count($units);
    echo $provider . ': ' . count($units) . ' | ' . date('Y-m-d H:i:s') . "\n";
    while ($i < $n) {
        $j = $i + 1000;
        $in = implode(',', array_slice($units, $i, 1000));
        $load->getCoreData($in);
        $load->getDeterminationHistory($in);
        echo date('Y-m-d H:i:s') . ': ' . $i . "\n";
        $i = $j;
    }
}


$load->zipItUp('avh_delta_20190305_1830');