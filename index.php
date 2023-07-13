<?php

/********* CONFIG ********/
$clusterEndpoint = getenv('DB_ENDPOINT');
$clusterPort = getenv('DB_PORT');
$clusterRegion = getenv('AWS_REGION');
$dbUsername = getenv('DB_USER');
$dbDatabase = getenv('DB_NAME');
/*************************/

// AWS-PHP-SDK installed via Composer
require 'vendor/autoload.php';

use Aws\Credentials\CredentialProvider;

$provider = CredentialProvider::assumeRoleWithWebIdentityCredentialProvider();
// Cache the results in a memoize function to avoid loading and parsing
// the ini file on every API operation
$provider = CredentialProvider::memoize($provider);

$RdsAuthGenerator = new Aws\Rds\AuthTokenGenerator($provider);

$token = $RdsAuthGenerator->createToken($clusterEndpoint . ":" . $clusterPort, $clusterRegion, $dbUsername);

if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}

$db = pg_connect("host=$clusterEndpoint user=$dbUsername password=$token dbname=$dbDatabase sslmode=require");

   if(!$db) {
      echo "Error in opening database\n";
   } else {
      $sql = "select iprange,geoname from ref_ip_blocks where iprange >>= '$ip'::ip4r;";
      $ret = pg_query($db, $sql);
      if(!$ret) {
         echo pg_last_error($db);
         exit;
      } 
      while($row = pg_fetch_row($ret)) {
         header('Content-Type: application/json');

         echo json_encode(array(
                    "your_ip" => $ip,
                    "countrycode" => $row[1],
                               ), JSON_PRETTY_PRINT);
      }
      pg_close($db);
   }

?>
