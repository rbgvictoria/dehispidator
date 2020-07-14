<?php

require_once 'includes/uuid.php';

/**
 * Description of PgSqlToMySql
 *
 * @author Niels.Klazenga <Niels.Klazenga at rbg.vic.gov.au>
 */
class PgSqlToMySql 
{
    
    protected $mydb;
    protected $pgdb;
    
    protected $namespace;
    protected $insertOccurrenceStmt;
    protected $insertIdentificationStmt;
    protected $insertResourceRelationshipStmt;
    protected $insertLoanStmt;
    
    public function __construct($mydb, $pgdb)
    {
        $this->mydb = $mydb;
        $this->pgdb = $pgdb;
        $this->insertOccurrenceStmt = $this->createInsertOccurrenceStmt();
        $this->insertIdentificationStmt = $this->createInsertIdentificationStmt();
        $this->insertResourceRelationshipStmt = $this->createInsertResourceRelationshipStmt();
        $this->insertLoanStmt = $this->createLoanStmt();
        $this->namespace = '0ce24204-075c-11ea-8805-005056b12e73';

    }
    
    protected function createInsertOccurrenceStmt()
    {
        $fields = array_keys((array) new Occurrence());
        $values = [];
        foreach ($fields as $key => $field) {
            $fields[$key] = "`$field`";
            $values[] = '?';
        }
        $fields = implode(',', $fields);
        $values = implode(',', $values);
        $sql = "INSERT INTO occurrence ($fields) 
                VALUES ($values)";
        return $this->mydb->prepare($sql);
    }
    
    protected function createInsertIdentificationStmt()
    {
        $fields = array_keys((array) new Identification());
        $values = [];
        foreach ($fields as $key => $field) {
            $fields[$key] = "`$field`";
            $values[] = '?';
        }
        $fields = implode(',', $fields);
        $values = implode(',', $values);
        $sql = "INSERT INTO identification_history ($fields) 
                VALUES ($values)";
        return $this->mydb->prepare($sql);
    }
    
    protected function createInsertResourceRelationshipStmt()
    {
        $fields = array_keys((array) new ResourceRelationship());
        $values = [];
        foreach ($fields as $key => $field) {
            $fields[$key] = "`$field`";
            $values[] = '?';
        }
        $fields = implode(',', $fields);
        $values = implode(',', $values);
        $sql = "INSERT INTO resource_relationship ($fields) 
                VALUES ($values)";
        return $this->mydb->prepare($sql);
    }
    
    protected function createLoanStmt()
    {
        $fields = array_keys((array) new Loan());
        $values = [];
        foreach ($fields as $key => $field) {
            $fields[$key] = "`$field`";
            $values[] = '?';
        }
        $fields = implode(',', $fields);
        $values = implode(',', $values);
        $sql = "INSERT INTO loan ($fields) 
                VALUES ($values)";
        return $this->mydb->prepare($sql);
    }
    
    public function getOccurrenceData($ids)
    {
        $sql = <<<EOT
SELECT "CoreID" as id,
  "modified",
  "institutionCode",
  "collectionCode",
  coalesce("basisOfRecord", 'PreservedSpecimen') AS "basisOfRecord",
  coalesce("occurrenceID","institutionCode"||':'||"collectionCode"||':'||"catalogNumber") AS "occurrenceID",
  "catalogNumber",
  CASE WHEN "recordNumber"!='s.n.' THEN "recordNumber" ELSE NULL END as "recordNumber",
  replace("recordedBy", ';', ' |') as "recordedBy",
  "lifeStage",
  replace("reproductiveCondition", '; ', ' | ') as "reproductiveCondition",
  "establishmentMeans",
  "preparations",
  "associatedSequences",
  "associatedTaxa",
  "occurrenceRemarks",
  "eventDate",
  "verbatimEventDate",
  "habitat",
  "continent",
  "waterBody",
  "islandGroup",
  "island",
  "country",
  "countryCode",
  "stateProvince",
  "county",
  "locality",
  "verbatimLocality",
  "minimumElevationInMeters",
  coalesce("maximumElevationInMeters", "minimumElevationInMeters") as "maximumElevationInMeters",
  "verbatimElevation",
  "minimumDepthInMeters",
  coalesce("maximumDepthInMeters", "minimumDepthInMeters") as "maximumDepthInMeters",
  "verbatimDepth",
  "locationRemarks",
  "decimalLatitude",
  "decimalLongitude",
  "geodeticDatum",
  "coordinateUncertaintyInMeters",
  "coordinatePrecision",
  "verbatimCoordinates",
  "verbatimLatitude",
  "verbatimLongitude",
  "verbatimCoordinateSystem",
  "verbatimSRS",
  "georeferencedBy",
  "georeferencedDate",
  "georeferenceProtocol",
  "georeferenceSources",
  "georeferenceVerificationStatus",
  "georeferenceRemarks",
  "identificationID",
  "identificationQualifier",
  "typeStatus",
  "identifiedBy",
  "dateIdentified",
  "identificationRemarks",
  "scientificName",
  "kingdom",
  "phylum",
  "class",
  "order",
  "family",
  "genus",
  "specificEpithet",
  "infraspecificEpithet",
  "taxonRank",
  "scientificNameAuthorship",
  "nomenclaturalStatus",
  "event_remarks",
  'Present' as "occurrenceStatus",
  replace("previousIdentifications", ';', ' |') as "identificationHistory",
  "bush_blitz_expedition" as parent_event_id
FROM public.core
WHERE "CoreID" IN ($ids)
EOT;
        $query = $this->pgdb->query($sql);
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $row) {
                foreach ($row as $key => $value) {
                    if (substr($value, 0, 1) == '"') {
                        $row[$key] = '"' . $value;
                    }
                    if (substr($value, -1) == '"') {
                        $row[$key] .= '"';
                    }
                }
                $this->insertOccurrenceStmt->execute(array_values($row));
                if ($this->insertOccurrenceStmt->errorCode() !== '00000') {
                    $handle = fopen('log/error.log', 'a');
                    $error = $this->insertOccurrenceStmt->errorInfo();
                    array_unshift($error, $row['id']);
                    array_unshift($error, 'occurrence');
                    array_unshift($error, date('Y-m-d H:i:s'));
                    fputcsv($handle, $error);
                    fclose($handle);
                }
            }
        }
    }
    
    public function getIdentificationData($ids)
    {
        $sql = <<<EOT
SELECT e."DeterminationHistoryID",
  c."CoreID",
  e."identificationID",
  coalesce(c."occurrenceID",c."institutionCode"||':'||c."collectionCode"||':'||c."catalogNumber"),
  e."identifiedBy",
  e."dateIdentified",
  e."DwCIdentificationQualifier",
  e."identificationRemarks",
  e."scientificName",
  e."scientificNameAuthorship"
FROM core c
JOIN determinationhistory e ON c."CoreID"=e."CoreID"
WHERE c."CoreID" IN ($ids)
EOT;
        $query = $this->pgdb->query($sql);
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            foreach ($result as $row) {
                foreach ($row as $key => $value) {
                    if (substr($value, 0, 1) == '"') {
                        $row[$key] = ' ' + $value;
                    }
                }
                $this->insertIdentificationStmt->execute(array_values($row));
                if ($this->insertIdentificationStmt->errorCode() !== '00000') {
                    $handle = fopen('log/error.log', 'a');
                    $error = $this->insertIdentificationStmt->errorInfo();
                    array_unshift($error, $row['id']);
                    array_unshift($error, 'identification_history');
                    array_unshift($error, date('Y-m-d H:i:s'));
                    fputcsv($handle, $error);
                    fclose($handle);
                }
            }
        }
    }

    public function getResourceRelationshipData($ids)
    {
        $sql = <<<EOT
SELECT "CoreID", coalesce("occurrenceID", "institutionCode"||':'||"collectionCode"||':'||"catalogNumber") as occurrence_id, 
  "institutionCode", "collectionCode", "catalogNumber",
  "ExHerb", "ExHerbCatalogueNumber"
FROM core
WHERE "CoreID" IN ($ids) AND "ExHerbCatalogueNumber" IS NOT NULL
EOT;
        $query = $this->pgdb->query($sql);
        $result = $query->fetchAll(5);
        if ($result) {
            foreach ($result as $row) {
                $name = $row->catalogNumber . '_' . $row->ExHerbCatalogueNumber;
                $uuid = UUID::v5($this->namespace, $name);

                $rel = new ResourceRelationship();
                $rel->id = $uuid;
                $rel->core_id = $row->CoreID;
                $rel->resource_relationship_id = $uuid;
                $rel->resource_id = $row->CoreID;
                $rel->related_resource_id = $row->ExHerb . ':' . $row->ExHerbCatalogueNumber;
                $rel->relationship_of_resource = 'duplicateOf';
                $rel->relationship_according_to = $row->institutionCode;
                $rel->occurrence_id = $row->occurrence_id;

                $this->insertResourceRelationshipStmt->execute(array_values((array) $rel));
                if ($this->insertResourceRelationshipStmt->errorCode() !== '00000') {
                    $handle = fopen('log/error.log', 'a');
                    $error = $this->insertResourceRelationshipStmt->errorInfo();
                    array_unshift($error, $row->id);
                    array_unshift($error, 'resource_relationship');
                    array_unshift($error, date('Y-m-d H:i:s'));
                    fputcsv($handle, $error);
                    fclose($handle);
                }

            }
        }
    }

    public function getLoansData($ids)
    {
        $sql = <<<EOT
SELECT "catalogNumber" as id, coalesce("occurrenceID", "institutionCode"||':'||"collectionCode"||':'||"catalogNumber") as occurrence_id,
  "LoanIdentifier" as loan_identifier, "LoanDestination" as loan_destination, "LoanDate" as loan_date
FROM core
WHERE "CoreID" IN ($ids) AND "LoanIdentifier" IS NOT NULL
EOT;
        $query = $this->pgdb->query($sql);
        $result = $query->fetchAll(5);
        if ($result) {
            foreach ($result as $row) {
                $row->id = UUID::v5($this->namespace, $row->id . '_' . $row->loan_identifier);
            }

            $this->insertLoanStmt->execute(array_values((array) $row));
            if ($this->insertLoanStmt->errorCode() !== '00000') {
                $handle = fopen('log/error.log', 'a');
                $error = $this->insertLoanStmt->errorInfo();
                array_unshift($error, $row->id);
                array_unshift($error, 'loan');
                array_unshift($error, date('Y-m-d H:i:s'));
                fputcsv($handle, $error);
                fclose($handle);
            }

        }
    }
}

class Occurrence {
    var $id = null;
    var $modified = null;
    var $institution_code = null;
    var $collection_code = null;
    var $basis_of_record = null;
    var $occurrence_id = null;
    var $catalog_number = null;
    var $record_number = null;
    var $recorded_by = null;
    var $life_stage = null;
    var $reproductive_condition = null;
    var $establishment_means = null;
    var $preparations  = null;
    var $associated_sequences = null;
    var $associated_taxa = null;
    var $occurrence_remarks  = null;
    var $event_date = null;
    var $verbatim_event_date = null;
    var $habitat = null;
    var $continent = null;
    var $water_body = null;
    var $island_group = null;
    var $island = null;
    var $country = null;
    var $country_code = null;
    var $state_province = null;
    var $county = null;
    var $locality = null;
    var $verbatim_locality = null;
    var $minimum_elevation_in_meters = null;
    var $maximum_elevation_in_meters = null;
    var $verbatim_elevation = null;
    var $minimum_depth_in_meters = null;
    var $maximum_depth_in_meters = null;
    var $verbatim_depth = null;
    var $location_remarks = null;
    var $decimal_latitude = null;
    var $decimal_longitude = null;
    var $geodetic_datum = null;
    var $coordinate_uncertainty_in_meters = null;
    var $coordinate_precision = null;
    var $verbatim_coordinates = null;
    var $verbatim_latitude = null;
    var $verbatim_longitude = null;
    var $verbatim_coordinate_system = null;
    var $verbatim_srs = null;
    var $georeferenced_by = null;
    var $georeferenced_date = null;
    var $georeference_protocol = null;
    var $georeference_sources = null;
    var $georeference_verification_status = null;
    var $georeference_remarks = null;
    var $indentification_id = null;
    var $identification_qualifier = null;
    var $type_status = null;
    var $identified_by = null;
    var $date_identified = null;
    var $identification_remarks = null;
    var $scientific_name = null;
    var $kingdom = null;
    var $phylum = null;
    var $class = null;
    var $order = null;
    var $family = null;
    var $genus = null;
    var $specific_epithet = null;
    var $infraspecific_epithet = null;
    var $taxon_rank = null;
    var $scientific_name_authorship = null;
    var $nomenclatural_status = null;
    var $event_remarks = null;
    var $occurrence_status = null;
    var $previous_identifications = null;
    var $parent_event_id = null;
}

class Identification {
    var $id = null;
    var $core_id = null;
    var $identification_id = null;
    var $occurrence_id = null;
    var $identified_by = null;
    var $date_identified = null;
    var $identification_qualifier = null;
    var $identification_remarks = null;
    var $scientific_name = null;
    var $scientific_name_authorship = null;
}

class ResourceRelationship
{
    var $id = null;
    var $core_id = null;
    var $resource_relationship_id = null;
    var $resource_id = null;
    var $related_resource_id = null;
    var $relationship_of_resource = null;
    var $relationship_according_to = null;
    var $occurrence_id = null;
}

class Loan {
    var $id = null;
    var $occurrence_id = null;
    var $loan_identifier = null;
    var $loan_destination = null;
    var $loan_date = null;
}
