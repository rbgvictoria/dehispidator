<?php 

class DwcArchive {
    private $db;
    private $units;
    private $dethist;
    
    public function __construct($db) {
        $this->db = $db;
        $this->units = fopen('archive/unit.csv', 'w');
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
        fputcsv($this->units, $firstrow);
        
        $this->dethist = fopen('archive/determinationhistory.csv', 'w');
        $detFirstrow = ["CoreID","identificationID","identificationQualifier","identifiedBy","dateIdentified",
            "identificationRemarks","scientificName","scientificNameAuthorship","nomenclaturalStatus"];
        fputcsv($this->dethist, $detFirstrow);

    }

    public function getCoreData ($values) {
        $select = <<<EOT
SELECT count(*)
FROM public.core
WHERE "CoreID" IN ($values) AND "Quarantine" IS NULL
EOT;
        $stmt = $this->db->prepare($select);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
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
    "identificationQualifier","DwCTypeStatus","identifiedBy","dateIdentified","identificationRemarks",
    "scientificName","kingdom","phylum","class","order","family","genus","specificEpithet","infraspecificEpithet",
    "taxonRank","scientificNameAuthorship","nomenclaturalStatus", "event_remarks", "bush_blitz_expedition"
FROM public.core
WHERE "CoreID" IN ($values) AND "Quarantine" IS NULL
EOT;
            $stmt = $this->db->prepare($select);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                fputcsv($this->units, $row);
            }
            return TRUE;
        }
        else
            return FALSE;
    }

    /**
     * Downloads Identification History records
     * 
     * @param type $values
     * @return boolean
     */
    public function getDeterminationHistory($values) 
    { 
        $select = <<<EOT
SELECT count(*)
FROM public.determinationhistory
WHERE "CoreID" IN ($values)
EOT;
        $stmt = $this->db->prepare($select);
        $stmt->execute();
        $num = $stmt->fetchColumn();
        if ($num > 0) {
            
            $select = <<<EOT
SELECT c."CoreID", e."identificationID", e."DwCIdentificationQualifier", e."identifiedBy", 
    e."dateIdentified", e."identificationRemarks", e."scientificName", e."scientificNameAuthorship", 
    e."nomenclaturalStatus"
FROM core c
JOIN determinationhistory e ON c."CoreID"=e."CoreID"
WHERE c."CoreID" IN ($values)
EOT;
            $query = $this->db->query($select);
            while ($row = $query->fetch(3)) {
                fputcsv($this->dethist, $row);
            }
            return TRUE;
        }
        else 
            return FALSE;
    }
    
    public function zipItUp($filename) {
        chdir('archive');
        $zip = new ZipArchive;
        if ($zip->open("$filename.zip", ZipArchive::CREATE) === TRUE) {
            $zip->addFile('unit.csv');
            if (file_exists('determinationhistory.csv')) {
                $zip->addFile('determinationhistory.csv');
            }
            $zip->addFile('meta.xml');
            $zip->addFile('eml.xml');
            $zip->close();

            // delete csv files
            unlink('unit.csv');
            if (file_exists('determinationhistory.csv')) {
                unlink('determinationhistory.csv');
            }
        }
         else {
            echo 'Failed...';
        }
        chdir('..');
    }
}

?>
