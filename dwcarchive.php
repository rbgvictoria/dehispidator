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
            $firstrow = array("ID","institutionCode","collectionCode","catalogNumber","occurrenceID","basisOfRecord","preparations",
                "modified","recordedBy","AdditionalCollectors","recordNumber","eventDate","verbatimEventDate","country",
                "countryCode","stateProvince","locality","GeneralisedLocality","NearNamedPlaceRelationship","decimalLatitude",
                "decimalLongitude","verbatimCoordinates","geodeticDatum","coordinateUncertaintyInMeters","georeferencedBy",
                "georeferencedProtocol","minimumElevationInMeters","maximumElevationInMeters","verbatimElevation","minimumDepthInMeters",
                "maximumDepthInMeters","verbatimDepth","habitat","occurrenceRemarks","scientificName","kingdom","phylum","class",
                "order","family","genus","specificEpithet","taxonRank","infraspecificEpithet","CultivarName","scientificNameAuthorship",
                "nomenclaturalStatus","identificationQualifier","IdentificationQualifierInsertionPoint","DwCIdentificationQualifier",
                "ScientificNameAddendum","DeterminerRole","identifiedBy","dateIdentified","VerbatimIdentificationDate","identificationRemarks",
                "previousIdentifications","typeStatus","TypifiedName","DoubtfulFlag","Verifier","VerificationDate","VerificationNotes",
                "DwCTypeStatus","ExHerb","ExHerbCatalogueNumber","DuplicatesDistributedTo","LoanIdentifier","LoanDestination",
                "AustralianHerbariumRegion","IBRARegion","IBRASubregion","Phenology","CultivatedOccurrence","NaturalOccurrence",
                "establishmentMeans","verbatimLatitude","verbatimLongitude","coordinatePrecision","verbatimCoordinateSystem",
                "verbatimSRS","locationRemarks","associatedTaxa","ABCDAssemblageID","HISPIDSubstrate","HISPIDSoil",
                "HISPIDVegetation","HISPIDAspect","HISPIDMiscellaneousNotes","HISPIDFrequency","HISPIDHabit","HISPIDVoucher","county",
                "continent","subclass","georeferencedDate","georeferenceSources","georeferenceVerificationStatus","georeferenceRemarks",
                "identificationID", "lifeStage", "associatedSequences", "waterBody", "islandGroup", "island");
            fputcsv($handle, $firstrow);

            $select = <<<EOT
SELECT "CoreID" AS "ID", "institutionCode", "collectionCode", "catalogNumber",
    "occurrenceID", "basisOfRecord", "preparations", "modified", "recordedBy", "AdditionalCollectors", 
    "collectorsFieldNumber", "eventDate", "verbatimEventDate", "country", "countryCode", "stateProvince", "locality", 
    "GeneralisedLocality", "NearNamedPlaceRelationship", "decimalLatitude", "decimalLongitude", "verbatimCoordinates", 
    "geodeticDatum", "coordinateUncertaintyInMeters", "georeferencedBy", "georeferencedProtocol", 
    "minimumElevationInMeters", "maximumElevationInMeters", "verbatimElevation", "minimumDepthInMeters", 
    "maximumDepthInMeters", "verbatimDepth", "habitat", "occurrenceRemarks", "scientificName", "kingdom", 
    "phylum", "class", "order", "family", "genus", "specificEpithet", "taxonRank", "infraspecificEpithet", 
    "CultivarName", "scientificNameAuthorship", "nomenclaturalStatus", "identificationQualifier", 
    "IdentificationQualifierInsertionPoint", "DwCIdentificationQualifier", "ScientificNameAddendum", "DeterminerRole", 
    "identifiedBy", "dateIdentified", "VerbatimIdentificationDate", "identificationRemarks", "previousIdentifications", "typeStatus", 
    "TypifiedName", "DoubtfulFlag", "Verifier", "VerificationDate", "VerificationNotes", "DwCTypeStatus", "ExHerb", 
    "ExHerbCatalogueNumber", "DuplicatesDistributedTo", "LoanIdentifier", "LoanDestination", "Australian Herbarium Region", 
    "IBRARegion", "IBRASubregion", "Phenology", "CultivatedOccurrence", "NaturalOccurrence", "establishmentMeans",
    "verbatimLatitude","verbatimLongitude","coordinatePrecision","verbatimCoordinateSystem","verbatimSRS",
    "locationRemarks","associatedTaxa","abcd_AssemblageID","Substrate","Soil","Vegetation","Aspect",
    "abcd_UnitNotes","hispid_Frequency","hispid_Habit","hispid_Voucher","county","continent","subclass","georeferencedDate",
    "georeferenceSources","georeferenceVerificationStatus","georeferenceRemarks","identificationID","lifeStage","associatedSequences",
    "waterBody","islandGroup",island
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
        if (in_array($this->operator, array('IS NULL', 'IS NOT NULL'))) {
            $where = "\"$this->field\" $this->operator";
        }
        else {
            $where = "\"$this->field\"$this->operator'$this->value'";
        }
        
        // determination history
        $select = <<<EOT
SELECT count(*)         
FROM core c
JOIN determinationhistory e ON c."CoreID"=e."CoreID"
WHERE $where;
EOT;
        $stmt = $this->db->prepare($select);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            $handle = fopen('archive/determinationhistory.csv', 'w');
            fwrite($handle, '"CoreID","scientificName","kingdom","phylum","class","order","family","genus","specificEpithet","taxonRank","infraspecificEpithet","CultivarName","scientificNameAuthorship","nomenclaturalStatus","identificationQualifier","IdentificationQualifierInsertionPoint","DwCIdentificationQualifier","ScientificNameAddendum","DeterminerRole","identifiedBy","dateIdentified","VerbatimIdentificationDate","identificationRemarks","identificationID"' . "\n");
            
            $select = <<<EOT
SELECT c."CoreID", e."scientificName", e."kingdom", e."phylum", e."class", e."order", e."family", e."genus", 
    e."specificEpithet", e."taxonRank", e."infraspecificEpithet", e."CultivarName", e."scientificNameAuthorship", 
    e."nomenclaturalStatus", e."identificationQualifier", e."IdentificationQualifierInsertionPoint", 
    e."DwCIdentificationQualifier", e."ScientificNameAddendum", e."DeterminerRole", e."identifiedBy", 
    e."dateIdentified", e."VerbatimIdentificationDate", e."identificationRemarks",e."identificationID"
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
            if ($this->extension) $zip->addFile('determinationhistory.csv');
            $zip->addFile('meta.xml');
            $zip->addFile('eml.xml');
            $zip->close();

            // delete csv files
            unlink('unit.csv');
            if ($this->extension) unlink('determinationhistory.csv');
        }
         else {
            echo 'Failed...';
        }
        chdir('..');
    }
}

?>
