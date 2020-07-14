<?php
/**
 * AVHHarvester BioCASe Class
 * 
 * Contains functions that run queries on a BioCASe provider and return the result 
 * as a string.
 * 
 * @package     AVHHarvester
 * @subpackage  BioCASe
 * @author      Niels Klazenga
 * @copyright   Copyright (c) 2011-2012, Royal Botanic Gardens Melbourne, 
 *              Council of Heads of Australasian Herbaria (CHAH)
 *  
 */
class BioCase {
    /**
     * $url
     * 
     * URL of the provider
     * 
     * @var string
     */
    var $url;
    /**
     * $dsa
     * 
     * DSA of the resource
     * 
     * @var string
     */
    var $dsa;
    /**
     *
     * @var string
     */
    var $fromdate;
    /**
     *
     * @var string
     */
    var $offset;
    /**
     *
     * @var string
     */
    var $limit;
    /**
     *
     * @var string
     */
    var $schema;

    /**
     *
     * @param string $url
     * @param string $dsa
     * @param string $schema
     * @param string $fromdate
     * @param string $offset
     * @param string $limit
     */
    public function __construct($url, $dsa, $schema=FALSE, $fromdate=FALSE, $offset=FALSE, $limit=FALSE) {
        $this->url = $url;
        $this->dsa = $dsa;
        $this->fromdate = ($fromdate) ? $fromdate : '0000-00-00';
        $this->offset = ($offset) ? $offset : 0;
        $this->limit = ($limit) ? $limit : 10;
        $this->schema = ($schema) ? $schema : 'http://www.tdwg.org/schemas/abcd/2.06';
    }

    /**
     *
     * @return string 
     */
    public function biocaseUnits() {
        $query =<<<QUERY
<?xml version="1.0" encoding="UTF-8"?>
<request xmlns="http://www.biocase.org/schemas/protocol/1.3">
  <header><type>search</type></header>
  <search>
    <requestFormat>$this->schema</requestFormat>
    <responseFormat start="$this->offset" limit="$this->limit">$this->schema</responseFormat>
      <filter>
        <greaterThan path="/DataSets/DataSet/Units/Unit/DateLastEdited">$this->fromdate</greaterThan>
      </filter>
      <count>false</count>
  </search>
</request>
QUERY;
        return $this->doCurl($query);
    }

    /**
     *
     * @return string 
     */
    public function bulkHarvest() {
        $query =<<<QUERY
<?xml version="1.0" encoding="UTF-8"?>
<request xmlns="http://www.biocase.org/schemas/protocol/1.3">
  <header><type>search</type></header>
  <search>
    <requestFormat>$this->schema</requestFormat>
    <responseFormat start="$this->offset" limit="$this->limit">$this->schema</responseFormat>
      <count>false</count>
  </search>
</request>
QUERY;
        return $this->doCurl($query);
    }

    /**
     *
     * 
     * biocaseCount Function
     * Counts the total number of records for the resource 
     * identified by URL and DSA
     * @return string 
     */
    public function biocaseCount() {
        $query =<<<QUERY
<?xml version="1.0" encoding="UTF-8"?>
<request xmlns="http://www.biocase.org/schemas/protocol/1.3">
  <header><type>search</type></header>
  <search>
    <requestFormat>$this->schema</requestFormat>
    <responseFormat start="$this->offset" limit="$this->limit">$this->schema</responseFormat>
      <count>true</count>
  </search>
</request>
QUERY;
        return $this->doCurl($query);
    }

    /**
     *
     * 
     * biocaseCount Function
     * Counts the total number of records for the resource 
     * identified by URL and DSA
     * @return string 
     */
    public function biocaseCountFilter($filter) {
        $query =<<<QUERY
<?xml version="1.0" encoding="UTF-8"?>
<request xmlns="http://www.biocase.org/schemas/protocol/1.3">
  <header><type>search</type></header>
  <search>
    <requestFormat>$this->schema</requestFormat>
    <responseFormat start="$this->offset" limit="$this->limit">$this->schema</responseFormat>
    <filter>
      $filter
    </filter>
    <count>true</count>
  </search>
</request>
QUERY;
        return $this->doCurl($query);
    }

    /**
     *
     * @return string 
     */
    public function biocaseCapabilities() {
        $command = "curl $this->url?dsa=$this->dsa";
        $result = `$command`;
        return $result;
    }
    
    public function biocaseGeneralFilter($filter) {
        $query =<<<QUERY
<?xml version="1.0" encoding="UTF-8"?>
<request xmlns="http://www.biocase.org/schemas/protocol/1.3">
  <header><type>search</type></header>
  <search>
    <requestFormat>$this->schema</requestFormat>
    <responseFormat start="$this->offset" limit="$this->limit">$this->schema</responseFormat>
    <filter>
      $filter
    </filter>
    <count>false</count>
  </search>
</request>
QUERY;
        return $this->doCurl($query);
    }
    
    private function doCurl($query) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url . '?dsa='. $this->dsa . '&query=' . urlencode($query));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($ch, CURLOPT_PROXY, "http://10.15.14.4:8080"); 
        curl_setopt($ch, CURLOPT_PROXYPORT, 8080); 
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, "nklazenga:48Dicranol!");
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    

}

?>
