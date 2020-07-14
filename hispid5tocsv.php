<?php

/**
 * Hispid5ToCsv class
 * 
 * Converts HISPID5 and ABCD2.06 to CSV.
 * 
 * @package Dehispidator
 * @author Niels Klazenga
 * @copyright Copyright (c) 2012, Council of Heads of Australian Herbaria (CHAH)
 * @license http://creativecommons.org/licenses/by/3.0/au/ CC BY 3.0 
 */

require_once('Encoding.php');

class Hispid5ToCsv {
    
    private $establishmentMeansMapping;
    
    public function __construct() {
        $this->establishmentMeansMapping = json_decode(file_get_contents('config/mapping_establishment_means.json'));
    }

    /**
     * removeWrapper function
     * 
     * Removes the BioCASe wrapper. The function was intended to also remove the 
     * namespace aliases, but that proved to be done more easily in the XPATHs than 
     * in the document. As the wrapper is not in the way, the function is not used 
     * anymore.
     * 
     * @param DOMDocument $doc
     * @return DOMDocument 
     */
    public function removeWrapper($doc) {
        $node = $doc->getElementsByTagName('DataSets')->item(0);
        $newdoc = new DOMDocument('1.0', 'UTF-8');
        $node = $newdoc->importNode($node, TRUE);
        $newdoc->appendChild($node);
        return $newdoc;
    }

    /**
     * parseHISPID5 function
     * 
     * Parses the XML and turns it into a two-dimensional array.
     * 
     * The function first creates a node list of all Units. For each Unit a node list
     * of all descendant elements is created. Each node is then checked for attributes and
     * whether they have a single child node (which means that is the text node). For 
     * elements with attributes the attribute nodes are retrieved and then for both 
     * elements and attributes the node path and node values are stored in a column-value 
     * array.
     * 
     * Special functions are called in each row to parse higher taxon names, named areas,
     * measurements or facts and collectors, which need a different structure in the output 
     * than they are stored in in the XML.
     * 
     * @param DOMDocument $doc
     * @return array 
     */
    public function parseHISPID5($doc) {
        $data = array();
        /*
         * Get the Units (individual records).
         */
        foreach ($doc->getElementsByTagName('Unit') as $key=>$unit){
            $row = array();
            
            $row[] = array(
                'column' => 'ID',
                'value' => $key+1
            );

            /*
             * Get all the decendant elements of the Unit.
             */
            foreach ($unit->getElementsByTagName('*') as $node) {
                /*
                 * The node path for the first in a set of repeatable elements is 
                 * different when there is only one element than when there are more
                 * than one. The search and replace arrays and the string replace
                 * functions later on take care of that.
                 */
                $search = array(
                    'GatheringAgent/',
                    'Identification/',
                    'HigherTaxon/',
                    'MeasurementOrFact/'
                );
                $replace = array(
                    'GatheringAgent[1]/',
                    'Identification[1]/',
                    'HigherTaxon[1]/',
                    'MeasurementOrFact[1]/'
                );

                /*
                 * Check whether the element node has attributes and, if so, retrieve
                 * them and store the node path and value. Some regular expresssions
                 * are used to get the right XPATH for the node path.
                 */
                if ($node->hasAttributes()) {
                    foreach ($node->attributes as $attribute){
                        $nodepath = $attribute->getNodePath();
                        preg_match('/Unit(\[[\d]+\])?\//', $nodepath, $matches);
                        $nodepath = 'Unit' . substr($nodepath, strpos($nodepath, $matches[0])+strlen($matches[0])-1);
                        $nodepath = preg_replace(array('/\/[\w]+:/', '/@[\w]+:/'), array('/', '@'), $nodepath);
                        $nodepath = str_replace($search, $replace, $nodepath);
                            $row[] = array(
                                'column' => $nodepath,
                                'value' => $attribute->nodeValue
                            );
                            
                    }
                }
                
                /*
                 * For nodes that have only a single child node, store the node path
                 * and value. Some regular expresssions are used to get the right 
                 * XPATH for the node path.
                 */
                if ($node->childNodes->length == 1) {
                    $nodepath = $node->getNodePath();
                    preg_match('/Unit(\[[\d]+\])?\//', $nodepath, $matches);
                    $nodepath = 'Unit' . substr($nodepath, strpos($nodepath, $matches[0])+strlen($matches[0])-1);
                    $nodepath = preg_replace(array('/\/[\w]+:/', '/@[\w]+:/'), array('/', '@'), $nodepath);
                    $nodepath = str_replace($search, $replace, $nodepath);
                    $row[] = array(
                        'column' => $nodepath,
                        'value' => $node->nodeValue
                    );
                }
            }
            
            /*
             * Functions for elements that need to be transposed, the results of
             * which are merged onto the row array.
             */
            
            // higher taxa
            $row = array_merge($row, $this->HigherTaxa($unit));

            // named areas
            $row = array_merge($row, $this->NamedAreas($unit));

            // measurement or facts
            $row = array_merge($row, $this->MeasurementsOrFacts($unit));
            $row = array_merge($row, $this->dwcGeoreference($unit));
            
            // collectors
            $row = array_merge($row, $this->GatheringAgents($unit));
            
            /*
             * Parse collectors strings for data sets without individual collectors 
             * and merge results onto the row array.
             */
            $row = array_merge($row, $this->parseGatheringAgentsText($unit));
            
            $row = array_merge($row, $this->Collectors($unit));
            
            $row = array_merge($row, $this->CurrentDetermination($unit));
            
            $determinationHistory = $this->previousIdentifications($unit);
            if ($determinationHistory)
                $row[] = $determinationHistory;
            
            $row = array_merge($row, $this->parseCoordinateMethod($unit));
            
            if ($collectingdate = $this->CollectingDate($unit))
                $row[] = $collectingdate;
            
            $row = array_merge($row, $this->Altitude($unit));
            $row = array_merge($row, $this->Depth($unit));
            
            if ($exherb = $this->ExHerbCatalogueNumber($unit))
                $row = array_merge($row, $exherb);
            
            if ($typestatus = $this->TypeStatus($unit))
                $row[] = $typestatus;
            
            $row = array_merge($row, $this->InstitutionCode($unit));
            
            $row = array_merge($row, $this->CollectionCode($unit));
            
            $row[] = $this->locality($unit);
            
            $row = array_merge($row, $this->habitat($unit));
            
            $row = array_merge($row, $this->dnaSequences($unit));
            
            $row = array_merge($row, $this->bushBlitz($unit));
            
            if ($est = $this->establishmentMeans($unit)) {
                $row[] = $est;
            }
        
            $data[] = $row;
        }
        return $data;
    }
    
    private function dnaSequences($unit) {
        $row = array();
        $sequences = array();
        $list = $unit->getElementsByTagName('Sequence');
        if ($list->length) {
            foreach ($list as $seq) {
                $uris = $seq->getElementsByTagName('URI');
                if ($uris->length) {
                    $sequences[] = $uris->item(0)->nodeValue;
                }
                else {
                    $gbaccnos = $seq->getElementsByTagName('ID-in-Database');
                    $sequences[] = 'http://www.ncbi.nlm.nih.gov/nuccore/' . $gbaccnos->item(0)->nodeValue;
                }
            }
        }
        if ($sequences) {
            $row[] = array(
                'column' => 'dwc:associatedSequences',
                'value' => implode(' | ', $sequences)
            );
        }
        return $row;
    }
    
    private function InstitutionCode($unit) {
        $row = array();
        $list = $unit->getElementsByTagName('SourceInstitutionID');
        if ($list->length) {
            $sourceinstid = $list->item(0)->nodeValue;
            switch ($sourceinstid) {
                case 'AD-A':
                case 'AD-C':
                    $sourceinstid = 'AD';
                    break;
                case 'CBG':
                    $sourceinstid = 'CANB';
                    break;
                default:
                    break;
            }
            $row[] = array(
                'column' => 'dwc:institutionCode',
                'value' => $sourceinstid
            );
        }
        return $row;
    }
    
    private function CollectionCode($unit) {
        $row = array();
        $list = $unit->getElementsByTagName('SourceInstitutionID');
        if ($list->length) {
            $sourceinstitutionid = $list->item(0)->nodeValue;
            if ($sourceinstitutionid == 'CANB' || $sourceinstitutionid == 'CNS') {
                $list = $unit->getElementsByTagName('UnitID');
                $unitid = $list->item(0)->nodeValue;
                $row[] = array(
                    'column' => 'dwc:collectionCode',
                    'value' => substr($unitid, 0, strpos($unitid, ':'))
                );
                $row[] = array(
                    'column' => 'dwc:catalogNumber',
                    'value' => str_replace(':', ' ', $unitid)
                );
            }
            elseif ($sourceinstitutionid == 'JCT') {
                $list = $unit->getElementsByTagName('UnitID');
                $unitid = $list->item(0)->nodeValue;
                $row[] = array(
                    'column' => 'dwc:collectionCode',
                    'value' => substr($unitid, 0, strpos($unitid, '-'))
                );
                $row[] = array(
                    'column' => 'dwc:catalogNumber',
                    'value' => $unitid
                );
            }
            else {
                if ($sourceinstitutionid == 'DNA') {
                    $collectionCode = $unit->getElementsByTagName('SourceID')->item(0)->nodeValue;
                }
                else {
                    $collectionCode = $unit->getElementsByTagName('SourceInstitutionID')->item(0)->nodeValue;
                }
                $row[] = array(
                    'column' => 'dwc:collectionCode',
                    'value' => $collectionCode
                );
                $row[] = array(
                    'column' => 'dwc:catalogNumber',
                    'value' => $collectionCode . ' ' . $unit->getElementsByTagName('UnitID')->item(0)->nodeValue
                );
            }
        }
        return $row;
    }

    /**
     * HigherTaxa function
     * 
     * Creates cell arrays with the Higher Taxon Rank (or the XPATh expression with
     * the Higher Taxon Rank in the filter) as the column name and Higher Taxon Name
     * as the value. 
     * 
     * @param DOMElement $unit
     * @return array 
     */
    private function HigherTaxa($unit) {
        $row = array();
        /*
         * Pretty straightforward DOM traversal
         */
        $identifications = $unit->getElementsByTagName('Identification');
        if ($identifications->length > 0) {
            foreach ($identifications as $i => $identification) {
                $highertaxa = $identification->getElementsByTagName('HigherTaxon');
                if ($highertaxa->length) {
                    foreach ($highertaxa as $highertaxon) {
                        $highertaxonrank = $highertaxon->getElementsByTagName('HigherTaxonRank');
                        if ($highertaxonrank->length) {
                            $rank = $highertaxonrank->item(0)->nodeValue;
                            $k = $i + 1;
                            $row[] = array(
                                'column' => "Unit/Identifications/Identification[$k]/Result/TaxonIdentified/HigherTaxa/HigherTaxon[HigherTaxonRank=\"$rank\"]/HigherTaxonName",
                                'value' => $highertaxon->getElementsByTagName('HigherTaxonName')->item(0)->nodeValue
                            );
                        }
                    }
                }
            }
        }
        return $row;
    }
    
    /**
     * HigherTaxa function
     * 
     * @param DOMElement $identification
     * @param boolean $current
     * @return array 
     */
    private function HigherTaxa2($identification, $current=TRUE) {
        $row = array();
        if ($current) $condition = 'PreferredFlag="true"';
        else $condition = 'PreferredFlag="false"';
        $highertaxa = $identification->getElementsByTagName('HigherTaxon');
        if ($highertaxa->length) {
            foreach ($highertaxa as $highertaxon) {
                $highertaxonrank = $highertaxon->getElementsByTagName('HigherTaxonRank');
                if ($highertaxonrank->length) {
                    $rank = $highertaxonrank->item(0)->nodeValue;
                    $row[] = array(
                        'column' => "Unit/Identifications/Identification[$condition]/Result/TaxonIdentified/HigherTaxa/HigherTaxon[HigherTaxonRank=\"$rank\"]/HigherTaxonName",
                        'value' => $highertaxon->getElementsByTagName('HigherTaxonName')->item(0)->nodeValue
                    );
                }
            }
        }
        return $row;
    }
    
    /**
     * NamedAreas function
     * 
     * Creates cell arrays with the Area Class (or the XPATH with the Area Class in
     * the filter) as the column name and the Area Name as the value.
     * 
     * @param DOMElement $unit
     * @return array 
     */
    private function NamedAreas($unit) {
        $row = array();
        $namedareas = $unit->getElementsByTagName('NamedArea');
        if ($namedareas->length) {
            foreach ($namedareas as $namedarea) {
                $class = $namedarea->getElementsByTagName('AreaClass');
                $class = ($class->length) ? ucfirst($class->item(0)->nodeValue) : FALSE;
                $name = $namedarea->getElementsByTagName('AreaName');
                $name = ($name->length) ? $name->item(0)->nodeValue : FALSE;
                if ($class && $name) {
                    if (in_array(strtolower($class), array('state', 'territory', 'province', 'district', 'state or province')))
                        $class = 'stateProvince';
                    
                    if (in_array(strtolower($class), array('special geographic unit', 'sgu')))
                        $class = 'county';
                    
                    if ($class == 'Continent') $class = 'continent';
                    
                    $na = array(
                        'column' => "Unit/Gathering/NamedAreas/NamedArea[AreaClass=\"$class\"]/AreaName",
                        'value' => $name
                    );
                    $row[] = $na;
                }
            }
        }
        
        
        return $row;
    }

    /**
     * MeasurementsOrFacts function
     * 
     * Creates cell areas with the Parameter added as a filter in the XPATHs that
     * make the column names, hence creating different names for each Measurement
     * or Fact.
     * 
     * Also creates valid ABCD XPATHs for Cultivated Occurrence and Natural Occurrence,
     * which are incorrect in the HISPID5 schema. These will be treated as Measurement 
     * or Facts with parameters CultivatedOccurrence and NaturalOccurrence respectively.
     * [2013-07-04:] Actually, CultivatedOccurrence and NaturalOccurrence are in ABCD2.06b. 
     * We still change the path to the ABCD Measurment or Fact paths for consistency.
     * [:2013-07-04]
     * 
     * Node values of HISPID Unit Phenology elements are concatenated and given an
     * ABCD2.06 Measurement or Fact XPATH with parameter Phenology.
     * 
     * [2013-07-04:] Added 'coordinatePrecision', 'verbatimCoordinateSystem' and 
     * 'verbatimSRS'. They will get a Site Measurement or Fact XPATH, no matter whether
     * they have been delivered as Site or Unit Measurement or Fact.[:2013-07-04]
     * 
     * @param DOMElement $unit
     * @return array 
     */
    private function MeasurementsOrFacts($unit) {
        $row = array();
        //$establishmentmeans = array();
        $measurementsorfacts = $unit->getElementsByTagName('MeasurementOrFactAtomised');
        if ($measurementsorfacts->length) {
            foreach ($measurementsorfacts as $measurementorfact) {
                $parameter = $measurementorfact->getElementsByTagName('Parameter');
                $parameter = ($parameter->length) ? $parameter->item(0)->nodeValue : FALSE;
                $lowervalue = $measurementorfact->getElementsByTagName('LowerValue');
                $lowervalue = ($lowervalue->length) ? $lowervalue->item(0)->nodeValue : FALSE;
                if ($parameter && $lowervalue) {
                    switch ($parameter) {
                        case 'cultivated occurrence':
                            $parameter = 'CultivatedOccurrence';
                        case 'natural occurrence':
                            $parameter = 'NaturalOccurrence';
                            break;

                        case 'phenology':
                        case 'voucher':
                        case 'frequency':
                        case 'soil':
                        case 'substrate':
                        case 'vegetation':
                        case 'habit':
                            $parameter = ucfirst($parameter);
                            break;

                        default:
                            break;
                    }
                    
                    /*if (in_array($parameter, array('CultivatedOccurrence', 'NaturalOccurrence')))
                        $establishmentmeans[] = $lowervalue;*/
                    
                    if (in_array($parameter, array('CultivatedOccurrence', 'NaturalOccurrence', 'Phenology', 'Voucher', 'Habit')))
                    $row[] = array(
                        'column' => "Unit/MeasurementsOrFacts/MeasurementOrFact/MeasurementOrFactAtomised[Parameter=\"$parameter\"]/LowerValue",
                        'value' => $lowervalue
                    );
                    
                    if (in_array($parameter, array('Frequency')))
                        $row[] = array(
                            'column' => "Unit/Gathering/SiteMeasurementsOrFacts/SiteMeasurementOrFact/MeasurementOrFactAtomised[Parameter=\"$parameter\"]/LowerValue",
                            'value' => $lowervalue
                    );
                    
                    if (in_array($parameter, array('Soil', 'Substrate', 'Vegetation')))
                        $row[] = array(
                            'column' => "Unit/Gathering/Biotope/MeasurementsOrFacts/MeasurementOrFactAtomised[Parameter=\"$parameter\"]/LowerValue",
                            'value' => $lowervalue
                    );
                        
                }
            }
        }
        
        /*
         * Get HISPID Cultivated Occurrence and Natural Occurrence.
         */
        $cultivated = $unit->getElementsByTagName('CultivatedOccurrence');
        if ($cultivated->length) {
            $row[] = array(
                'column' => 'Unit/MeasurementsOrFacts/MeasurementOrFact/MeasurementOrFactAtomised[Parameter="CultivatedOccurrence"]/LowerValue',
                'value' => $cultivated->item(0)->nodeValue
            );
            //$establishmentmeans[] = $cultivated->item(0)->nodeValue;
        }
        
        $natural = $unit->getElementsByTagName('NaturalOccurrence');
        if ($natural->length) {
            $row[] = array(
                'column' => 'Unit/MeasurementsOrFacts/MeasurementOrFact/MeasurementOrFactAtomised[Parameter="NaturalOccurrence"]/LowerValue',
                'value' => $natural->item(0)->nodeValue
            );
            //$establishmentmeans[] = $natural->item(0)->nodeValue;
        }
        
        /*
         * Concatenates Cultivated Occurrence and Natural Occurrence into establishmentMeans
         */
        
        
        /*if ($establishmentmeans){
            $establishmentmeans = implode('; ', $establishmentmeans);
            $row[] = array(
                'column' => 'dwc:establishmentMeans',
                'value' => $establishmentmeans
            );
        }*/
        
        /*
         * Frequency in HISPID element //HispidUnit/Frequency
         */
        $frequency = $unit->getElementsByTagName('Frequency');
        if ($frequency->length) {
            $row[] = array(
                'column' => 'Unit/Gathering/SiteMeasurementsOrFacts/MeasurementOrFact/MeasurementOrFactAtomised[Parameter="Frequency"]/LowerValue',
                'value' => $frequency->item(0)->nodeValue
            );
        }
        
        /*
         * Voucher in HISPID element //HispidUnit/Voucher
         */
        $voucher = $unit->getElementsByTagName('Voucher');
        if ($voucher->length) {
            $row[] = array(
                'column' => 'Unit/MeasurementsOrFacts/MeasurementOrFact/MeasurementOrFactAtomised[Parameter="Voucher"]/LowerValue',
                'value' => $voucher->item(0)->nodeValue
            );
        }
        
        
        /*
         * Concatenate values of HISPID Unit Phenology elements and stores the resulting
         * value with an ABCD Measurement or Fact XPATH. That is, provided that they are
         * mapped to the right concept, which is
         * not always the case.
         */
        $list = $unit->getElementsByTagName('Phenology');
        if ($list->length) {
            $phenology = array();
            foreach ($list as $phen) {
                $phenology[] = $phen->nodeValue;
            }
            $phenology = implode('; ', $phenology);
            $row[] = array(
                'column' => 'Unit/MeasurementsOrFacts/MeasurementOrFact/MeasurementOrFactAtomised[Parameter="Phenology"]/LowerValue',
                'value' => $phenology
            );
            
        }
        
        return $row;
    }
    
    private function establishmentMeans($unit) {
        $row = [];
        $establishmentMeans = FALSE;
        $list = $unit->getElementsByTagName('EstablishmentMeans');
        if ($list->length) {
            $establishmentMeans = $list->item(0)->nodeValue;
        }
        else {
            $nat = FALSE;
            $cult = FALSE;
            
            $mofs = $unit->getElementsByTagName('MeasurementOrFactAtomised');
            if ($mofs->length) {
                foreach ($mofs as $mof) {
                    $param = @$mof->getElementsByTagName('Parameter')->item(0)->nodeValue;
                    $val = @$mof->getElementsByTagName('LowerValue')->item(0)->nodeValue;
                    if ($param && $val) {
                        switch ($param) {
                            case 'CultivatedOccurrence':
                            case 'cultivated occurrence':
                                $cult = strtolower($val);
                                break;
                            case 'NaturalOccurrence':
                            case 'natural occurrence':
                                $nat = 'NaturalOccurrence';
                                break;
                            default:
                                break;
                        }
                    }
                }
            }
            else {
                $nat = @strtolower($unit->getElementsByTagName('NaturalOccurrence')->item(0)->nodeValue);
                $cult = @strtolower($unit->getElementsByTagName('CultivatedOccurrence')->item(0)->nodeValue);
            }
            if ($nat || $cult) {
                $map = $this->findMappingItem($nat, $cult);
                $filtered = array_filter($this->establishmentMeansMapping, $map);
                if (count($filtered) > 0) { // mapping item found
                    $mappingItem = array_values($filtered)[0];
                    if ($mappingItem->{'dwc:establishmentMeans'}) { // establihment means for this combination of NaturalOccurrence and CultivatedOccurrence exists
                        $row = array(
                            'column' => 'dwc:establishmentMeans',
                            'value' => $mappingItem->{'dwc:establishmentMeans'}
                        );
                    }
                }
            }
        }
        if ($establishmentMeans){
            $row = array(
                'column' => 'dwc:establishmentMeans',
                'value' => $establishmentMeans
            );
        }
        return $row;
    }
    
    protected function findMappingItem($nat, $cult) {
        return function($item) use ($nat, $cult) {
            return $item->{"abcd:NaturalOccurrence"} == $nat &&
                    $item->{'abcd:CultivatedOccurrence'} == $cult; 
        };
    }
    
    private function SiteMeasurementsOrFacts($unit) {
        $row = array();
        $establishmentmeans = array();
        $measurementsorfacts = $unit->getElementsByTagName('SiteMeasurementOrFact');
        if ($measurementsorfacts->length) {
            foreach ($measurementsorfacts as $measurementorfact) {
                $parameter = $measurementorfact->getElementsByTagName('Parameter');
                $parameter = ($parameter->length) ? $parameter->item(0)->nodeValue : FALSE;
                $lowervalue = $measurementorfact->getElementsByTagName('LowerValue');
                $lowervalue = ($lowervalue->length) ? $lowervalue->item(0)->nodeValue : FALSE;
                if ($parameter && $lowervalue)
                    if (in_array($parameter, array('coordinatePrecision', 'verbatimCoordinateSystem', 'verbatimSRS')))
                        $row[] = array(
                            'column' => "Unit/Gathering/SiteMeasurementsOrFacts/SiteMeasurementOrFact/MeasurementOrFactAtomised[Parameter=\"$parameter\"]/LowerValue",
                            'value' => $lowervalue
                    );
            }
        }
        return $row;
    }
    
    private function dwcGeoreference($unit) {
        $row = array();
        $elements = array(
            'georeferencedDate',
            'coordinatePrecision',
            'verbatimCoordinateSystem',
            'verbatimSRS'
        );
        foreach ($elements as $element) {
            $list = $unit->getElementsByTagName($element);
            if ($list->length) {
                $row[] = array(
                    'column' => "dwc:$element",
                    'value' => $list->item(0)->nodeValue
                );
            }
        }
        return $row;
    }
    
    /**
     * GatheringAgents function
     * 
     * Creates cell arrays with the Agent Text sequence attribute in the XPATH, in order
     * for the collector columns to be correctly ordered in the CSV. If no sequence attribute
     * is given the order in which the Agent Text elements come out of the XML, which is not
     * necessarily the same as the order they go in, will be used.
     * 
     * @param DOMElement $unit 
     * @return array
     */
    private function GatheringAgents($unit) {
        $row = array();
        $list = $unit->getElementsByTagName('GatheringAgent');
        if ($list->length) {
            foreach ($list as $i => $gatheringagent) {
                $primary = ($gatheringagent->hasAttribute('primarycollector')) ?
                    $gatheringagent->getAttribute('primarycollector') : 1;
                $agenttext = $gatheringagent->getElementsByTagName('AgentText');
                if ($agenttext->length)
                    $agenttext = $agenttext->item(0)->nodeValue;

                if ($gatheringagent->hasAttribute('sequence')) {
                    $sequence = $gatheringagent->getAttribute('sequence');
                    $row[] = array(
                        'column' => "Unit/Gathering/Agents/GatheringAgent[@sequence=\"$sequence\"]/@primarycollector",
                        'value' => $primary
                    );
                    $row[] = array(
                        'column' => "Unit/Gathering/Agents/GatheringAgent[@sequence=\"$sequence\"]/AgentText",
                        'value' => $agenttext
                    );
                }
                else {
                    $k = $i + 1;
                    $row[] = array(
                        'column' => "Unit/Gathering/Agents/GatheringAgent[@sequence=\"$k\"]/@primarycollector",
                        'value' => "1"
                    );
                    $row[] = array(
                        'column' => "Unit/Gathering/Agents/GatheringAgent[@sequence=\"$k\"]/AgentText",
                        'value' => $agenttext
                    );
                }
            }
        }
        return $row;
    }
    
    /**
     * parseGateringAgentsText function
     * 
     * For data sets that do not provide individual collectors, but provide a concatenated 
     * string of collectors, this function parses the string and creates cell arrays
     * with individual collectors and the appropriate XPATHs. 
     * 
     * @param DOMElement $unit 
     * @return array
     */
    private function parseGatheringAgentsText($unit) {
        $row = array();
        /*
         * First check if parsed collectors are not already in the XML.
         */
        $list = $unit->getElementsByTagName('GatheringAgent');
        if (!$list->length) {
            /*
             * Get the collectors string, parse it and create individual collector
             * arrays.
             */
            $gatheringagents = $unit->getElementsByTagName('GatheringAgentsText');
            if ($gatheringagents->length) {
                $text = $gatheringagents->item(0)->nodeValue;
                $gatheringagents = explode(';', $text);
                foreach ($gatheringagents as $i => $agent) {
                    $k = $i + 1;
                    $row[] = array(
                        'column' => "Unit/Gathering/Agents/GatheringAgent[@sequence=\"$k\"]/@primarycollector",
                        'value' => "1"
                    );
                    $row[] = array(
                        'column' => "Unit/Gathering/Agents/GatheringAgent[@sequence=\"$k\"]/AgentText",
                        'value' => trim($agent)
                    );
                }
            }
        }
        return $row;
    }
    
    
    
    /**
     * outputToCsv function
     * 
     * Outputs parsed data to CSV. It does so by first creating an array of column
     * names from the parsed data, which forms the header row and then for each 
     * input find each column and store the value in the output row array. The output
     * row array is imploded and added to the CSV array, which in the end is imploded
     * and returned as a string.
     * 
     * Optionally a CSV file ('config.csv') with XPATHs in the first column 
     * and friendly column names in the second can be used to create a header row
     * with friendly column names. This file is also used to set the columns that
     * will be in the output and the order of the columns.
     * 
     * Currently only columns for which there is data will be in the output, but it
     * will be quite easy to add an option to output all columns in the configuration
     * file. This will be handy if we want to store data from multiple XML files in
     * the same CSV file. In this case the header row should be made optional as well.
     * 
     * @param array $data
     * @param array $config
     * @return string 
     */
    public function outputToCsv($data, $config=FALSE, $headerrow=TRUE, $allcols=FALSE) {
        $csv = array();
        
        /*
         * Get the column names from the data.
         */
        $xpathcols = array();
        foreach ($data as $unit) {
            foreach ($unit as $item)
                $xpathcols[] = $item['column'];
        }
        $xpathcols = array_unique($xpathcols);
        sort($xpathcols);
        
        if ($config) {
            /*
             * Get the XPATHs and the friendly column names from the configuration.
             */
            $xp_cols = array();
            $friendlycolnames = array();
            foreach ($config as $name) {
                /*
                 * Check whether the columns from the configuration file are present.
                 * in the data
                 */
                if ($allcols || in_array($name[0], $xpathcols)){
                    $friendlycolnames[] = $name[1];
                    $xp_cols[] = $name[0];
                }
            }
            
            
            /*
             * This fixes the Canberra Source Institution and Unit IDs. Shifting the
             * first two items of both the XPATH column name and friendly column name
             * arrays, Accession Catalogue and Accession Number will be used instead
             * of Source Institution ID and Unit ID.
             */
            if (in_array('Unit/SpecimenUnit/Accessions/AccessionNumber', $xpathcols)) {
                $key = array_search('UnitID', $friendlycolnames);
                if ($key !== FALSE)
                    $xp_cols[$key] = 'Unit/SpecimenUnit/Accessions/AccessionNumber';
                else {
                    $key = array_search('catalogNumber', $friendlycolnames);
                    if ($key !== FALSE)
                        $xp_cols[$key] = 'Unit/SpecimenUnit/Accessions/AccessionNumber';
                }
                
                $key = array_search('SourceInstitutionID', $friendlycolnames);
                if ($key !== FALSE)
                    $xp_cols[$key] = 'Unit/SpecimenUnit/Accessions/AccessionCatalogue';
                else {
                    $key = array_search('institutionCode', $friendlycolnames);
                    if ($key !== FALSE)
                        $xp_cols[$key] = 'Unit/SpecimenUnit/Accessions/AccessionNumber';
                }
            }
            
            
            /*
             * Create the columns array and add the header row to the CSV array.
             */
            $cols = $xp_cols;
            if ($headerrow)
                $csv[] = implode(',', $friendlycolnames);
        }
        else {
            /*
             * Create the columns array and add the header row to the CSV array.
             */
            $cols = $xpathcols;
            if ($headerrow)
                $csv[] = implode(',', array_values($cols));
        }
        
        foreach ($data as $unit) {
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
                    $row[] = (is_numeric($value)) ? $value : '"' . $this->escapeQuotes($value) . '"';
                }
                else
                    $row[] = '""';
            }
            
            /*
             * Implode the row array and add to the CSV array.
             */
            $csv[] = implode(',', $row);
        }
        
        /*
         * Implode the CSV array and return as a string.
         */
        $csv = implode("\n", $csv);
        return $csv;
    }
    
    /**
     * getNamedAreaClasses function
     * 
     * Gets the Named Area Classes from the data array
     * 
     * @param array $data
     * @return array 
     */
    public function getNamedAreaClasses($data) {
        $classes = array();
        foreach ($data as $record) {
            foreach ($record as $item) {
                if (strpos($item['column'], 'AreaClass='))
                    $classes[] = $item['column'];
            }
        }
        $classes = array_unique($classes);
        return $classes;
    }

    /**
    * escapeQuotes function
    * 
    * In CSV double quotes must be escaped by replacing a single double quote with two doube quotes.
    * 
    * @param string $string
    * @return string 
    */
    private function escapeQuotes($string) {
        return str_replace('"', '""', $string);
    }

    /**
     * CurrentDetermination function
     * 
     * @param DOMElement $unit
     * @return array 
     */
    private function CurrentDetermination($unit) {
        $ret = array(); 
        $list = $unit->getElementsByTagName('Identification');
        if ($list->length > 0) {
            foreach ($list as $item) {
                $preferredflag = $item->getElementsByTagName('PreferredFlag');
                if (($preferredflag->length > 0 && $preferredflag->item(0)->nodeValue) || $list->length == 1) {
                    $ret = $this->parseIdentification($item, TRUE);
                }
            }
        }
        return $ret;
    }
    
    private function previousIdentifications($unit) {
        $dets = array();
        $date = array();
        $list = $unit->getElementsByTagName('Identification');
        if ($list->length > 1) { // This skips all Units that have a single Identification
            // (which is assumed to be the current identification).
            foreach ($list as $item) {
                $preferredflag = $item->getElementsByTagName('PreferredFlag');
                if ($preferredflag->length>0 && in_array($preferredflag->item(0)->nodeValue, array('0', 'FALSE', 'false'))) {
                    // There is a preferred flag and it resolves to FALSE.
                    $det = array();
                    $nlist = $item->getElementsByTagName('FullScientificNameString');
                    $det['FullScientificNameString'] = ($nlist->length > 0) ? $nlist->item(0)->nodeValue : FALSE;
                    
                    $nlist = $item->getElementsByTagName('IdentificationQualifier');
                    if ($nlist->length > 0) {
                        $det['IdentificationQualifier'] = $nlist->item(0)->nodeValue;
                        $det['IdentificationQualifierInsertionPoint'] = $nlist->item(0)->getAttribute('insertionpoint');
                    }
                    else {
                        $det['IdentificationQualifier'] = FALSE;
                        $det['IdentificationQualifierInsertionPoint'] = FALSE;
                    }
                    
                    $nlist = $item->getElementsByTagName('NameAddendum');
                    $det['NameAddendum'] = ($nlist->length > 0) ? $nlist->item(0)->nodeValue : FALSE;

                    $nlist = $item->getElementsByTagName('HybridFlag');
                    if ($nlist->length > 0) {
                        $det['HybridFlag'] = $nlist->item(0)->nodeValue;
                        $det['HybridFlagInsertionPoint'] = $nlist->item(0)->getAttribute('insertionpoint');
                    }
                    else {
                        $det['HybridFlag'] = FALSE;
                        $det['HybridFlagInsertionPoint'] = FALSE;
                    }
                        
                   $nlist = $item->getElementsByTagName('IdentifierRole');
                    $det['IdentifierRole'] = ($nlist->length > 0) ? $nlist->item(0)->nodeValue : FALSE;
                    
                    $nlist = $item->getElementsByTagName('IdentifiersText');
                    $det['IdentifiersText'] = ($nlist->length > 0) ? $nlist->item(0)->nodeValue : FALSE;
                    
                    $nlist = $item->getElementsByTagName('ISODateTimeBegin');
                    $det['IdentificationDate'] = ($nlist->length > 0) ? $nlist->item(0)->nodeValue : FALSE;
                    $date[] = ($nlist->length > 0) ? $nlist->item(0)->nodeValue : 'ZZZZ';
                    
                    $nlist = $item->getElementsByTagName('Notes');
                    $det['IdentificationNotes'] = ($nlist->length > 0) ? $nlist->item(0)->nodeValue : FALSE;
                    
                    $dets[] = $det;
                }
            }
            
            // previous identifications are sorted by identification date
            array_multisort($date, SORT_ASC, $dets); 
            
            $previousDets = array();
            foreach ($dets as $index => $det) {
                $prev = '';
                
                // Scientific name
                $sciname = $det['FullScientificNameString'];
                $scinameBits = explode(' ', $sciname);
                if ($det['HybridFlag'] && $det['HybridFlagInsertionPoint']) {
                    $scinameBits = explode(' ', $sciname);
                    $scinameBits[$det['HybridFlagInsertionPoint']-1] = Encoding::toUTF8('×') . $scinameBits[$det['HybridFlagInsertionPoint']-1];
                    $sciname = implode(' ', $scinameBits);
                }
                if ($det['IdentificationQualifier'] && $det['IdentificationQualifierInsertionPoint']) {
                    $scinameBits = explode(' ', $sciname);
                    if ($det['IdentificationQualifierInsertionPoint'] > count($scinameBits))
                        $det['IdentificationQualifierInsertionPoint'] = count($scinameBits);
                    $spacer = ($det['IdentificationQualifier'] == '?') ? '' : ' ';
                    $scinameBits[$det['IdentificationQualifierInsertionPoint']-1] = $det['IdentificationQualifier'] . $spacer . $scinameBits[$det['IdentificationQualifierInsertionPoint']-1];
                    $sciname = implode(' ', $scinameBits);
                }
                if ($det['NameAddendum']) $sciname .= ' ' . $det['NameAddendum'];
                $prev .= $sciname;
                
                // Determiner
                if ($det['IdentifiersText']) {
                    $prev .= ', ';
                    $prev .= ($det['IdentifierRole'] == 'conf.') ? 'conf. ' : 'det. ';
                    
                    $identifiers = explode(';', $det['IdentifiersText']);
                    
                    $identifier = explode(',', $identifiers[0]);
                    $prev .= (count($identifier) > 1) ? trim($identifier[1]) . ' ' . trim($identifier[0]) : trim($identifier[0]);
                    
                    if (count($identifiers) == 2) {
                        $identifier = explode(',', $identifiers[1]);
                        $prev .= ' & ';
                        $prev .= (count($identifier) > 1) ? trim($identifier[1]) . ' ' . trim($identifier[0]) : trim($identifier[0]);
                    }
                    elseif (count($identifiers) > 2)
                        $prev .= ' et al.';
                }
                
                // Determination date
                if ($det['IdentificationDate']) {
                    $dateBits = explode('-', $det['IdentificationDate']);
                    $date = '';
                    
                    $day = (isset($dateBits[2])) ? $dateBits[2] : FALSE;
                    
                    $month = FALSE;
                    if (isset($dateBits[1])) {
                        switch ($dateBits[1]) {
                            case '01':
                                $month = 'i';
                               break;

                            case '02':
                                $month = 'ii';
                               break;

                            case '03':
                                $month = 'iii';
                               break;

                            case '04':
                                $month = 'iv';
                               break;

                            case '05':
                                $month = 'v';
                               break;

                            case '06':
                                $month = 'vi';
                               break;

                            case '07':
                                $month = 'vii';
                               break;

                            case '08':
                                $month = 'viii';
                               break;

                            case '09':
                                $month = 'ix';
                               break;

                            case '10':
                                $month = 'x';
                               break;

                            case '11':
                                $month = 'xi';
                               break;

                            case '12':
                                $month = 'xii';
                               break;

                            default:
                                break;
                        }
                    }
                    
                    $year = $dateBits[0];
                    
                    if ($day) 
                        $date = "$day.$month.$year";
                    elseif ($month)
                        $date = "$month.$year";
                    else
                        $date = $year;
                    
                    $prev .= ', ' . $date;
                    
                }
                
                if ($det['IdentificationNotes']) {
                    $prev .= ' (' . $det['IdentificationNotes'] . ')';
                }
                
                $previousDets[] = $prev;
                
            }
            
            $previousDets = implode('; ', $previousDets);
            if (substr($previousDets, strlen($previousDets)-1, 1) != '.')
                $previousDets .= '.';
            $ret = array (
                'column' => 'previousIdentifications',
                'value' => $previousDets,
            );
            return $ret;
            
        }
        
    }
    
    
    /**
     * parseIdentification function
     * 
     * @param DOMElement $identification
     * @param boolean $current
     * @return array 
     */
    private function parseIdentification($identification, $current) {
        $row = array();
        if ($current) {
            $root = 'Unit/Identifications/Identification[PreferredFlag="true"]/';
            $meta = 'core';
        }
        else {
            $root = 'Unit/Identifications/Identification[PreferredFlag="false"]/';
            $meta = 'extension';
        }
        
        $identificationid = $identification->getElementsByTagName('identificationID');
        if ($identificationid->length) {
            $row[] = array(
                'column' => "dwc:identificationID[$meta]",
                'value' => $identificationid->item(0)->nodeValue
            );
        }
        
        $sciname = $identification->getElementsbyTagName('FullScientificNameString');
        if ($sciname->length) {
            $row[] = array(
                'column' => $root . 'Result/TaxonIdentified/ScientificName/FullScientificNameString',
                'value' => $sciname->item(0)->nodeValue
            );
        }
        $atomisedname = $identification->getElementsByTagName('Botanical');
        if ($atomisedname->length) {
            $parts = $atomisedname->item(0)->getElementsByTagName('*');
            foreach ($parts as $part) {
                $tagname = $part->tagName;
                if (strpos($tagname, ':')) $tagname = substr ($tagname, strpos($tagname, ':')+1);
                $row[] = array (
                    'column' => $root . 'Result/TaxonIdentified/ScientificName/NameAtomised/Botanical/' . $tagname,
                    'value' => $part->nodeValue
                );
            }
        }
        
        $taxonrank = $identification->getElementsByTagName('taxonRank');
        if ($taxonrank->length) {
            $row[] = array(
                'column' => "dwc:taxonRank[$meta]",
                'value' => $taxonrank->item(0)->nodeValue
            );
        }
        else {
            $taxonrank = $identification->getElementsByTagName('Rank');
            if ($taxonrank->length) {
                $row[] = array(
                    'column' => "dwc:taxonRank[$meta]",
                    'value' => $taxonrank->item(0)->nodeValue
                );
            }
        }

        $identificationqualifier = $this->IdentificationQualifier($identification);
        if ($identificationqualifier) {
            $row[] = array(
                'column' => "dwc:identificationQualifier[$meta]",
                'value' => $identificationqualifier[0]
            );
            $row[] = array(
                'column' => "abcd:IdentificationQualifier[$meta]",
                'value' => $identificationqualifier[1]
            );
            $row[] = array(
                'column' => "abcd:InsertionPoint[$meta]",
                'value' => $identificationqualifier[2]
            );
        }

        $nameaddendum = $identification->getElementsByTagName('NameAddendum');
        if ($nameaddendum->length) {
            $row[] = array(
                'column' => $root . 'Result/TaxonIdentified/ScientificName/NameAddendum',
                'value' => $nameaddendum->item(0)->nodeValue
            );
        }

        $identifier = $identification->getElementsByTagName('IdentifierRole');
        if ($identifier->length) {
            $row[] = array(
                'column' => $root . 'Identifiers/IdentifierRole',
                'value' => $identifier->item(0)->nodeValue
            );
        }

        $identifier = $identification->getElementsByTagName('IdentifiersText');
        if ($identifier->length) {
            $row[] = array(
                'column' => $root . 'Identifiers/IdentifiersText',
                'value' => $identifier->item(0)->nodeValue
            );
        }

        $identificationdate = $identification->getElementsByTagName('ISODateTimeBegin');
        if ($identificationdate->length) {
            $row[] = array(
                'column' => $root . 'Date/ISODateTimeBegin',
                'value' => $identificationdate->item(0)->nodeValue
            );
        }

        $identificationnotes = $identification->getElementsByTagName('Notes');
        if ($identificationnotes->length) {
            $row[] = array(
                'column' => $root . 'Notes',
                'value' => $identificationnotes->item(0)->nodeValue
            );
        }
        
        // Merge higher taxa onto the array
        $row = array_merge($row, $this->HigherTaxa2($identification, $current));
        
        // Push scientific name authorship onto array
        //$row = array_merge($row, $this->ScientificNameAuthorship($identification));
        if ($scientificnameauthorship = $this->ScientificNameAuthorship($identification))
            $row[] = array(
                'column' => "dwc:scientificNameAuthorship[$meta]",
                'value' => $scientificnameauthorship
            );
;
        
        return $row;
    }
    
    /**
     *
     * @param DOMDocument $doc
     * @return array
     */
    public function DeterminationHistory(DOMDocument $doc) {
        $ret = array();
        $units = $doc->getElementsByTagName('Unit');
        if ($units->length) {
            foreach ($units as $key=>$unit) {
                $identifications = $unit->getElementsByTagName('Identification');
                if ($identifications->length) {
                   foreach ($identifications as $identification) {
//                        $preferredflag = $identification->getElementsByTagName('PreferredFlag');
//                        if (!$preferredflag->length || !$preferredflag->item(0)->nodeValue) {
                            $row =array();
                            $row[] = array(
                                'column' => 'CoreID',
                                'value' => $key+1
                            );
                            
                            $accessionNumber = $unit->getElementsByTagName('AccessionNumber');
                            if ($accessionNumber->length > 0) {
                                $catalogNumber = $accessionNumber->item(0)->nodeValue;
                                $institutionCode = $unit->getElementsByTagName('AccessionCatalogue')->item(0)->nodeValue;
                            }
                            else {
                                $catalogNumber = $unit->getElementsByTagName('UnitID')->item(0)->nodeValue;
                                $institutionCode = $unit->getElementsByTagName('SourceInstitutionID')->item(0)->nodeValue;
                            }
                            
                            $row[] = array(
                                'column' => 'institutionCode',
                                'value' => $institutionCode
                            );
                            $row[] = array(
                                'column' => 'catalogNumber',
                                'value' => $catalogNumber
                            );

                            $ret[] = array_merge($row, $this->parseIdentification($identification, FALSE));
//                        }
                    }
                }
            }
        }
        return $ret;
    }

/*    private function CurrentDetermination($unit) {
        $row = array();
        $list = $unit->getElementsByTagName('Identification');
        if ($list->length > 0) {
            foreach ($list as $item) {
                $preferredflag = $item->getElementsByTagName('PreferredFlag');
                if (($preferredflag->length > 0 && $preferredflag->item(0)->nodeValue) || $list->length == 1) {
                    $sciname = $item->getElementsbyTagName('FullScientificNameString');
                    if ($sciname->length) {
                        $row[] = array(
                            'column' => 'Unit/Identifications/Identification[PreferredFlag="true"]/Result/TaxonIdentified/ScientificName/FullScientificNameString',
                            'value' => $sciname->item(0)->nodeValue
                        );
                    }
                    $atomisedname = $item->getElementsByTagName('Botanical');
                    if ($atomisedname->length) {
                        $parts = $atomisedname->item(0)->getElementsByTagName('*');
                        foreach ($parts as $part) {
                            $tagname = $part->tagName;
                            if (strpos($tagname, ':')) $tagname = substr ($tagname, strpos($tagname, ':')+1);
                            $row[] = array (
                                'column' => 'Unit/Identifications/Identification[PreferredFlag="true"]/Result/TaxonIdentified/ScientificName/NameAtomised/Botanical/' . $tagname,
                                'value' => $part->nodeValue
                            );
                        }
                    }
                    
                    list($identificationqualifier, $qualifier, $insertionpoint) = $this->IdentificationQualifier($identification);
                    if ($identificationqualifier) {
                        $row[] = array(
                            'column' => 'dwc:identificationQualifier',
                            'value' => $identificationqualifier
                        );
                        $row[] = array(
                            'column' => 'abcd:IdentificationQualifier',
                            'value' => $identificationqualifier
                        );
                        $row[] = array(
                            'column' => 'abcd:InsertionPoint',
                            'value' => $identificationqualifier
                        );
                    }
                    
                    $nameaddendum = $item->getElementsByTagName('NameAddendum');
                    if ($nameaddendum->length) {
                        $row[] = array(
                            'column' => 'Unit/Identifications/Identification[PreferredFlag="true"]/Result/TaxonIdentified/ScientificName/NameAddendum',
                            'value' => $nameaddendum->item(0)->nodeValue
                        );
                    }
                    
                    $identifier = $item->getElementsByTagName('IdentifiersText');
                    if ($identifier->length) {
                        $row[] = array(
                            'column' => 'Unit/Identifications/Identification[PreferredFlag="true"]/Result/TaxonIdentified/ScientificName/NameAddendum',
                            'value' => $identifier->item(0)->nodeValue
                        );
                    }
                    
                    $identificationdate = $item->getElementsByTagName('ISODateTimeBegin');
                    if ($identificationdate->length) {
                        $row[] = array(
                            'column' => 'Unit/Identifications/Identification[PreferredFlag="true"]/Date/ISODateTimeBegin',
                            'value' => $identificationdate->item(0)->nodeValue
                        );
                    }
                    
                    $identificationnotes = $item->getElementsByTagName('Notes');
                    if ($identificationnotes->length) {
                        $row[] = array(
                            'column' => 'Unit/Identifications/Identification[PreferredFlag="true"]/Notes',
                            'value' => $identificationnotes->item(0)->nodeValue
                        );
                    }
                }
            }
        }
        return $row;
    }
*/    
    /**
     *
     * @param DOMElement $identification 
     */
    function IdentificationQualifier($identification) {
        // Add parts of atomised name to array
        $atomisedname = array();
        $parts = array('GenusOrMonomial', 'FirstEpithet', 'Rank', 'InfraspecificEpithet');
        foreach($parts as $part) {
            $bit = $identification->getElementsByTagName($part);
            if ($bit->length)
                $atomisedname[$part] = $bit->item(0)->nodeValue;
            else
                $atomisedname[$part] = FALSE;
        }
        
        // explode scientific name into an array in case insertion point is not provided;
        // we'll be using it twice
        $scientificname = $identification->getElementsByTagName('FullScientificNameString');
        if ($scientificname->length) {
            $scientificname = $scientificname->item(0)->nodeValue;
            $bits = explode(' ', $scientificname);
        }
        else
            return FALSE;
        
        // Find position of qualifier (if any)
        $qualifier = FALSE;
        $insertionpoint = FALSE;
        $list = $identification->getElementsByTagName('IdentificationQualifier');
        if ($list->length) {
            $qualifier = $list->item(0)->nodeValue;
            $insertionpoint = $list->item(0)->getAttribute('insertionpoint');
            if(!$insertionpoint) {
                foreach ($bits as $key=>$bit) {
                    if (substr($bit, 0, strlen($qualifier)) == $qualifier)
                        $insertionpoint = $key+1;
                }
            }
        }
        else {
            // Qualifier is not provided separately, so we look if there is one in the 
            // scientific name string
            foreach ($bits as $key=>$bit) {
                if ($bit == 'cf.') {
                    $qualifier = 'cf.';
                    $insertionpoint = $key+1;
                }
                /*
                 * Due to problems associated with 'aff.' sometimes being used in 
                 * informal names, I decided not to look for 'aff.' in the scientific 
                 * name string if it is not clear whether its use is intended as a
                 * qualifier.
                 */
                /*elseif ($bit == 'aff.' && $bits[1] != 'sp.') {
                    $qualifier = 'aff.';
                    $insertionpoint = $key+1;
                }*/
                elseif (substr($bit, 0, 1) == '?') {
                    $qualifier = '?';
                    $insertionpoint = $key+1;
                }
            }
        }
        
        if ($qualifier) {
            $idqualifier = FALSE;
            if (!$insertionpoint) {
                if ($atomisedname['Rank'] && $atomisedname['InfraspecificEpithet']) $insertionpoint = 3;
                elseif ($atomisedname['FirstEpithet']) $insertionpoint = 2;
                elseif ($atomisedname['GenusOrMonomial']) $insertionpoint = 1;
                else return FALSE;
            }
            
            if ($insertionpoint == 1) {
                $idqualifier = $qualifier;
                $idqualifier .= ($qualifier != '?') ? ' ' : '';
                $idqualifier .= $atomisedname['GenusOrMonomial'];
                $idqualifier .= ($atomisedname['FirstEpithet']) ? ' ' . $atomisedname['FirstEpithet'] : '';
                $idqualifier .= ($atomisedname['Rank'] && ' ' . $atomisedname['InfraspecificEpithet']) 
                    ? $atomisedname['Rank'] . ' ' . $atomisedname['FirstEpithet'] : '';
            }
            if ($insertionpoint == 2) {
                //$idqualifier = $atomisedname['GenusOrMonomial'];
                //$idqualifier .= ' ' . $qualifier;
                $idqualifier = $qualifier;
                $idqualifier .= ($qualifier != '?') ? ' ' : '';
                $idqualifier .= ($atomisedname['FirstEpithet']) ? $atomisedname['FirstEpithet'] : '';
                $idqualifier .= ($atomisedname['Rank'] && $atomisedname['InfraspecificEpithet']) 
                    ? ' ' . $atomisedname['Rank'] . ' ' . $atomisedname['FirstEpithet'] : '';
            }
            if ($insertionpoint == 3) {
                //$idqualifier = $atomisedname['GenusOrMonomial'];
                //$idqualifier .= ($atomisedname['FirstEpithet']) ? ' ' . $atomisedname['FirstEpithet'] : '';
                //$idqualifier .= ' ' . $qualifier;
                $idqualifier = $qualifier;
                $idqualifier .= ($qualifier != '?') ? ' ' : '';
                $idqualifier .= ($atomisedname['Rank'] && $atomisedname['InfraspecificEpithet']) 
                    ? $atomisedname['Rank'] . ' ' . $atomisedname['FirstEpithet'] : '';
            }
            return array($idqualifier, $qualifier, $insertionpoint);
        }
        else
            return FALSE;
    }
    
    /**
     * parseCoordinateMethod function
     * 
     * Splits HISPID Coordinate Method into DarwinCore georeferencedBy and georeferencedProtocol
     * 
     * @param DOMElement $unit 
     */
    private function parseCoordinateMethod($unit) {
        $ret = array();
        
        $georeferencedby = FALSE;
        $list = $unit->getElementsByTagName('georeferencedBy');
        if ($list->length > 0) {
            $georeferencedby = $list->item(0)->nodeValue;
        }
        else {
            $list = $unit->getElementsByTagName('MeasurementOrFactAtomised');
            if ($list->length > 0) {
                foreach ($list as $mof) {
                    $parameter = $mof->getElementsByTagName('Parameter');
                    $parameter = ($parameter->length) ? $parameter->item(0)->nodeValue : FALSE;

                    if ($parameter == 'georeferencedBy') {
                        $val = $mof->getElementsByTagName('LowerValue');
                        $georeferencedby = ($val->length) ? $val->item(0)->nodeValue : FALSE;
                    }
                }
            }
        }
        
        if ($georeferencedby) {
            $ret[] = array(
               'column' => 'dwc:georeferencedBy',
               'value' => $georeferencedby
            );
        }
        
        $nodelist = $unit->getElementsByTagName('CoordinateMethod');
        if ($nodelist->length) {
            $coordinatemethod = $nodelist->item(0)->nodeValue;
            if ($georeferencedby) {
                $ret[] = array(
                    'column' => 'dwc:georeferenceProtocol',
                    'value' => $coordinatemethod
                );
            }
            else {
                if (in_array($coordinatemethod, array('collector', 'compiler')))
                    $ret[] = array(
                        'column' => 'dwc:georeferencedBy',
                        'value' => $coordinatemethod
                    );
                else {
                    $ret[] = array(
                        'column' => 'dwc:georeferenceProtocol',
                        'value' => $coordinatemethod
                    );
                }
            }
        }
        return $ret;
    }
    
    /**
     *
     * @param DOMElement $unit 
     */
    private function Collectors($unit) {
        $ret = array();
        $gatheringagents = $unit->getElementsByTagName('Agents');
        if ($gatheringagents->length) {
            $list = $unit->getElementsByTagName('GatheringAgent');
            
            if ($list->length) {
                $primarycollectors = array();
                $additionalcollectors = array();
                foreach ($list as $key=>$gatheringagent) {
                    $agent = $gatheringagent->getElementsByTagName('AgentText');
                    if ($agent->length) {
                        $agent = $agent->item(0)->nodeValue;

                        if ($gatheringagent->hasAttribute('hispid:sequence'))
                            $sequence = $gatheringagent->getAttribute('hispid:sequence');
                        elseif ($gatheringagent->hasAttribute('abcd:sequence'))
                            $sequence = $gatheringagent->getAttribute('abcd:sequence');
                        elseif ($gatheringagent->hasAttribute('sequence'))
                            $sequence = $gatheringagent->getAttribute('sequence');
                        else
                            $sequence = $key+1;

                        if ($gatheringagent->hasAttribute('hispid:primarycollector')
                            || $gatheringagent->hasAttribute('abcd:primarycollector')
                            || $gatheringagent->hasAttribute('primarycollector')) {
                            if (in_array($gatheringagent->getAttribute('hispid:primarycollector'), array(1, 'true', 'TRUE'))
                                || in_array($gatheringagent->getAttribute('abcd:primarycollector'), array(1, 'true', 'TRUE'))
                                || in_array($gatheringagent->getAttribute('primarycollector'), array(1, 'true', 'TRUE'))
                                    )
                                $primarycollectors[] = array (
                                    'sequence' => $sequence,
                                    'agent' => $agent
                                );
                            else
                                $additionalcollectors[] = array (
                                    'sequence' => $sequence,
                                    'agent' => $agent
                                );
                        }
                        else 
                            $primarycollectors[] = array (
                                'sequence' => $sequence,
                                'agent' => $agent
                            );
                    }
                }
                    
                    

                if ($primarycollectors) {
                    $seq = array();
                    $agent = array();
                    foreach ($primarycollectors as $item) {
                        $seq[] = $item['sequence'];
                        $agent[] = $item['agent'];
                    }
                    array_multisort($seq, $agent);
                    $primarycollectors = implode('; ', $agent);

                    $ret[] = array(
                        'column' => 'Collectors',
                        'value' => $primarycollectors
                    );
                }
                if ($additionalcollectors) {
                    $seq = array();
                    $agent = array();
                    foreach ($additionalcollectors as $item) {
                        $seq[] = $item['sequence'];
                        $agent[] = $item['agent'];
                    }
                    array_multisort($seq, $agent);
                    $additionalcollectors = implode('; ', $agent);
                    $ret[] = array(
                        'column' => 'AdditionalCollectors',
                        'value' => $additionalcollectors
                    );
                }
                
            }
            else {
                $gatheringagentstext = $unit->getElementsByTagName('GatheringAgentsText');
                if ($gatheringagentstext->length) {
                    $ret[] = array(
                        'column' => 'Collectors',
                        'value' => $gatheringagentstext->item(0)->nodeValue
                    );
                }
            }
        }
        return $ret;
    
    }
    
    /**
     * Collectors function
     * 
     * Concatenates start and end collecting date into a valid ISO 8601 interval.
     * 
     * @param DOMElement $unit
     * @return array 
     */
    private function CollectingDate($unit) {
        $ret = FALSE;
        $eventdate = FALSE;
        $gathering = $unit->getElementsByTagName('Gathering');
        if ($gathering->length) {
            $gathering = $gathering->item(0);
            $startdate = $gathering->getElementsByTagName('ISODateTimeBegin');
            if ($startdate->length) {
                $eventdate = $this->ISODateLong($startdate->item(0)->nodeValue);
                $enddate = $gathering->getElementsByTagName('IsoDateTimeEnd');
                if ($enddate->length) 
                    $eventdate .= '/' . $this->ISODateLong($enddate->item(0)->nodeValue);
            }
            $ret = array(
                'column' => 'CollectingDate',
                'value' => $eventdate
            );
        }
        return $ret;
    }
    
    /**
     * ISODateLong function
     * 
     * Returns ISO date in long notation, i.e. with hyphens.
     * 
     * @param string $date
     * @return string 
     */
    private function ISODateLong($date) {
        if (strpos($date, '-') || strlen($date) == 4) {
            return $date;
        }
        else {
            if (strlen($date) == 6)
                $date = substr($date, 0, 4) . '-' . substr($date, 4, 2);
            elseif (strlen($date) == 8)
                $date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
        }
        return $date;
    }
    
    private function Altitude($unit) {
        $ret = array();
        $altitude = $unit->getElementsByTagName('Altitude');
        if ($altitude->length) {
            $altitude = $altitude->item(0);
            
            $altarray = array();
            $items = $altitude->getElementsByTagName('*');
            foreach ($items as $item){
                $tagname = $item->tagName;
                if (strpos($tagname, ':')) $tagname = substr ($tagname, strpos($tagname, ':')+1);
                $altarray[$tagname] = $item->nodeValue;
            }
            
            $altunit = 'metres';
            if (isset($altarray['UnitOfMeasurement']))
                $altunit = $altarray['UnitOfMeasurement'];
                
            if (isset($altarray['LowerValue'])) {
                $minalt = $altarray['LowerValue'];
                
                switch ($altunit) {
                    case 'feet':
                    case 'foot':
                    case 'ft':
                        $minalt = $minalt * 0.3048;
                        break;
                }
                
                $ret[] = array(
                    'column' => 'dwc:minimumElevationInMeters',
                    'value' => $minalt
                );
                if (isset($altarray['UpperValue'])) {
                    $maxalt = $altarray['UpperValue'];
                    
                     switch ($altunit) {
                        case 'feet':
                        case 'foot':
                        case 'ft':
                            $maxalt = $maxalt * 0.3048;
                            break;
                    }

                    $ret[] = array(
                        'column' => 'dwc:maximumElevationInMeters',
                        'value' => $maxalt
                    );
                }
            }
            if (isset($altarray['MeasurementOrFactText'])) {
                $ret[] = array(
                    'column' => 'dwc:verbatimElevation',
                    'value' => $altarray['MeasurementOrFactText']
                );
            }
            elseif (isset($altarray['UnitOfMeasurement']) && isset($altarray['LowerValue'])
                    && !in_array($altarray['UnitOfMeasurement'], array('m', 'metres'))) {
                if (isset($altarray['UpperValue']))
                    $text = $altarray['LowerValue'] . '-' . $altarray['UpperValue'] . ' ' . $altarray['UnitOfMeasurement'];
                else
                    $text = $altarray['LowerValue'] . $altarray['UnitOfMeasurement'];
                $ret[] = array(
                    'column' => 'dwc:verbatimElevation',
                    'value' => $text
                );
            }
        }
        return $ret;
    }
    
    private function Depth($unit) {
        $ret = array();
        $depth = $unit->getElementsByTagName('Depth');
        if ($depth->length) {
            $depth = $depth->item(0);
            $deptharray = array();
            $items = $depth->getElementsByTagName('*');
            foreach ($items as $item) {
                $tagname = $item->tagName;
                if (strpos($tagname, ':'))
                        $tagname = substr($tagname, strpos ($tagname, ':')+1);
                $deptharray[$tagname] = $item->nodeValue;
            
            }
            
            $depthunit = 'metres';
            if (isset($deptharray['UnitOfMeasurement']))
                $depthunit = $deptharray['UnitOfMeasurement'];
                
            if (isset($deptharray['LowerValue'])) {
                $mindepth = $deptharray['LowerValue'];
                
                switch ($depthunit) {
                    case 'feet':
                    case 'foot':
                    case 'ft':
                        $mindepth = $mindepth * 0.3048;
                        break;
                    case 'fath':
                        $mindepth = $mindepth * 1.8288;
                        break;
                    case 'inch':
                        $mindepth = $mindepth * 0.0254;
                        break;
                    case 'cent':
                        $mindepth = $mindepth * 100;
                        break;
                }
                
                $ret[] = array(
                    'column' => 'dwc:minimumDepthInMeters',
                    'value' => $mindepth
                );
                if (isset($deptharray['UpperValue'])) {
                    $maxdepth = $deptharray['UpperValue'];
                    
                switch ($depthunit) {
                    case 'feet':
                    case 'foot':
                    case 'ft':
                        $maxdepth = $maxdepth * 0.3048;
                        break;
                    case 'fath':
                        $maxdepth = $maxdepth * 1.8288;
                        break;
                    case 'inch':
                        $maxdepth = $maxdepth * 0.0254;
                        break;
                    case 'cent':
                        $maxdepth = $maxdepth * 100;
                        break;
                }

                    $ret[] = array(
                        'column' => 'dwc:maximumDepthInMeters',
                        'value' => $maxdepth
                    );
                }
            }
            if (isset($deptharray['MeasurementOrFactText'])) {
                $ret[] = array(
                    'column' => 'dwc:verbatimElevation',
                    'value' => $deptharray['MeasurementOrFactText']
                );
            }
            elseif (isset($deptharray['UnitOfMeasurement']) && isset($deptharray['LowerValue'])
                    && !in_array($deptharray['UnitOfMeasurement'], array('m', 'metres'))) {
                if (isset($deptharray['UpperValue']))
                    $text = $deptharray['LowerValue'] . '-' . $deptharray['UpperValue'] . ' ' . $deptharray['UnitOfMeasurement'];
                else
                    $text = $deptharray['LowerValue'] . $deptharray['UnitOfMeasurement'];
                $ret[] = array(
                    'column' => 'dwc:verbatimElevation',
                    'value' => $text
                );
            }
        }
        return $ret;
    }
    
    /**
     * ExHerbCatalogueNumber function
     * 
     * Concatenates the PreviousSourceInstitutionID and the PreviousUnitID. The function
     * assumes that PreviousUnit is used for the herbarium the (duplicate) specimen came 
     * from and will ignore all but the first PreviousUnitElement.
     * 
     * @param DOMElement $unit 
     */
    function ExHerbCatalogueNumber($unit) {
        $ret = array();
        $acquisition = $unit->getElementsByTagName('AcquisitionSourceID');
        if ($acquisition->length) {
            $ret[] = array(
                    'column' => 'ExHerb',
                    'value' => $acquisition->item(0)->nodeValue
                );
        }
        else {
            $previousunit = $unit->getElementsByTagName('PreviousUnit');
            if ($previousunit->length) {
                $ret[] = array(
                        'column' => 'ExHerb',
                        'value' => $previousunit->item(0)->getElementsByTagName('PreviousSourceInstitutionID')->item(0)->nodeValue
                    );
            }
        }
        
        $previousunit = $unit->getElementsByTagName('PreviousUnit');
        if ($previousunit->length) {
            $previousunit = $previousunit->item(0);
            $sourceinstitutionid = $previousunit->getElementsByTagName('PreviousSourceInstitutionID')->item(0)->nodeValue;
            $unitid = $previousunit->getElementsByTagName('PreviousUnitID')->item(0)->nodeValue;
            if (!in_array($unitid, array('Unknown', 'unknown'))) {
                $ret[] = array(
                    'column' => 'ExHerbCatalogueNumber',
                    'value' => $sourceinstitutionid . ' ' . $unitid
                );
            }
        }
        return $ret;
    }
    
    /**
     * ScientificNameAuthorship function
     * 
     * Concatenates abcd:AuthorTeam and abcd:AuthorTeamParenthesis into dwc:scientificNameAuthorship
     * 
     * @param DOMElement $identification
     * @return array 
     */
    function ScientificNameAuthorship($identification) {
        $author = array();
        $authorteamparenthesis = $identification->getElementsByTagName('AuthorTeamParenthesis');
        if ($authorteamparenthesis->length) $author[] = '(' . $authorteamparenthesis->item(0)->nodeValue . ')';
        $authorteam = $identification->getElementsByTagName('AuthorTeam');
        if ($authorteam->length) $author[] = $authorteam->item(0)->nodeValue;
        if ($author) {
            return implode(' ', $author);
        }
        return FALSE;
    }
    
    function TypeStatus($unit) {
        $type = $unit->getElementsByTagName('NomenclaturalTypeDesignation');
        if ($type->length) {
            $list = $type->item(0)->getElementsByTagName('*');
            $bits = array();
            foreach ($list as $item) {
                $tagname = $item->tagName;
                if (strpos($tagname, ':')) $tagname = substr($tagname, strpos($tagname, ':')+1);
                $bits[$tagname] = $item->nodeValue;
            }
            if (isset($bits['TypeStatus']) && isset($bits['FullScientificNameString'])) {
                $typestatus = '';
                if (isset($bits['DoubtfulFlag'])) {
                    $typestatus .= ucfirst($bits['DoubtfulFlag']);
                    if ($bits['DoubtfulFlag'] != '?') $typestatus .= ' ';
                }
                if ($bits['TypeStatus']) $typestatus .= strtoupper($bits['TypeStatus']) . ' of ';
                if ($bits['FullScientificNameString']) $typestatus .= $bits['FullScientificNameString'];
                if (isset($bits['FullName'])) {
                    $typestatus .= ', fid. ' . $bits['FullName'];
                    if (isset($bits['VerificationDate'])) 
                        $typestatus .= ', ' . $bits['VerificationDate'];
                }
                return array (
                    'column' => 'dwc:typeStatus',
                    'value' => $typestatus
                );
            }
        }
        return FALSE;
    }
    
    function locality($unit) {
        $list = $unit->getElementsByTagName('LocalityText');
        if ($list->length) {
            $loc = $list->item(0)->nodeValue;
            return [
                'column' => 'dwc:locality',
                'value' => $loc
            ];
        }
    }
    
    function habitat($unit) {
        $ret = array();
        $hab = array();
        $top = array();
        $assoc = FALSE;
        
        // topography
        $list = $unit->getElementsByTagName('AreaDetail');
        if ($list->length) {
            $top['Topography'] = $list->item(0)->nodeValue;
            $ret[] = array(
                'column' => 'hispid:topography',
                'value' => $top['Topography']
            );
        }
        
        // aspect
        $list = $unit->getElementsByTagName('Ordination');
        if ($list->length) {
            $top['Aspect'] = $list->item(0)->nodeValue;
            $ret[] = array(
                'column' => 'hispid:aspect',
                'value' => $top['Aspect']
            );
        }
        
        // associated taxa
        $list = $unit->getElementsByTagName('Synecology');
        if ($list->length) {
            $taxa = $list->item(0)->getElementsByTagName('FullScientificNameString');
            if ($taxa->length) {
                $names = array();
                foreach ($taxa as $item) {
                    $names[] = $item->nodeValue;
                }
                $assoc = implode('; ', $names);
            }
            else {
                $taxa = $list->item(0)->getElementsByTagName('Comment');
                if ($taxa->length)
                    $assoc = $taxa->item(0)->nodeValue;
            }
        }
        
        // habitat
        $list = $unit->getElementsByTagName('Biotope');
        if ($list->length) {
            /*$text = $list->item(0)->getElementsByTagName('Text');
            if ($text->length) {
                $hab['Habitat'] = $text->item(0)->nodeValue;
                $ret[] = array(
                    'column' => 'hispid:habitat',
                    'value' => $hab['Habitat']
                );
            }
            */
            
            // biotope measurement or facts
            $mofs = $list->item(0)->getElementsByTagName('MeasurementOrFactAtomised');
            if ($mofs->length) {
                foreach ($mofs as $item) {
                    $param = $item->getElementsByTagName('Parameter')->item(0)->nodeValue;
                    $value = $item->getElementsByTagName('LowerValue')->item(0)->nodeValue;
                    $hab[$param] = $value;
                    $ret[] = array(
                        'column' => 'hispid:' . strtolower($param),
                        'value' => $value
                    );
                }
            }
        }
        
        // HISPID Gathering
        $list = $unit->getElementsByTagName('HispidGathering');
        if ($list->length) {
            $gath = $list->item(0);
            
            $fieldarray = array(
                'Soil',
                'Substrate',
                'Vegetation'
            );
            
            foreach ($fieldarray as $field) {
                $list = $gath->getElementsByTagName($field);
                if ($list->length) {
                    $hab[$field] = $list->item(0)->nodeValue;
                    $ret[] = array(
                        'column' => 'hispid:' . strtolower($field),
                        'value' => $hab[$field]
                    );
                }
            }
        }
        
        
        if ($top) {
            $value = FALSE;
            if (isset($top['Topography'])) {
                $value = $top['Topography'];
            }
            
            if (isset($top['Aspect'])) {
                if ($value) {
                    if (substr($value, strlen(trim($value)), 1) == '.' )
                        $value = trim($value) . ' ';
                    else
                        $value = trim($value) . '. ';
                }
                $value .= '[Aspect:] ' . $top['Aspect'] . '.';
            }
            $ret[] = array(
                'column' => 'dwc:locationRemarks',
                'value' => $value
            );
        }
        
        if ($assoc) {
            $ret[] = array(
                'column' => 'dwc:associatedTaxa',
                'value' => $assoc
            );
        }
        
/*        if ($hab) {
            if (count($hab) == 1 && isset($hab['Habitat'])) {
                $ret[] = array(
                    'column' => 'dwc:habitat',
                    'value' => $hab['Habitat']
                );
            }
            else {
                $habitat = array();
                foreach ($hab as $key => $value) {
                    if ($key != 'Habitat')
                        $habitat[] = "[$key:] $value";
                }
                $ret[] = array(
                    'column' => 'dwc:habitat',
                    'value' => implode('; ', $habitat)
                );
            }
        }
*/        
        return $ret;
    }
    
    function bushBlitz($unit)
    {
        $ret = [];
        $blitz = false;
        $surveys = $unit->getElementsByTagName('NamedCollectionOrSurvey');
        if ($surveys->length) {
            $blitz = $surveys->item(0)->nodeValue;
        }
        else {
            $projects = $unit->getElementsByTagName('ProjectTitle');
            if ($projects->length) {
                $blitz = $projects->item(0)->nodeValue;
            }
        }
        if ($blitz && substr($blitz, 0, strlen('Bush Blitz')) == 'Bush Blitz') {
            $ret[] = [
                'column' => 'avh:bushBlitzExpedition',
                'value' => $blitz
            ];
            $eventRemarks = $unit->getElementsByTagName('eventRemarks');
            if ($eventRemarks->length == 0) {
                $ret[] = [
                    'column' => 'Unit/UnitExtension/Event/eventRemarks',
                    'value' => $blitz
                ];
            }
        }
        return $ret;
    }
}
?>
