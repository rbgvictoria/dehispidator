<?php

require_once('biocase.php');
require_once('biocasediagnostic.php');
require_once('hispid5tocsv.php');
require_once('avhdb.php');

class HarvestResource {
    private $db;
    private $resource;
    private $log;
    private $limit;
    private $offset;
    
    private $resourceid;
    private $resourceurl;
    private $resourcedsa;
    private $resourceschema;
    private $resourcelastupdated;
    
    public function __construct($db, $resource, $log, $limit=100, $offset=0) {
        $this->db = $db;
        $this->resource = $resource;
        $this->log = $log;
        $this->limit = $limit;
        $this->offset = $offset;
        
        $select = "SELECT resource_id, url, dsa, resource_schema, to_char(date_last_queried, 'YYYY-MM-DD HH24:MI:SS') as date_last_queried FROM resource WHERE resource_name=?";
        $stmt = $this->db->prepare($select);
        $stmt->execute(array($this->resource));
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        
        $this->resourceid = $row->resource_id;
        $this->resourceurl = $row->url;
        $this->resourcedsa = $row->dsa;
        $this->resourceschema = $row->resource_schema;
        $this->resourcelastupdated = $row->date_last_queried;
    }
    
    public function harvest() {

        $biocase = new BioCase($this->resourceurl, $this->resourcedsa, $this->resourceschema, $this->resourcelastupdated);
        
        
        /*if ($this->resource == 'DNA') {
            $this->resourcelastupdated = substr($this->resourcelastupdated, 0, 10);
        }*/
        $filter = "<greaterThan path=\"/DataSets/DataSet/Units/Unit/DateLastEdited\">$this->resourcelastupdated</greaterThan>";
        
        $result = $biocase->biocaseCountFilter($filter);

        $doc = new DOMDocument('1.0', 'UTF-8');
        
        if ($doc->loadXML($this->utf8_for_xml($result))) {
            //echo $doc->saveXML();
            $list = $doc->getElementsByTagName('count');
            $recordCount = $list->item(0)->nodeValue;

            if ($recordCount > 0) {
                fwrite($this->log, date('Y-m-d H:i:s') . "\t" . str_pad($this->resource . ':', 6, ' ', STR_PAD_RIGHT) . "\t" . $recordCount . "\n");
                $offset = $this->offset;

                $date = date('Y-m-d');

                //get config
                $config = array();
                $config['core'] = array();
                $handle = fopen('config/config_avh_db_core.csv', 'r');
                while (!feof($handle))
                    $config['core'][] = fgetcsv ($handle);
                fclose($handle);

                $config['extension'] = array();
                $handle = fopen('config/config_avh_db_extension.csv', 'r');
                while (!feof($handle))
                    $config['extension'][] = fgetcsv ($handle);
                fclose($handle);

                while ($offset < $recordCount) {
                    $biocase = new BioCase($this->resourceurl, $this->resourcedsa, $this->resourceschema, $this->resourcelastupdated, $offset, $this->limit);
                    $xml = $biocase->biocaseUnits();

                    $doc = new DOMDocument('1.0', 'UTF-8');
                    if ($doc->loadXML($xml)) {
                        $diag = new BiocaseDiagnostic($this->db, $doc, $this->resourceid);
                        $diag->diagnose();

                        $data = array();
                        $dehispidator = new Hispid5ToCsv();
                        $data['core'] = $dehispidator->parseHISPID5($doc);
                        $data['extension'] = $dehispidator->DeterminationHistory($doc);

                        $upload = new AvhDb($this->db, $data, $config);
                        $upload->uploadCoreData();
                        $upload->uploadExtensionData();

                    }
                    else {
                        fwrite($this->log, "\t" . $offset . "\tXML can't be loaded\n");
                        fwrite($this->log, "\tFilter: " . $filter . "\n");
                        $filename = 'xml/' . $this->resource . '_' . $date . '_' . str_pad($offset, 7, '0', STR_PAD_LEFT) . '.xml';
                        file_put_contents($filename, $xml);
                    }
                    $offset += $this->limit;
                }

                $select = "SELECT MAX(modified) FROM core WHERE \"institutionCode\"=?";
                $selStmt = $this->db->prepare($select);
                $selStmt->execute(array($this->resource));
                $r = $selStmt->fetch(PDO::FETCH_NUM);
                $lastModified = $r[0];

                $update = "UPDATE resource
                    SET date_last_queried=?
                    WHERE resource_id=?";
                $updStmt = $this->db->prepare($update);
                $updStmt->execute(array($lastModified, $this->resourceid));
            }
            else
                fwrite($this->log, date('Y-m-d H:i:s') . "\t" . str_pad($this->resource . ':', 6, ' ', STR_PAD_RIGHT) . "\t0\n");
        }
        else 
            fwrite($this->log, date('Y-m-d H:i:s') . "\t" . str_pad($this->resource . ':', 6, ' ', STR_PAD_RIGHT) . "\tProvider can't be accessed\n");
            
    }
    
    private function utf8_for_xml($string) {
        return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
    }


    
    
}


?>
