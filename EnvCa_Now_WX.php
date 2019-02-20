<?php
require_once('lib.php');
$url = 'http://weather.gc.ca/rss/city/ab-52_e.xml';

// Dragons below.

$payload = [];
$items = [];

$xml = simplexml_load_file($url) or die("feed not loading");

$entries = $xml->entry;

foreach ($entries as $entry_key=>$entry) {
    if (substr($entry->title,0,7) == "Current") {
        ## capture the temp
        $needle = '/Temperature:<\/b> ((?:\d|\.|-){1,5})/';
        $haystack = $entry->summary;
        preg_match($needle, $haystack, $matches);
        if ($matches) 
            $items['f|Temperature'] = (float)$matches[1];

        ## capture the pressure
        preg_match('/Pressure \/ Tendency:<\/b> ((?:\d|\.|-){1,5}) kPa/', $entry->summary, $matches);
        if ($matches) 
            $items['f|BaroPressure'] = (float)$matches[1];
        
        ## capture the visibility
        preg_match('/Visibility:<\/b> ((?:\d|\.|-){1,5})/', $entry->summary, $matches);
        if ($matches) 
            $items['f|Visibility'] = (float)$matches[1];
        
        ## capture the humidity
        preg_match('/Humidity:<\/b> ((?:\d|\.|-){1,5})/', $entry->summary, $matches);
        if ($matches) 
            $items['f|Humidity'] = (float)$matches[1];
        
        ## capture the dew point
        preg_match('/Dewpoint:<\/b> ((?:\d|\.|-){1,5})/', $entry->summary, $matches);
        if ($matches) 
            $items['f|Dewpoint'] = (float)$matches[1];

        ## capture the Wind
        preg_match('/Wind:<\/b> ((?:[NESW]){1,3}) ((?:\d|\.|-){1,5}) km\/h(?: gust ((?:\d|\.|-){1,5}) km\/h|)/', $entry->summary, $matches);
        if ($matches) {
            $items['f|WindDirection'] = $wind_directions[$matches[1]];
            $items['f|WindDegrees'] = ($items['f|WindDirection'] - 1) * 45;
            if ($matches[3]) {
                $items['f|WindSpeedLow'] = (float)$matches[2];
                $items['f|WindSpeedHigh'] = (float)$matches[3];
                $items['f|WindSpeedAvg'] = (float)($matches[2] + $matches[3] / 2);
            } else {
                $items['f|WindSpeedAvg'] = (float)$matches[2];
            }
        }
        
        // We ignore the air quality from this url because there is a more indepth result at a different url. will hit that to get details stats.
        
        // TBD: Add subsequent days for forecasting.
        
        array_push($payload,array('tags'=>array('host'=>'envca'),'fields'=>$items,'timestamp'=>$entry->updated));
    } else {
        continue;
    }
}

postToEchelon($payload);