<?php

/**
 * Hispid3ToCsv class
 * 
 * Converts HISPID3 to CSV.
 * 
 * @package Dehispidator
 * @author Niels Klazenga
 * @copyright Copyright (c) 2012, Council of Heads of Australian Herbaria (CHAH)
 * @license http://creativecommons.org/licenses/by/3.0/au/ CC BY 3.0 
 */
class Hispid3ToCsv {
    
    /**
     * parseHISPID3 function
     * 
     * Parses HISPID3 and returns a two-dimensional array, the first dimension
     * being the records or rows and the second dimension the field-value pairs.
     * 
     * @param string $hispid
     * @return array 
     */
    function parseHispid3($hispid) {
        /*
         * Get the records by cutting off the transfer file metadata at the start
         * of the file and the endfile bit at the end
         */
        preg_match('/,[\s]+{/', $hispid, $matches);
        $hispid = trim(substr($hispid, strpos($hispid, $matches[0])+  strlen($matches[0])));
        
        preg_match('/}[\s]+endfile/', $hispid, $matches);
        $hispid = trim(substr($hispid, 0, strpos($hispid, $matches[0])));
        
        /*
         * Split the string in individual records. Records are between braces in HISPID3,
         * so the string is split on a regular expression that searches for an opening and 
         * closing brace that have only whitespace in between.
         */
        $units = preg_split("/}[\s]+{/", $hispid);
        foreach ($units as $key=>$unit) {
            
            /*
             * Split the record string in individual fields. Fields are separated by a 
             * comma and line break, so we look for a comma and whitespace containing at
             * least a line-break.
             */
            $fields = preg_split('/,[\s]*[\n]+[\s]*/', $unit);
            $cols = array();
            foreach ($fields as $field) {
                /*
                 * Separate the transfer code and the value. A field in HISPID has the
                 * transfer code, some whitespace and than the value, which is either
                 * numerical or a string enclosed by double quotes. 
                 */
                $field = trim(preg_replace('/[\s]+/', ' ', $field));
                $col = preg_split('/[\s]+/', $field, 2);
                if (count($col) == 2)
                    $cols[] = array (
                        'field' => $col[0],
                        'value' => (substr($col[1], strlen($col[1])-1) == ',') ? 
                            substr($col[1], 0, strlen($col[1])-1): $col[1]
                    );
            }
            $units[$key] = $cols;
        }
        return $units;
        
    }
    
    /**
     * outputToCsv
     * 
     * Ouputs the parsed data to a CSV string, i.e. rows separated by line breaks 
     * and columns by commas. It does so by looping through the array of parsed
     * data twice, the first time to get the column names and the second time to
     * get the values. After the first round an array with unique column names is 
     * created. In the second round of looping hrough the input data the input row 
     * is checked for a value for each itemi n the column array. If there is a value
     * it is stored in the output row array; otherwise an empty string is stored. 
     * The output row array is then imploded into a string glued together by commas. 
     * After the end of the second loop, the output array is glued together by
     * line-breaks and returned as a CSV string.
     * 
     * @param array $parsed
     * @return string
     */
    function outputToCsv($parsed) {
        $csv = array();
        
        /*
         * Get the column names 
         */
        $cols = array();
        foreach ($parsed as $unit) {
            foreach ($unit as $item) {
                $cols[] = $item['field'];
            }
        }
        
        /*
         * Get the unique column names 
         */
        $cols = array_unique($cols);
        
        /*
         * Add the header row to the CSV array
         */
        $csv[] = implode(',', array_values($cols));
        
        /*
         * Get the values 
         */
        foreach ($parsed as $unit) {
            
            /*
             * From the input row array create an array with column names and
             * one with column (cell) values 
             */
            $rowcols = array();
            $values = array();
            foreach ($unit as $item) {
                $rowcols[] = $item['field'];
                $values[] = $item['value'];
            }
            
            /*
             * For each output column check if there is a cell in the row. If so 
             * store its value in the output row array; otherwise store an empty
             * string
             */
            $row = array();
            foreach ($cols as $col) {
                $key = array_search($col, $rowcols);
                if ($key !== FALSE) 
                    $row[] = $values[$key];
                else
                    $row[] = '""';
            }
            
            /*
             * Add the row to the CSV array 
             */
            $csv[] = implode(',', $row);
        }
        $csv = implode("\n", $csv);
        return $csv;
    }
}
?>
