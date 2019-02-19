<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 19/02/2019
 * Time: 12:30 PM
 */
require_once('lib.php');

$url_boulder = 'https://services.swpc.noaa.gov/json/boulder_k_index_1m.json';
$url_planetary = 'https://services.swpc.noaa.gov/json/planetary_k_index_1m.json';

$payload = [];

$boulder = json_decode(file_get_contents($url_boulder),true,JSON_UNESCAPED_UNICODE);
if ($boulder === null) die("JSON Error: ". json_last_error_msg());

$planetary = json_decode(file_get_contents($url_planetary),true,JSON_UNESCAPED_UNICODE);
if ($planetary === null) die("JSON Error: ". json_last_error_msg());

foreach ($boulder as $k) {    
    $items = [];
    $timestamp = strtotime($k['time_tag']);
    $items['f|K-Index'] = $k['k_index'];
    
    array_push($payload,array('tags'=>array('host'=>'noaa'),'fields'=>$items,'timestamp'=>$timestamp,'friendly'=>date('c',$timestamp)));
}

foreach ($planetary as $kp) {    
    $items = [];
    $timestamp = strtotime($kp['time_tag']);
    $items['f|Kp-Index'] = $kp['kp_index'];
    
    array_push($payload,array('tags'=>array('host'=>'noaa'),'fields'=>$items,'timestamp'=>$timestamp,'friendly'=>date('c',$timestamp)));
}

postToEchelon($payload);