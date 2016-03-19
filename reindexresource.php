<?php

require_once('biocase.php');
require_once('biocasediagnostic.php');
require_once('hispid5tocsv.php');
require_once('avhdb.php');

class ReindexResource {
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
        
        $select = "SELECT resource_id, url, dsa, resource_schema, date_last_queried FROM resource WHERE resource_name=?";
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

        $biocase = new BioCase($this->resourceurl, $this->resourcedsa, $this->resourceschema);
        
        $result = $biocase->biocaseCount();

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($result);

        $list = $doc->getElementsByTagName('count');
        $recordCount = $list->item(0)->nodeValue;

        if ($recordCount) {
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

            while ($offset <= $recordCount) {
                $biocase = new BioCase($this->resourceurl, $this->resourcedsa, $this->resourceschema, $this->resourcelastupdated, $offset, $this->limit);
                $xml = $biocase->bulkHarvest();

                $filename = 'xml/' . $this->resource . '_' . $date . '_' . str_pad($offset, 7, '0', STR_PAD_LEFT) . '.xml';
                file_put_contents($filename, $xml);

                $doc = new DOMDocument('1.0', 'UTF-8');
                $doc->loadXML($xml);
                $diag = new BiocaseDiagnostic($this->db, $doc, $this->resourceid);
                $diag->diagnose();

                if ($this->resource == 'AD') {
                    $doc = new DOMDocument('1.0', 'UTF-8');
                    $xml = substr($xml, strpos($xml, '<DataSet>'));
                    $xml = substr($xml, 0, strpos($xml, '</DataSet>')+  strlen('</DataSets>'));
                    $doc->loadXML($xml);
                }

                $data = array();
                $dehispidator = new Hispid5ToCsv();
                $data['core'] = $dehispidator->parseHISPID5($doc);
                $data['extension'] = $dehispidator->DeterminationHistory($doc);

                $upload = new AvhDb($this->db, $data, $config);
                $upload->uploadCoreData(TRUE);
                $upload->uploadExtensionData();

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
    
    
}


?>
