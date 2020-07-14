<?php

require_once 'pgpdoconnect.php';

$fields = [
    /*'recordNumber',
    'recordedBy',
    'lifeStage',
    'reproductiveCondition',
    'establishmentMeans',
    'preparations',
    'associatedSequences',
    'associatedTaxa',
    'occurrenceRemarks',
    'previousIdentifications',
    'eventDate',
    'verbatimEventDate',
    'habitat',
    'continent',
    'waterBody',
    'islandGroup',
    'island',
    'country',
    'countryCode',
    'stateProvince',
    'county',
    'locality',
    'verbatimLocality',*/
    //'minimumElevationInMeters',
    //'maximumElevationInMeters',
    'verbatimElevation',
    //'minimumDepthInMeters',
    //'maximumDepthInMeters',
    'verbatimDepth',
    'locationRemarks',
    //'decimalLatitude',
    //'decimalLongitude',
    'geodeticDatum',
    //'coordinateUncertaintyInMeters',
    //'coordinatePrecision',
    'verbatimCoordinates',
    'verbatimLatitude',
    'verbatimLongitude',
    'verbatimCoordinateSystem',
    'verbatimSRS',
    'georeferencedBy',
    //'georeferencedDate',
    'georeferenceProtocol',
    'georeferenceSources',
    'georeferenceVerificationStatus',
    'georeferenceRemarks',
    //'identificationID',
    'identificationQualifier',
    'typeStatus',
    'identifiedBy',
    //'dateIdentified',
    'identificationRemarks',
    'scientificName',
    'kingdom',
    'phylum',
    'class',
    'order',
    'family',
    'genus',
    'specificEpithet',
    'infraspecificEpithet',
    'taxonRank',
    'scientificNameAuthorship',
    'nomenclaturalStatus',
    'eventRemarks',
    'bushBlitzExpedition',
];

$handle = fopen('csv/backslashes.csv', 'a');

//fputcsv($handle, ['id', 'catalogNumber', 'field', 'value']);

foreach ($fields as $field) {
    echo $field . "\n";
    $sql = <<<EOT
SELECT "CoreID", "catalogNumber", '$field' as field, "$field"
FROM core
WHERE "$field" ~ '\\\\'
EOT;

    $query = $pgdb->query($sql);
    $result = $query->fetchAll(2);
    if ($result) {
        foreach ($result as $row) {
            fputcsv($handle, array_values($row));
        }
    }
}
fclose($handle);