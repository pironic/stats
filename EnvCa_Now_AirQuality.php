<?php
require_once('config.php');
$url = 'https://weather.gc.ca/airquality/pages/multiple_stations/abaq-002_e.html';

// Dragons below.

$payload = [];
$items = [];

$haystack = file_get_contents($url) or die("feed not loading");

## capture the date
$needle = '/Calculated at:<\/a[^>]*[^\d]*>(.*)<\/div></';
preg_match($needle, $haystack, $matches);
if ($matches) {
    $date = explode(" ",$matches[0]);
    $datestr = "{$date[7]} {$date[6]} ".substr($date[8],0,4)." ".substr($date[2],4)." {$date[3]} {$date[4]}\n";
    $timestamp = strtotime($datestr);
} else {
    die ("something wrong with timestamp interpretation");
}

## capture the nw
preg_match('/Calgary Northwest<\/a[^>]*[^\d]*>((?:\d|\.|-){1,4})<\/b>/', $haystack, $matches);
if ($matches) 
    $items['i|air_qual_nw'] = (int)$matches[1];

## capture the central
preg_match('/Calgary Central<\/a[^>]*[^\d]*>((?:\d|\.|-){1,4})<\/b>/', $haystack, $matches);
if ($matches) 
    $items['i|air_qual_central'] = (int)$matches[1];

## capture the se
preg_match('/Calgary Southeast<\/a[^>]*[^\d]*>((?:\d|\.|-){1,4})<\/b>/', $haystack, $matches);
if ($matches) 
    $items['i|air_qual_se'] = (int)$matches[1];

## capture the overall
preg_match('/Calgary<\/a[^>]*[^\d]*>((?:\d|\.|-){1,4})<\/b>/', $haystack, $matches);
if ($matches) 
    $items['i|air_qual_overall'] = (int)$matches[1];

array_push($payload,array('tags'=>array('host'=>'envca'),'fields'=>$items,'timestamp'=>$timestamp));

print_r($payload);

if (count($payload) > 0) {
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
    echo "empty payload. ending";
}