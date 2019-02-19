<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 17/02/2019
 * Time: 1:44 PM
 */
 
require_once('lib.php');
$url = 'https://calgary.weatherstats.ca/data/precipitation-daily.js?key=wd01&browser_zone=Mountain%20Daylight%20Time';

$payload = [];

$json = substr(file_get_contents($url),60,-4);
$needle = '/new Date\( (\d{4}), (\d{1,2}), (\d{1,2}) \)/';
preg_match_all($needle, $json, $matches);
foreach($matches[0] as $key=>$match) {
    //Super weird... the month is a 0 based array, the day and year are not.
    //  new Date( 2019, 1, 17, 13, 0, 0 ) Feb 17, 2019, 13:00:00
    $external = $matches[2][$key]+1 . "/" .
        str_pad($matches[3][$key], 2, '0', STR_PAD_LEFT) . "/" . 
        str_pad($matches[1][$key], 2, '0', STR_PAD_LEFT) . " 00:00:00 America/Edmonton";
    $timestamp = DateTime::createFromFormat("n/j/Y H:i:s P", $external);
    $json = str_replace($match,'"'.$timestamp->format("c").'"',$json);
}

$precip = json_decode($json,true,JSON_UNESCAPED_UNICODE);
if ($precip === null) die("JSON Error: ". json_last_error_msg());

foreach($precip['rows'] as $row) {
    // // skip blank rows. 
    // if ($row['c'][1]['v'] == null) continue;
    
    $items = [];
    $timestamp = $row['c'][0]['v'];
    $value = $row['c'][1]['v'];
    $items["f|Precipitation"] = $value;

    array_push($payload,array('tags'=>array('host'=>'wstats'),'fields'=>$items,'timestamp'=>$timestamp));
}

postToEchelon($payload);