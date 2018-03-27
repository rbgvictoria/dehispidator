<?php

require_once('pgpdoconnect.php');
require_once('harvestresource.php');
require_once('dwcarchive.php');

$select = 'SELECT MAX("TimeLoaded") FROM public.core';
$stmt = $pgdb->prepare($select);
$stmt->execute();
$lasttime = $stmt->fetch(PDO::FETCH_NUM);
$lasttime = $lasttime[0];

date_default_timezone_set('Australia/Melbourne');
$log = fopen('log/harvest.log', 'a');
$startdate = date('Y-m-d H:i:s');
fwrite($log, 'Harvest start: ' . $startdate . "\n");

/*echo "-- MEL --\n";
$mel = new HarvestResource($pgdb, 'MEL', $log);
$mel->harvest();

echo "-- HO --\n";
$mel = new HarvestResource($pgdb, 'HO', $log);
$mel->harvest();
*/
echo "-- MELU --\n";
$mel = new HarvestResource($pgdb, 'MELU', $log);
$mel->harvest();
/*
echo "-- LTB --\n";
$mel = new HarvestResource($pgdb, 'LTB', $log);
$mel->harvest();

echo "-- AD --\n";
$ad = new HarvestResource($pgdb, 'AD', $log);
$ad->harvest();

echo "-- PERTH --\n";
$perth = new HarvestResource($pgdb, 'PERTH', $log);
$perth->harvest();

echo "-- DNA --\n";
$dna = new HarvestResource($pgdb, 'DNA', $log);
$dna->harvest();

echo "-- CANB --\n";
$canb = new HarvestResource($pgdb, 'CANB', $log);
$canb->harvest();

echo "-- CNS --\n";
$cns = new HarvestResource($pgdb, 'CNS', $log);
$cns->harvest();

echo "-- JCT --\n";
$cns = new HarvestResource($pgdb, 'JCT', $log);
$cns->harvest();
*/
/*echo "-- NE --\n";
$ne = new HarvestResource($pgdb, 'NE', $log);
$ne->harvest();*/
/*
echo "-- NSW --\n";
$nsw = new HarvestResource($pgdb, 'NSW', $log);
$nsw->harvest();
*/
// create archive
new DwcArchive($pgdb, 'TimeLoaded', '>', $lasttime, 'avh_delta_' . date('Ymd_Hi'));
fwrite($log, 'Archive created: ' . date('Y-m-d H:i:s') . "\n");

fwrite($log, 'Harvest complete: ' . date('Y-m-d H:i:s') . "\n\n\n");
fclose($log);

?>
