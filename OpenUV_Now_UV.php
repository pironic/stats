<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 20/02/2019
 * Time: 10:58 AM
 */
 
require_once('lib.php');
$url = 'https://api.openuv.io/api/v1/uv?lat='.$cfg['gps_lat'].'&lng='.$cfg['gps_lng'];

$payload = [];

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_HTTPHEADER,
        array("Content-type: application/json",
        "x-access-token: ". $cfg['openuv_key']));
curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);

$json_response = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

$uv = json_decode($json_response,true,JSON_UNESCAPED_UNICODE);
if ($uv === null) die("JSON Error: ". json_last_error_msg());

$items = [];
$items["f|CurrentUV"] = $uv['result']['uv'];
$items["i|SET_1"] = $uv['result']['safe_exposure_time']['st1'];
$items["i|SET_2"] = $uv['result']['safe_exposure_time']['st2'];
$items["i|SET_3"] = $uv['result']['safe_exposure_time']['st3'];
$items["i|SET_4"] = $uv['result']['safe_exposure_time']['st4'];
$items["i|SET_5"] = $uv['result']['safe_exposure_time']['st5'];
$items["i|SET_6"] = $uv['result']['safe_exposure_time']['st6'];
$items["f|SunAzimuth"] = $uv['result']['sun_info']['sun_position']['azimuth'];
$items["f|SunAltitude"] = $uv['result']['sun_info']['sun_position']['altitude'];
array_push($payload,array('tags'=>array('host'=>'openuv'),'fields'=>$items,'timestamp'=>$uv['result']['uv_time']));

$items = [];
$items["f|Ozone"] = $uv['result']['ozone'];
array_push($payload,array('tags'=>array('host'=>'openuv'),'fields'=>$items,'timestamp'=>$uv['result']['ozone_time']));

$items = [];
$items["f|ForecastPeakUV"] = $uv['result']['uv_max'];
array_push($payload,array('tags'=>array('host'=>'openuv'),'fields'=>$items,'timestamp'=>$uv['result']['uv_max_time']));

// print_r($payload);
postToEchelon($payload);