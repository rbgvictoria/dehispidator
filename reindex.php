<?php

require_once('pgpdoconnect.php');
require_once('reindexresource.php');
require_once('dwcarchive.php');

$resource = 'NE';

date_default_timezone_set('Australia/Melbourne');
$log = fopen('log/reindex.log', 'a');
$startdate = date('Y-m-d H:i:s');
fwrite($log, 'Reindex start: ' . $startdate . "\n");

$reindex = new ReindexResource($pgdb, $resource, $log, 500);
$reindex->harvest();

// create archive
new DwcArchive($pgdb, 'CollectionCode', '=', $resource, 'AVH_' . $resource . '_' . date('Ymd_Hi'));
fwrite($log, 'Archive created: ' . date('Y-m-d H:i:s') . "\n");

fwrite($log, 'Reindex complete: ' . date('Y-m-d H:i:s') . "\n\n\n");
fclose($log);




?>
