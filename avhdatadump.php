<?php

require_once('pgpdoconnect.php');
require_once('dwcarchive.php');

//new DwcArchive($pgdb, 'institutionCode', '=', 'MEL', 'AVH_MEL');
//new DwcArchive($pgdb, 'institutionCode', '=', 'AD', 'AVH_AD');
//new DwcArchive($pgdb, 'institutionCode', '=', 'BRI', 'AVH_BRI_20120716');
//new DwcArchive($pgdb, 'institutionCode', '=', 'CANB', 'AVH_CANB');
//new DwcArchive($pgdb, 'institutionCode', '=', 'CNS', 'AVH_CNS');
//new DwcArchive($pgdb, 'institutionCode', '=', 'DNA', 'AVH_DNA');
//new DwcArchive($pgdb, 'institutionCode', '=', 'HO', 'AVH_HO');
//new DwcArchive($pgdb, 'institutionCode', '=', 'NSW', 'AVH_NSW');
//new DwcArchive($pgdb, 'institutionCode', '=', 'PERTH', 'AVH_PERTH');

date_default_timezone_set('Australia/Melbourne');
$lasttime = date('Y-m-d');
//$lasttime = '2016-01-27';
new DwcArchive($pgdb, 'TimeLoaded', '>', $lasttime, 'avh_delta_' . date('Ymd_Hi'));
//new DwcArchive($pgdb, 'institutionCode', '=', 'LTB', 'avh_ltb');


?>