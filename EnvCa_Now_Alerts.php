<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 20/12/18
 * Time: 3:45 PM
 */
 
require_once('lib.php');
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

postToEchelon($payload);