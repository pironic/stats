<?php
/**
 * Created by PhpStorm.
 * User: Michael
 * Date: 20/12/18
 * Time: 3:45 PM
 */
 
require_once('config.php');
$url_archaic = 'http://climate.weather.gc.ca/climate_data/bulk_data_e.html?format=xml&stationID=2205&Year=2008&Month=1&Day=1&timeframe=4';
$url_recent = 'http://climate.weather.gc.ca/climate_data/bulk_data_e.html?format=xml&stationID=50430&Year=2008&Month=1&Day=1&timeframe=4';

// Dragons below.

$payload = [];

// load the old one first.
$archaic = simplexml_load_file($url_archaic) or die("feed not loading");

// now load the new one
$recent = simplexml_load_file($url_recent) or die("feed not loading");

$extremes = array();
// loop through old one first to establish baseline to compare the recent to.
echo "loading historic data into temporary array to compare new format to... ";
foreach ($archaic->month as $month_index=>$month) {
    $month_index = (string)$month->attributes()->index;
    $extremes[$month_index] = array();
    
    foreach ($month->day as $day_index=>$day) {
        $day_index = (string)$day->attributes()->index;
        $extremes[$month_index][$day_index] = array();

        foreach($day->temperature as $metric) {
            $year = (string)$metric->attributes()->year;
            $key = (string)$metric->attributes()->class;
            $value = (float)$metric;
            $extremes[$month_index][$day_index][$key] = $value;
            ($year>0?$extremes[$month_index][$day_index][$key."-year"] = $year:"");
        }
        foreach($day->precipitation as $metric) {
            $year = (string)$metric->attributes()->year;
            $key = (string)$metric->attributes()->class;
            $value = (float)$metric;
            $extremes[$month_index][$day_index][$key] = $value;
            ($year>0?$extremes[$month_index][$day_index][$key."-year"] = $year:"");
        }
    }
}
echo "done. \n";

echo "loading newer format to compare to old ones...\n";
foreach ($recent->month as $month_index=>$month) {
    $month_index = (string)$month->attributes()->index;
    foreach ($month->day as $day_index=>$day) {
        $day_index = (string)$day->attributes()->index;

        foreach($day->temperature as $metric) {
            if ((string)$metric!==null) {
                $year = (string)$metric->attributes()->year;
                $key = (string)$metric->attributes()->class;
                $value = (float)$metric;
                
                if ($value !== 0.0) {
                    if(substr($key,-3) === "Min") {
                        if ($value<$extremes[$month_index][$day_index][$key]) {
                            echo $month_index."/".$day_index.": ".$key."=".$value."<".$extremes[$month_index][$day_index][$key]." ? ".($value<$extremes[$month_index][$day_index][$key]). "\n";
                            
                            $extremes[$month_index][$day_index][$key] = $value;
                            ($year>0?$extremes[$month_index][$day_index][$key."-year"] = $year:"");
                        }
                    } else {
                        if ($value>$extremes[$month_index][$day_index][$key]) {
                            echo $month_index."/".$day_index.": ".$key."=".$value.">".$extremes[$month_index][$day_index][$key]." ? ".($value>$extremes[$month_index][$day_index][$key]). "\n";
                                
                            $extremes[$month_index][$day_index][$key] = $value;
                            ($year>0?$extremes[$month_index][$day_index][$key."-year"] = $year:"");
                        }
                    }
                }
            }
        }
        foreach($day->precipitation as $metric) {
            if ((string)$metric!==null) {
                $year = (string)$metric->attributes()->year;
                $key = (string)$metric->attributes()->class;
                $value = (float)$metric;
                
                if ($value>$extremes[$month_index][$day_index][$key]) {
                    echo $month_index."/".$day_index.": ".$key."=".$value.">".$extremes[$month_index][$day_index][$key]." ? ".($value<$extremes[$month_index][$day_index][$key]). "\n";
                    
                    $extremes[$month_index][$day_index][$key] = $value;
                    ($year>0?$extremes[$month_index][$day_index][$key."-year"] = $year:"");
                }
            }
        }
    }
}    
echo "done. \nbuilding payload...";

foreach ($extremes as $month_index=>$month) {
    foreach ($month as $day_index=>$day) {
        // do not attempt to save Feb 29th if it's not a leap year!
        if (!date("L")) { if ($month_index == 2 && $day_index == 29) { continue; } }
        
        $date = strtotime(date("Y")."-".$month_index."-".$day_index." 00:00:00 America/Edmonton"); //2008-09-11 00:00:00 America/Edmonton
        $timestamp = date("U",$date);
        
        $items = [];
        foreach($day as $key=>$value) {
            if (substr($key,-5) == "-year") {
                $items["i|".$key] = $value;
            } else {
                $items["f|".$key] = $value;
            }
        }
        array_push($payload,array('tags'=>array('host'=>'envca'),'fields'=>$items,'timestamp'=>$timestamp));
        
        echo date('c',$timestamp). " ". substr(print_r($items,true),6);
    }
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