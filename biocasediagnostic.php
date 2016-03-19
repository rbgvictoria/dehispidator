<?php

class BiocaseDiagnostic {
    private $db;
    private $doc;
    private $resourceid;
    
    public function __construct($db, $doc, $resourceid) {
        $this->db = $db;
        $this->doc = $doc;
        $this->resourceid = $resourceid;
    }
    
    public function diagnose() {
        
        $responseArray = array();
        $diagnosticArray = array();
        
        /*
         * Prepared statements
         */
        $insert = 'INSERT INTO biocaseresponse ("biocaseResponseID", "resourceID", "xmlSchema", "source", "sendTime", 
                "recordCount", "recordDropped", "recordStart", "totalSearchHits")
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmtResp = $this->db->prepare($insert);
        
        $insert = 'INSERT INTO biocasediagnostic ("biocaseResponseID", "severity", "text")
            VALUES (?, ?, ?)';
        $stmtDiag = $this->db->prepare($insert);
        
        $select = 'SELECT MAX("biocaseResponseID") FROM biocaseresponse';
        $stmtMax = $this->db->prepare($select);
        $stmtMax->execute();
        $max = $stmtMax->fetch(PDO::FETCH_NUM);
        $id = $max[0] + 1;
        $responseArray[0] = $id;
        
        $responseArray[1] = $this->resourceid;
        
        $list = $this->doc->getElementsByTagName('response');
        if ($list->length) {
            $schema = FALSE;
            if ($list->item(0)->getAttribute('xmlns:hispid'))
                $schema = $list->item(0)->getAttribute('xmlns:hispid');
            elseif ($list->item(0)->getAttribute('xmlns:abcd'))
                $schema = $list->item(0)->getAttribute('xmlns:abcd');
            $responseArray[2] = $schema;
        }
        else
            $responseArray[2] = NULL;

        $list = $this->doc->getElementsByTagName('source');
        if ($list->length) 
            $responseArray[3] = $list->item(0)->nodeValue;
        else
            $responseArray[3] = NULL;

        $list = $this->doc->getElementsByTagName('sendTime');
        if ($list->length)
            $responseArray[4] = $list->item(0)->nodeValue;
        else
            $responseArray[4] = NULL;

        $list = $this->doc->getElementsByTagName('content');
        if ($list->length) {
            $content = $list->item(0);
            $responseArray[5] = $content->getAttribute('recordCount');
            $responseArray[6] = $content->getAttribute('recordDropped');
            $responseArray[7] = $content->getAttribute('recordStart');
            $responseArray[8] = $content->getAttribute('totalSearchHits');
        }
        else {
            $responseArray[5] = NULL;
            $responseArray[6] = NULL;
            $responseArray[7] = NULL;
            $responseArray[8] = NULL;
        }
        
        $stmtResp->execute($responseArray);
        if ($stmtResp->errorCode() != '00000')
            print_r($stmtResp->errorInfo());

        $list = $this->doc->getElementsByTagName('diagnostic');
        if ($list->length) {
            foreach ($list as $item) {
                $severity = $item->getAttribute('severity');
                if (in_array($severity, array('WARNING', 'ERROR'))) {
                    $diagnosticArray = array(
                        $id,
                        $severity,
                        $item->nodeValue
                    );
                    $stmtDiag->execute($diagnosticArray);
                    if ($stmtDiag->errorCode() != '00000')
                        print_r($stmtDiag->errorInfo());
                }
            }
        }
    
    }
}
?>

