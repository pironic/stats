<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 20/12/18
 * Time: 3:45 PM
 */
 
require_once('config.php');
$url = 'http://weather.gc.ca/rss/warning/ab-52_e.xml';

// Dragons below.

$payload = [];
$items = [];

$xml = simplexml_load_file($url) or die("feed not loading");

$entries = $xml->entry;

foreach ($entries as $entry_key=>$entry) {
    // print_r($entry);
    
    $needle = '/(.*) (WARNING|WATCH|STATEMENT) (.*),/';
    $haystack = $entry->title;
    preg_match($needle, $haystack, $matches);
    if ($matches)  {
        // alerts found. capture the info.
        $items['alert_title'] = $entry->title;
        $items['alert_tag'] = $matches[2];
        $items['alert_status'] = $matches[3];
        $items['alert_summary'] = $entry->summary;
        
        array_push($payload,array('tags'=>array('host'=>'envca'),'fields'=>$items,'timestamp'=>$entry->updated));
    }

    print_r($payload);
}

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
    echo "no alerts, nothing to post";
}