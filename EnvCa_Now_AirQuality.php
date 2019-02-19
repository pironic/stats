<?php
require_once('lib.php');
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

postToEchelon($payload);