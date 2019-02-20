<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 20/02/2019
 * Time: 9:32 AM
 */
 
require_once('lib.php');
$url = 'http://api.wunderground.com/api/'.$cfg['wug_key'].'/conditions/q/'.$cfg['wug_gps'].'.json';
$url = 'https://repono.writhem.com/wug.json';

$payload = [];

$wx = json_decode(file_get_contents($url),true,JSON_UNESCAPED_UNICODE);
if ($wx === null) die("JSON Error: ". json_last_error_msg());

$items = [];
print_r($wx['current_observation']);

$items["s|Conditions"] = $wx['current_observation']['weather'];
$items["f|Temperature"] = $wx['current_observation']['temp_c'];
$items["f|Humidity"] = $wx['current_observation']['relative_humidity'];
$items["f|BaroPressure"] = $wx['current_observation']['pressure_mb'];
$items["f|Dewpoint"] = $wx['current_observation']['dewpoint_c'];
$items["f|Feelslike"] = $wx['current_observation']['feelslike_c'];
$items["f|Visibility"] = $wx['current_observation']['visibility_km'];
$items["f|CurrentUV"] = $wx['current_observation']['UV'];
$items["f|Precipitation"] = $wx['current_observation']['precip_today_metric'];
$items["s|ConditionsIconUrl"] = $wx['current_observation']['icon_url'];

$items['f|WindDirection'] = $wind_directions[$wx['current_observation']['wind_dir']];
$items['f|WindDegrees'] = $wx['current_observation']['wind_degrees'];
$items['f|WindSpeedLow'] = (float)$wx['current_observation']['wind_kph'];
$items['f|WindSpeedHigh'] = (float)$wx['current_observation']['wind_gust_kph'];

array_push($payload,array('tags'=>array('host'=>'wunderg','station_id'=>$wx['current_observation']['station_id']),'fields'=>$items,'timestamp'=>$wx['current_observation']['observation_epoch']));

postToEchelon($payload);