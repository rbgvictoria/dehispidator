<?php
require_once('pgpdoconnect.php');
require_once('biocasediagnostic.php');
require_once('hispid5tocsv.php');
require_once('avhdb.php');
date_default_timezone_set('Australia/Melbourne');

//$dir = 'C:/Users/nklaze/Documents/NetBeansProjects/dehispidator/xml/mel_depth';
$dir = 'C:\\Users\\nklaze\\Documents\\Git\\dehispidator\\xml\\bushblitz';
if (is_dir($dir)) {
    if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== false) {
            if (strlen($file) > 2 && !is_dir($dir . '/' . $file)) {
                $xml = file_get_contents($dir . '/' . $file);
                uploadBiocaseFile($pgdb, $xml, FALSE, TRUE);
                echo $file . "\n";
            }
        }
        closedir($dh);
    }
}

function uploadBiocaseFile($db, $xml, $reindex=FALSE, $diagnostics=FALSE, $resourceid=FALSE){
    $doc = new DOMDocument();

    $doc->loadXML(utf8_for_xml($xml));

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
    
    if($diagnostics && $resourceid) {
        $diag = new BiocaseDiagnostic($db, $doc, $resourceid);
        $diag->diagnose();
    }
    
    $data = array();
    $dehispidator = new Hispid5ToCsv();
    $data['core'] = $dehispidator->parseHISPID5($doc);
    
    $data['extension'] = $dehispidator->DeterminationHistory($doc);
    
    $upload = new AvhDb($db, $data, $config);
    $upload->uploadCoreData($reindex);
}

function utf8_for_xml($string) {
    return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
}

