<?php

require_once('../pgpdoconnect.php');

$archive = 'BRI_20150419v2';
new Loader($pgdb, $archive);

class Loader {
    private $db;
    private $config;
    private $dwca;
    private $core_file_name;
    private $core_row;
    private $core_defaults;
    private $core_skip_columns;
    private $core_ignore_header_line;
    
    public function __construct($db, $dwca) {
        $this->db = $db;
        $this->loadConfig();
        $this->dwca = $dwca;
        $this->unzipDWCA();
        $this->parseMetaFile();
        $this->loadCore();
        
    }
    
    private function loadConfig() {
        $handle = fopen('config/dbmapping.csv', 'r');
        $this->config = array();
        while (!feof($handle)) {
            $line = fgetcsv($handle);
            if ($line[0])
                $this->config[$line[0]] = $line[1];
        }
    }
    
    private function unzipDWCA() {
        chdir('dwca');
        $zip = new ZipArchive;
        $res = $zip->open("$this->dwca.zip");
        if ($res === TRUE) {
            $zip->extractTo($this->dwca);
            $zip->close();
        } else {
            echo 'failed, code:' . $res;
        }
    }
    
    private function parseMetaFile() {
        $doc = new DOMDocument();
        $doc->load("$this->dwca/meta.xml");
        
        $list = $doc->getElementsByTagName('core');
        if ($list->length) {
            $corefields = array();
            $coreindexes = array();
            $this->core_defaults = array();
            $this->core_row = array();
            $this->core_skip_columns = array();
            $this->core_skip_columns[] = 0;
            
            $this->core_file_name = $list->item(0)->getElementsByTagName('location')->item(0)->nodeValue;
            
            $this->core_ignore_header_line = $list->item(0)->getAttribute('ignoreHeaderLines');
            
            $fields = $list->item(0)->getElementsByTagName('field');
            if ($fields->length) {
                foreach ($fields as $field) {
                    $term = $field->getAttribute('term');
                    $index = $field->getAttribute('index');
                    $default = $field->getAttribute('default');
                    
                    if ($index) {
                        $corefields[] = array(
                            'index' => $index,
                            'default' => $default,
                            'term' => $term
                        );
                        $indexes[] = $index;
                    }
                    else {
                        if (isset($this->config[$term])) 
                            $this->core_defaults[$this->config[$term]] = $default;
                    }
                }
                
                array_multisort($indexes, SORT_ASC, $corefields);
                    
                foreach ($corefields as $field) {
                    if (isset($this->config[$field['term']]))
                        $this->core_row[$this->config[$field['term']]] = $field['default'];
                    else
                        $this->core_skip_columns[] = $field['index'];
                }
                
            }
        }
        
    }
    
    private function loadCore() {
        
        $fields = array_keys($this->core_row);
        if ($this->core_defaults)
            $fields = array_merge($fields, array_keys($this->core_defaults));
        $values = array();
        
        foreach ($fields as $i => $field) {
            $fields[$i] = '"' . $field . '"';
            $values[] = '?';
        }
        
        // INSERT statement
        $fields = implode(',', $fields);
        $values = implode(',', $values);
        $insert = "INSERT INTO core ($fields)
            VALUES ($values)";
        $insStmt = $this->db->prepare($insert);
        
        $handle = fopen("$this->dwca/$this->core_file_name", 'r');
        
        for ($i = 0; $i < $this->core_ignore_header_line; $i++) {
            fgetcsv($handle);
        }
        
        while (!feof($handle)) {
            $row = $this->core_row;
            $columns = array_keys($this->core_row);
            $csv_row = array();
            $line = fgetcsv($handle);
            if ($line[0]) {
                foreach ($line as $index => $value) {
                    if (!in_array($index, $this->core_skip_columns)) {
                        $csv_row[] = ($value) ? $value : NULL;
                    }
                }
                
                foreach ($csv_row as $index => $value) {
                    $row[$columns[$index]] = $value;
                }
            }
            
            if ($this->core_defaults)
                $row = array_merge($row, $this->core_defaults);
            
            // strip off collectionCode from catalogNumber, if present
            if (isset($row['catalogNumber']) && $row['catalogNumber'] &&
                    isset($row['collectionCode']) && $row['collectionCode'] &&
                    substr($row['catalogNumber'], 0, strlen($row['collectionCode'])) == $row['collectionCode'])
                $row['catalogNumber'] = trim (substr ($row['catalogNumber'], strlen($row['collectionCode'])));
            
            $insStmt->execute(array_values($row));
            if ($insStmt->errorCode() != '00000')
                print_r($insStmt->errorInfo());
        }
        
    }
    
}