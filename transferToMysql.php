<?php

require_once 'pdoconnect.php';
require_once 'PgSqlToMySql.php';

$mydb = PdoConnect::connect('mysql', '10.15.15.121', 'avh_cache', 'admin', 'admpwd');
$pgdb = PdoConnect::connect('pgsql', '10.15.15.101', 'avh_cache', 'niels', 'dicranoloma');

$transfer = new PgSqlToMySql($mydb, $pgdb);

$providers = [
    'MEL',
    'CANB',
    'CNS',
    'AD',
    'DNA',
    'NSW',
    'PERTH',
    'NE',
    'LTB',
    'JCT'
];

$mydb->exec("TRUNCATE occurrence");
$mydb->exec("TRUNCATE identification_history");
$mydb->exec("TRUNCATE resource_relationship");
$mydb->exec("TRUNCATE loan");

foreach ($providers as $provider) {
    $units = [];
    $select = "SELECT \"CoreID\" FROM core WHERE \"institutionCode\"='$provider' AND (\"basisOfRecord\" is null OR \"basisOfRecord\"='PreservedSpecimen')";
    $query = $pgdb->query($select);
    $result = $query->fetchAll(5);
    if ($result) {
        foreach ($result as $row) {
            $units[] = $row->CoreID;
        }
    }

    $i = 0;
    $n = count($units);
    echo $provider . ': ' . count($units) . ' | ' . date('Y-m-d H:i:s') . "\n";
    while ($i < $n) {
        $j = $i + 1000;
        $in = implode(',', array_slice($units, $i, 1000));
        $transfer->getOccurrenceData($in);
        $transfer->getIdentificationData($in);
        $transfer->getResourceRelationshipData($in);
        $transfer->getLoansData($in);
        echo date('Y-m-d H:i:s') . ': ' . $provider . ': ' . $i . ' of ' . count($units) . "\n";
        $i = $j;
    }
}
