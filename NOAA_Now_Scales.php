<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 19/02/2019
 * Time: 10:38 PM
 */
 
require_once('lib.php');
$url = 'https://services.swpc.noaa.gov/products/noaa-scales.json';

$payload = [];

$scales = json_decode(file_get_contents($url),true,JSON_UNESCAPED_UNICODE);
if ($scales === null) die("JSON Error: ". json_last_error_msg());

foreach ($scales as $scale) {
    if ($scale["TimeStamp"] == "00:00:00") continue; // we dont want predictions to seep into our observations
    
    $items = [];
    $timestamp = strtotime($scale['DateStamp']." ".$scale['TimeStamp']);
    $items['f|RadioBlackouts'] = ($scale['R']['Scale'] == null ? 0 : $scale['R']['Scale']);
    $items['f|SolarRadiationStorm'] = ($scale['S']['Scale'] == null ? 0 : $scale['S']['Scale']);
    $items['f|GeomagneticStorm'] = ($scale['G']['Scale'] == null ? 0 : $scale['G']['Scale']);
    
    array_push($payload,array('tags'=>array('host'=>'noaa'),'fields'=>$items,'timestamp'=>$timestamp,'friendly'=>date('c',$timestamp)));
}

postToEchelon($payload);