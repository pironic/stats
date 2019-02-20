<?php
require ('config.php');

function postToEchelon($payload) {
    global $cfg;
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
            die("Error: call to URL {$cfg['echelon_url']} failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
        }


        curl_close($curl);

        $response = json_decode($json_response, true);
        return true;
    } else {
        echo "empty payload, nothing to post\n";
        return false;
    }
}


$wind_directions = ['N'=>1,
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