<?php 

class DwcArchive {
    private $db;
    private $units;
    private $dethist;
    
    public function __construct($db) {
        $this->db = $db;
        $this->units = fopen('archive/unit.csv', 'w');
        $firstrow = array("ID","institutionCode","collectionCode","catalogNumber","occurrenceID","basisOfRecord","preparations",
                "modified","recordedBy","AdditionalCollectors","recordNumber","eventDate","verbatimEventDate","country",
                "countryCode","stateProvince","verbatimLocality","decimalLatitude",
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
        fputcsv($this->units, $firstrow);
        
        $this->dethist = fopen('archive/determinationhistory.csv', 'w');
        fwrite($this->dethist, '"CoreID","scientificName","kingdom","phylum","class","order","family","genus","specificEpithet",
            "taxonRank","infraspecificEpithet","CultivarName","scientificNameAuthorship","nomenclaturalStatus",
            "identificationQualifier","IdentificationQualifierInsertionPoint","DwCIdentificationQualifier",
            "ScientificNameAddendum","DeterminerRole","identifiedBy","dateIdentified","VerbatimIdentificationDate",
            "identificationRemarks","identificationID"' . "\n");

    }

    function getCoreData ($values) {
        $select = <<<EOT
SELECT count(*)
FROM public.core
WHERE "CoreID" IN ($values) AND "Quarantine" IS NULL
EOT;
        $stmt = $this->db->prepare($select);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            $select = <<<EOT
SELECT "CoreID" AS "ID", "institutionCode", "collectionCode", "catalogNumber",
    "occurrenceID", "basisOfRecord", "preparations", "modified", "recordedBy", "AdditionalCollectors", 
    "collectorsFieldNumber", "eventDate", "verbatimEventDate", "country", "countryCode", "stateProvince", "locality", 
    "decimalLatitude", "decimalLongitude", "verbatimCoordinates", 
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
WHERE "CoreID" IN ($values) AND "Quarantine" IS NULL
LIMIT 100000
EOT;
            $stmt = $this->db->prepare($select);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_NUM))
                fputcsv($this->units, $row);

            return TRUE;
        }
        else
            return FALSE;
    }

    function getDeterminationHistory($values) {
        // determination history
        $select = <<<EOT
SELECT count(*)         
FROM public.determinationhistory
WHERE "CoreID" IN ($values)
EOT;
        $stmt = $this->db->prepare($select);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            
            $select = <<<EOT
SELECT e."CoreID", e."scientificName", e."kingdom", e."phylum", e."class", e."order", e."family", e."genus", 
    e."specificEpithet", e."taxonRank", e."infraspecificEpithet", e."CultivarName", e."scientificNameAuthorship", 
    e."nomenclaturalStatus", e."identificationQualifier", e."IdentificationQualifierInsertionPoint", 
    e."DwCIdentificationQualifier", e."ScientificNameAddendum", e."DeterminerRole", e."identifiedBy", 
    e."dateIdentified", e."VerbatimIdentificationDate", e."identificationRemarks",e."identificationID"
FROM public.determinationhistory e
WHERE e."CoreID" IN ($values)
EOT;
            $stmt = $this->db->prepare($select);
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_NUM))
                fputcsv ($this->dethist, $row);
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
            //if ($this->extension) $zip->addFile('determinationhistory.csv');
            $zip->addFile('meta.xml');
            $zip->addFile('eml.xml');
            $zip->close();

            // delete csv files
            // unlink('unit.csv');
            // if ($this->extension) unlink('determinationhistory.csv');
        }
         else {
            echo 'Failed...';
        }
        chdir('..');
    }
}

?>
