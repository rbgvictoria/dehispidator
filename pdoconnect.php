<?php

class PdoConnect 
{
    public static function connect($driver, $host, $database, $username, $passwd, $port=false) 
    {
        if ($port) {
            $dsn = "$driver:host=$host;port=$port;dbname=$database";
        }
        else {
            $dsn = "$driver:host=$host;dbname=$database";
        }
        try {
            $db = new PDO($dsn, $username, $passwd, 
            array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
            return $db;
        }
        catch (PDOException $e) {
            exit('PDO connection error: ' . $e);
        }        
    }
}