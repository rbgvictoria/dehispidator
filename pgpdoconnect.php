<?php
$dsn = 'pgsql:dbname=avh_cache;host=localhost';
$user = '';
$password = '';

try {
    $pgdb = new PDO($dsn, $user, $password);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}

?>
