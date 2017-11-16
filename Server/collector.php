<?php

// Copyright 2016-2017 SolarWinds Worldwide, LLC
// 
// Licensed under the Apache License, Version 2.0 (the "License");
//   you may not use this file except in compliance with the License.
//   You may obtain a copy of the License at
// 
//       http://www.apache.org/licenses/LICENSE-2.0
// 
//   Unless required by applicable law or agreed to in writing, software
//   distributed under the License is distributed on an "AS IS" BASIS,
//   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//   See the License for the specific language governing permissions and
//   limitations under the License.

// This script runs server side, gets the data from HTTP POST, cleans it for MySQL, and runs processor.
// TODO: code for receiving debug info has to be added to bluesky.sh on client, mysql tables added

// Minimal validation:
if (isset($_POST['serialNum'], $_POST['actionStep']) ) {
    // Connect to the database:
    $dbc = mysqli_connect('localhost', 'collector', 'CHANGETHIS', 'BlueSky');
    
    // If a connection was established, run the query:
    if ($dbc) {
        
        // Sanctify the provided information:
        $serialNum = mysqli_real_escape_string($dbc, trim($_POST['serialNum']));
        $actionStep = mysqli_real_escape_string($dbc, trim($_POST['actionStep']));
        if (isset($_POST['hostName']) ) {
        	$hostName = mysqli_real_escape_string($dbc, trim($_POST['hostName']));
        } else {
        	$hostName = "";
        }
        
        // Pass it on
        $procResult = `/usr/local/bin/processor.sh "$serialNum" "$actionStep" "$hostName"`;
        echo "$procResult";

    } else {
        echo 'ERROR: cant get dbc';
    }

} else {

  if (isset($_POST['newpub']) ) {
    $pubKey = ($_POST['newpub']);
    $keyResult = `/usr/local/bin/keymaster.sh "$pubKey"`;
    echo "$keyResult";
  } else {
    //debugReport=`curl $curlProxy -1 -s -S -m 600 --cacert "$ourHome/cacert.pem" -X POST --data-urlencode "serialNum=$serialNum" --data-urlencode "activity@/tmp/.bluAct" --data-urlencode "main@/tmp/.bluMain" --data-urlencode "helper@/tmp/.bluHelp" --data-urlencode "auto@/tmp/.bluAuto" --data-urlencode "auto1@/tmp/.bluAuto1" --data-urlencode "launchctl@/tmp/.bluLaunchd" --data-urlencode "settings@$ourHome/settings.plist" https://"$serverAddress"/cgi-bin/collector.php`
    if (isset($_POST['serialNum'],$_POST['activity'], $_POST['auto'], $_POST['launchctl']) ) {
      // Connect to the database:
      $dbc = mysqli_connect('localhost', 'collector', 'CHANGETHIS', 'BlueSky');

      // If a connection was established, run the query:
      if ($dbc) {
        // Sanctify the provided information:
        $serialNum = mysqli_real_escape_string($dbc, trim($_POST['serialNum']));
        $activity = mysqli_real_escape_string($dbc, trim($_POST['activity']));
        $auto = mysqli_real_escape_string($dbc, trim($_POST['auto']));
        $launchctl = mysqli_real_escape_string($dbc, trim($_POST['launchctl']));
                
				// These are optional
				if (isset($_POST['main'])) {
          $main = mysqli_real_escape_string($dbc, trim($_POST['main']));
				} else {
				  $main = '';
				}
				if (isset($_POST['helper'])) {
          $helper = mysqli_real_escape_string($dbc, trim($_POST['helper']));
				} else {
				  $helper = '';
				}
				if (isset($_POST['auto1'])) {
          $auto1 = mysqli_real_escape_string($dbc, trim($_POST['auto1']));
				} else {
				  $auto1 = '';
				}
				if (isset($_POST['settings'])) {
          $settings = mysqli_real_escape_string($dbc, trim($_POST['settings']));
				} else {
				  $settings = '';
				}
            
        // Run the query:
        $q = "INSERT INTO debug (serialNum,date,activity,auto,launchctl,main,helper,auto1,settings) VALUES ('$serialNum',CURRENT_TIMESTAMP,'$activity','$auto','$launchctl','$main','$helper','$auto1','$settings')";
        //$r = mysqli_query($dbc, $q); // TODO - Uncomment this when debug table is done

        // Report upon the results:
        if (mysqli_affected_rows($dbc) == 1) {
            echo 'Sweet';
        } else {
            echo 'ERROR: failed to upload report for debug';
        }
      } else {
          echo 'ERROR: cant get dbc for report';
      }

    } else {
      echo 'This page has been accessed in error.';
    }
  }
}

?>