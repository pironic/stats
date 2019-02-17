<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 17/02/2019
 * Time: 1:41 PM
 */
 
require_once('config.php');
$url = 'https://calgary.weatherstats.ca/data/solar_radiation-hourly.js?key=wd01&browser_zone=Mountain%20Daylight%20Time';

// Dragons below.

$payload = [];

$json = substr(file_get_contents($url),60,-4);
$needle = '/new Date\( (\d{4}), (\d{1,2}), (\d{1,2}), (\d{1,2}), (\d{1,2}), (\d{1,2}) \)/';
preg_match_all($needle, $json, $matches);
foreach($matches[0] as $key=>$match) {
    //Super weird... the month is a 0 based array, the day and year are not.
    //  new Date( 2019, 1, 17, 13, 0, 0 ) Feb 17, 2019, 13:00:00
    $external = $matches[2][$key]+1 . "/" .
        str_pad($matches[3][$key], 2, '0', STR_PAD_LEFT) . "/" . 
        str_pad($matches[1][$key], 2, '0', STR_PAD_LEFT) . " " . 
        str_pad($matches[4][$key], 2, '0', STR_PAD_LEFT) . ":" . 
        str_pad($matches[5][$key], 2, '0', STR_PAD_LEFT) . ":" . 
        str_pad($matches[6][$key], 2, '0', STR_PAD_LEFT) . " America/Edmonton";
    $timestamp = DateTime::createFromFormat("n/j/Y H:i:s P", $external);
    $json = str_replace($match,'"'.$timestamp->format("c").'"',$json);
}

$solar = json_decode($json,true,JSON_UNESCAPED_UNICODE);
if ($solar === null) die("JSON Error: ". json_last_error_msg());

foreach($solar['rows'] as $row) {
    // skip blank rows. 
    if ($row['c'][1]['v'] == null) continue;
    
    $items = [];
    $timestamp = $row['c'][0]['v'];
    $value = $row['c'][1]['v'];
    $items["i|solar_radiation"] = $value;

    array_push($payload,array('tags'=>array('host'=>'wstats'),'fields'=>$items,'timestamp'=>$timestamp));
}

if (count($payload) > 0) {
    
    print_r($payload);

    // post to echelon endpoint here.
    $curl = curl_init($cfg['echelon_url']);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER,
            array("Content-type: application/json",
            "API-KEY: ". $cfg['echelon_key']));
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));

    $json_response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    print_r($json_response);
    print_r($status);

    if ( $status != 200 ) {
        die("Error: call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
    }


    curl_close($curl);

    $response = json_decode($json_response, true);
} else {
    echo "empty payload, nothing to post\n";
}