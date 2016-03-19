<?php

require_once('pgpdoconnect.php');
require_once('dwchugearchive.php');

$load = new DwcArchive($pgdb);

date_default_timezone_set('Australia/Melbourne');
$i = 0;
$n = 1632681;
echo date('Y-m-d H:i:s') . "\n";
while ($i < $n) {
    $j = $i + 1000;
    $select = "SELECT core_id FROM niels.field_number_records WHERE id>$i AND id<=$j";
    $query = $pgdb->query($select);
    $result = $query->fetchAll(5);
    if ($result) {
        $in = array();
        foreach ($result as $row) {
            $in[] = $row->core_id;
        }
        $in = implode(',', $in);

        $load->getCoreData($in);
        $load->getDeterminationHistory($in);
        
        echo date('Y-m-d H:i:s') . ': ' . $i . "\n";
        $i = $j;
    }
    else 
        break;
}



?>