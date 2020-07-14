<?php 

class DwcArchive {
    private $db;
    private $field;
    private $operator;
    private $value;
    private $core;
    private $extension;
    private $filename;
    
    public function __construct($db, $field, $operator, $value, $filename) {
        $this->db = $db;
        $this->field = $field;
        $this->operator = $operator;
        $this->value = $value;
        $this->filename = $filename;
        $this->core = $this->getCoreData();
        $this->extension = $this->getDeterminationHistory();
        if ($this->core) $this->zipItUp ();
    }

    function getCoreData () {
        if (in_array($this->operator, array('IS NULL', 'IS NOT NULL'))) {
            $where = "\"$this->field\" $this->operator";
        }
        elseif ($this->operator == 'IN') {
            $where = "\"$this->field\" IN ($this->value)";
        }
        else {
            $where = "\"$this->field\"$this->operator'$this->value'";
        }
        
        $select = <<<EOT
SELECT count(*)
FROM public.core
WHERE $where
EOT;
        $stmt = $this->db->prepare($select);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            $handle = fopen('archive/unit.csv', 'w');
            $firstrow = array("ID","modified","institutionCode","collectionCode","basisOfRecord",
                "occurrenceID","catalogNumber","recordNumber","recordedBy","lifeStage","reproductiveCondition",
                "establishmentMeans","preparations","associatedSequences","associatedTaxa","occurrenceRemarks",
                "previousIdentifications","eventDate","verbatimEventDate","habitat","continent","waterBody",
                "islandGroup","island","country","countryCode","stateProvince","county","locality",
                "verbatimLocality","minimumElevationInMeters","maximumElevationInMeters","verbatimElevation",
                "minimumDepthInMeters","maximumDepthInMeters","verbatimDepth","locationRemarks",
                "decimalLatitude","decimalLongitude","geodeticDatum","coordinateUncertaintyInMeters",
                "coordinatePrecision","verbatimCoordinates","verbatimLatitude","verbatimLongitude",
                "verbatimCoordinateSystem","verbatimSRS","georeferencedBy","georeferencedDate",
                "georeferenceProtocol","georeferenceSources","georeferenceVerificationStatus",
                "georeferenceRemarks","identificationID","identificationQualifier","typeStatus","identifiedBy",
                "dateIdentified","identificationRemarks","scientificName","kingdom","phylum","class","order",
                "family","genus","specificEpithet","infraspecificEpithet","taxonRank","scientificNameAuthorship",
                "nomenclaturalStatus", "eventRemarks", "bushBlitzExpedition");
            fputcsv($handle, $firstrow);

            $select = <<<EOT
SELECT "CoreID" as id,"modified","institutionCode","collectionCode","basisOfRecord","occurrenceID",
    "catalogNumber","recordNumber","recordedBy","lifeStage","reproductiveCondition","establishmentMeans",
    "preparations","associatedSequences","associatedTaxa","occurrenceRemarks","previousIdentifications",
    "eventDate","verbatimEventDate","habitat","continent","waterBody","islandGroup","island","country",
    "countryCode","stateProvince","county","locality","verbatimLocality","minimumElevationInMeters",
    "maximumElevationInMeters","verbatimElevation","minimumDepthInMeters","maximumDepthInMeters","verbatimDepth",
    "locationRemarks","decimalLatitude","decimalLongitude","geodeticDatum","coordinateUncertaintyInMeters",
    "coordinatePrecision","verbatimCoordinates","verbatimLatitude","verbatimLongitude",
    "verbatimCoordinateSystem","verbatimSRS","georeferencedBy","georeferencedDate","georeferenceProtocol",
    "georeferenceSources","georeferenceVerificationStatus","georeferenceRemarks","identificationID",
    "identificationQualifier","typeStatus","identifiedBy","dateIdentified","identificationRemarks",
    "scientificName","kingdom","phylum","class","order","family","genus","specificEpithet","infraspecificEpithet",
    "taxonRank","scientificNameAuthorship","nomenclaturalStatus", "event_remarks", "bush_blitz_expedition"
FROM public.core
WHERE $where AND "Quarantine" IS NULL
EOT;
            $stmt = $this->db->prepare($select);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_NUM))
                fputcsv($handle, $row);

            fclose($handle);
            return TRUE;
        }
        else
            return FALSE;
    }

    function getDeterminationHistory() {
        if (in_array(strtoupper($this->operator), array('IS NULL', 'IS NOT NULL'))) {
            $where = "\"$this->field\" $this->operator";
        }
        else {
            $where = "\"$this->field\"$this->operator'$this->value'";
        }
        
        // determination history
        $handle = fopen('archive/identification_history.csv', 'w');
        $firstrow = ["CoreID","identificationID","identificationQualifier","identifiedBy","dateIdentified",
            "identificationRemarks","scientificName","scientificNameAuthorship","nomenclaturalStatus"];
        fputcsv($handle, $firstrow);

        $select = <<<EOT
SELECT count(*)         
FROM core c
JOIN determinationhistory e ON c."CoreID"=e."CoreID"
WHERE $where;
EOT;
        $stmt = $this->db->prepare($select);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            
            $select = <<<EOT
SELECT c."CoreID", e."identificationID", e."DwCIdentificationQualifier", e."identifiedBy", 
    e."dateIdentified", e."identificationRemarks", e."scientificName", e."scientificNameAuthorship", 
    e."nomenclaturalStatus"
FROM core c
JOIN determinationhistory e ON c."CoreID"=e."CoreID"
WHERE c.$where;
EOT;
            $stmt = $this->db->prepare($select);
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_NUM))
                fputcsv ($handle, $row);
            fclose($handle);
            return TRUE;
        }
        else 
            return FALSE;
    }
    
    private function zipItUp() {
        chdir('archive');
        $zip = new ZipArchive;
        if ($zip->open("$this->filename.zip", ZipArchive::CREATE) === TRUE) {
            $zip->addFile('unit.csv');
            $zip->addFile('identification_history.csv');
            $zip->addFile('meta.xml');
            $zip->addFile('eml.xml');
            $zip->close();

            // delete csv files
            unlink('unit.csv');
            unlink('identification_history.csv');
        }
         else {
            echo 'Failed...';
        }
        chdir('..');
    }
}

?>
