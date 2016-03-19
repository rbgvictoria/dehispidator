<?php

class AvhDb {
    private $db;
    private $data;
    private $config;
    private $corexpathcols;
    private $extxpathcols;
    var $coredbfields;
    private $extdbfields;
    var $coorIDs;
    var $catalogNumbers;
    var $coredata;
    var $extensiondata;
    
    public function __construct($db, $data, $config) {
        $this->db = $db;
        $this->data = $data;
        $this->config = $config;
        $this->coredata = array();
        $this->extensiondata = array();
        $this->coorIDs = array();
        $this->catalogNumbers = array();

        $insertError = 'INSERT INTO public.dberrors ("table", "catalogNumber", "sqlStateErrorCode",
              "driverSpecificErrorCode", "driverSpecificErrorMessage")
            VALUES (?, ?, ?, ?, ?)';
        $this->stmtError = $this->db->prepare($insertError);

        $this->prepareCoreData();
        $this->prepareExtensionData();
    }
    
    public function prepareCoreData() {
        /*
         * Get the column names from the data.
         */
        $xpathcols = array();
        foreach ($this->data['core'] as $unit) {
            foreach ($unit as $item)
                $xpathcols[] = $item['column'];
        }
        $xpathcols = array_unique($xpathcols);
        sort($xpathcols);
        
        /*
         * Get the XPATHs and the friendly column names from the configuration.
         */
        $this->corexpathcols = array();
        $this->coredbfields = array();
        foreach ($this->config['core'] as $name) {
            /*
             * Check whether the columns from the configuration file are present.
             * in the data
             */
            $this->coredbfields[] = $name[1];
            if (in_array($name[0], $xpathcols))
                $this->corexpathcols[] = $name[0];
            else 
                $this->corexpathcols[] = FALSE;
        }

        /*
         * This fixes the Canberra Source Institution and Unit IDs. Shifting the
         * first two items of both the XPATH column name and friendly column name
         * arrays, Accession Catalogue and Accession Number will be used instead
         * of Source Institution ID and Unit ID.
         */
        /*if (in_array('Unit/SpecimenUnit/Accessions/AccessionNumber', $xpathcols) 
                && in_array('Unit/SpecimenUnit/Accessions/AccessionCatalogue', $xpathcols)) {
            $key = array_search('UnitID', $this->coredbfields);
            if ($key !== FALSE)
                $this->corexpathcols[$key] = 'Unit/SpecimenUnit/Accessions/AccessionNumber';
            else {
                $key = array_search('catalogNumber', $this->coredbfields);
                if ($key !== FALSE)
                    $this->corexpathcols[$key] = 'Unit/SpecimenUnit/Accessions/AccessionNumber';
            }

            $key = array_search('SourceInstitutionID', $this->coredbfields);
            if ($key !== FALSE)
                $this->corexpathcols[$key] = 'Unit/SpecimenUnit/Accessions/AccessionCatalogue';
            else {
                $key = array_search('collectionCode', $this->coredbfields);
                if ($key !== FALSE)
                    $this->corexpathcols[$key] = 'Unit/SpecimenUnit/Accessions/AccessionCatalogue';
            }
        }
        */
        
        /*
         * Create the columns array and add the header row to the CSV array.
         */
        $cols = $this->corexpathcols;
        
        foreach ($this->data['core'] as $unit) {
            $row = array();
            
            /*
             * Create arrays with column name and values for each row.
             */
            $rowcols = array();
            $values = array();
            foreach ($unit as $item) {
                $rowcols[] = $item['column'];
                $values[] = $item['value'];
            }
            /*
             * For each column in the output CSV, find the array key in the column name
             * array for the input row, then store the value for the item with that
             * key in the input values array in the output row array. If the column
             * is not found an empty string is stored.
             */
            foreach ($cols as $col) {
                $key = array_search($col, $rowcols);
                if ($key !== FALSE) {
                    $value = $values[$key];
                    $row[] = $value;
                }
                else
                    $row[] = NULL;
            }
            
            /*
             * Implode the row array and add to the CSV array.
             */
            $this->coredata[] = $row;
        }
    }
    
    public function uploadCoreData($reindex=FALSE) {
        /*
         * Find keys for catalogNumber and institutionID columns in config.
         */
        $unitidcol = array_search('catalogNumber', $this->coredbfields);
        $sourceinstitutionidcol = array_search('collectionCode', $this->coredbfields);
        
        /*
         * Get the highest CoreID already in the database
         */
        $max = $this->db->prepare('SELECT MAX("CoreID") FROM public.core');
        $max->execute();
        $n = $max->fetchColumn(0);
        if (!$n) $n = 0;
        
        /*
         * Prepared statements
         */
        
        if (!$reindex) {
            $stmtCount = $this->db->prepare('SELECT count(*)
                    FROM public.core
                    WHERE "collectionCode"=? AND "catalogNumber"=?');

            $stmtCoreID = $this->db->prepare('SELECT "CoreID"
                    FROM public.core
                    WHERE "collectionCode"=? AND "catalogNumber"=?');

            $stmtDeleteFromCore = $this->db->prepare('DELETE FROM public.core WHERE "CoreID"=?');
            $stmtDeleteFromExtension = $this->db->prepare('DELETE FROM public.determinationhistory WHERE "CoreID"=?');
        }
        
        $fields = $this->coredbfields;
        array_unshift($fields, 'CoreID');
        $values = array();
        foreach ($fields as $key=>$field) {
            $values[] = '?';
            $fields[$key] = '"' . trim($field) . '"';
        }
        
        // Add time loaded field
        $fields[] = '"TimeLoaded"';
        $values[] = '?';
        
        $sql = 'INSERT INTO public.core (' . implode(', ', $fields) . ') ' . 'VALUES (' . implode(', ', $values) . ')';
        $stmtInsert = $this->db->prepare($sql);

        $i = 0;
        foreach ($this->coredata as $row) {
            /*
             * Find catalogNumber and institutionID in data
             */
            $catalogNumber = $row[$unitidcol];
            $institutionCode = $row[$sourceinstitutionidcol];
            
            /*
             * Check if record is already in AVH cache
             */
            if (!$reindex) {
                $stmtCount->execute(array($institutionCode, $catalogNumber));
                $count = $stmtCount->fetch(PDO::FETCH_NUM);
                if ($count[0] > 0) {
                    $stmtCoreID->execute(array($institutionCode, $catalogNumber));
                    $col = $stmtCoreID->fetch(PDO::FETCH_OBJ);
                    $coreid = $col->CoreID;

                    /*
                     * Delete the record with this CoreID
                     */
                    $stmtDeleteFromCore->execute(array($coreid));
                    $stmtDeleteFromExtension->execute(array($coreid));
                }
                else {
                    $n++;
                    $coreid = $n;
                }
            }
            else {
                $n++;
                $coreid = $n;
            }
            $this->coorIDs[] = $coreid;
            $this->catalogNumbers[] = $institutionCode . ' ' . $catalogNumber;
            
            array_unshift($row, $coreid);
            
            // Add time loaded value
            $row[] = date('Y-m-d H:i:s');
            
            $stmtInsert->execute($row);
            if ($stmtInsert->errorCode() != '00000') {
                $error = $stmtInsert->errorInfo();
                array_unshift($error, $institutionCode . ' ' . $catalogNumber);
                array_unshift($error, 'core');
                
                $this->stmtError->execute($error);
                
            }
        }
    }
    
    function prepareExtensionData() {
        /*
         * Get the column names from the data.
         */
        $xpathcols = array();
        foreach ($this->data['extension'] as $unit) {
            foreach ($unit as $item)
                $xpathcols[] = $item['column'];
        }
        $xpathcols = array_unique($xpathcols);
        sort($xpathcols);
        
        /*
         * Get the XPATHs and the friendly column names from the configuration.
         */
        $this->extxpathcols = array();
        $this->extdbfields = array();
        foreach ($this->config['extension'] as $name) {
            /*
             * Check whether the columns from the configuration file are present.
             * in the data
             */
            $this->extdbfields[] = $name[1];
            if (in_array($name[0], $xpathcols))
                $this->extxpathcols[] = $name[0];
            else 
                $this->extxpathcols[] = FALSE;
        }
        
        $cols = $this->extxpathcols;

        foreach ($this->data['extension'] as $unit) {
            $row = array();
            
            /*
             * Create arrays with column name and values for each row.
             */
            $rowcols = array();
            $values = array();
            foreach ($unit as $item) {
                $rowcols[] = $item['column'];
                $values[] = $item['value'];
            }
            /*
             * For each column in the output CSV, find the array key in the column name
             * array for the input row, then store the value for the item with that
             * key in the input values array in the output row array. If the column
             * is not found an empty string is stored.
             */
            foreach ($cols as $col) {
                $key = array_search($col, $rowcols);
                if ($key !== FALSE) {
                    $value = $values[$key];
                    $row[] = $value;
                }
                else
                    $row[] = NULL;
            }
            
            /*
             * Implode the row array and add to the CSV array.
             */
            $this->extensiondata[] = $row;
        }
    }
    
    public function uploadExtensionData($reindex=FALSE) {
        /*
         * Prepared statements
         */
        
        $fields = $this->extdbfields;
        array_shift($fields);
        array_shift($fields);
        array_unshift($fields, 'CoreID');
        $values = array();
        foreach ($fields as $key=>$field) {
            $values[] = '?';
            $fields[$key] = '"' . trim($field) . '"';
        }
        $sql = 'INSERT INTO public.determinationhistory (' . implode(', ', $fields) . ') ' . 'VALUES (' . implode(', ', $values) . ')';
        $stmtInsert = $this->db->prepare($sql);

        $i = 0;
        foreach ($this->extensiondata as $row) {
            $catalognumber = $row[0] . ' ' . $row[1];
            $key = array_search($catalognumber, $this->catalogNumbers);
            if ($key !== FALSE)
                $coreid = $this->coorIDs[$key];
            else $coreid = FALSE;
            
            array_shift($row);
            array_shift($row);
            array_unshift($row, $coreid);
            $stmtInsert->execute($row);
            if ($stmtInsert->errorCode() != '00000') {
                $error = $stmtInsert->errorInfo();
                array_unshift($error, $catalognumber);
                array_unshift($error, 'extension');
                $this->stmtError->execute($error);
            }
        }
    }
    
    
}



?>
