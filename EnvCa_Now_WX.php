<?php
require_once('config.php');
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
            $directions = ['N'=>1,
                'NNE'=>1.5,
                'NE'=>2,
                'ENE'=>2.5,
                'E'=>3,
                'ESE'=>3.5,
                'SE'=>4,
                'SSE'=>4.5,
                'S'=>5,
                'SSW'=>5.5,
                'SW'=>6,
                'WSW'=>6.5,
                'W'=>7,
                'WNW'=>7.5,
                'NW'=>8,
                'NNW'=>8.5,
                'N'=>9];
            $items['f|WindDirection'] = $directions[$matches[1]];
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
    print_r($payload);
}

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