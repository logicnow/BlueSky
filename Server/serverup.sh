#!/bin/bash

# Copyright 2016-2017 SolarWinds Worldwide, LLC

# Licensed under the Apache License, Version 2.0 (the "License");
#   you may not use this file except in compliance with the License.
#   You may obtain a copy of the License at

#       http://www.apache.org/licenses/LICENSE-2.0

#   Unless required by applicable law or agreed to in writing, software
#   distributed under the License is distributed on an "AS IS" BASIS,
#   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#   See the License for the specific language governing permissions and
#   limitations under the License.

## This script handles the sending of down or up alerts for computers marked with the Alert checkbox

function sendAlert {
  myQry="select hostname from computers where serialnum='$serialNum'"
  hostName=`$myCmd "$myQry"`
  
  lastDate=`date -d @"$lastConn" '+%Y-%m-%d %H:%M:%S %Z'`
  
  alertStat="$1"
  if [ "$alertStat" == "Down" ]; then
    messBody="You requested to be notified when $hostName with serial number $serialNum has been offline for more than 15 minutes. Last time we saw it was $lastDate"
  elif [ "$alertStat" == "Up" ]; then
    messBody="The computer $hostName with serial number $serialNum is now back online."
  else
    return
  fi
  
  if [ -e /usr/local/bin/emailHelper.sh ]; then
    /usr/local/bin/emailHelper.sh "BlueSky $alertStat Alert $serialNum" "$messBody"
  fi
}

myCmd="/usr/bin/mysql --defaults-file=/var/local/my.cnf BlueSky -N -B -e"

myQry="select serialnum from computers where alert='1'"
alertList=`$myCmd "$myQry"`

for serialNum in $alertList; do

	myQry="select downup from computers where serialnum='$serialNum'"
	firstStat=`$myCmd "$myQry"`
	if [ "$firstStat" == "NULL" ] || [ "$firstStat" == "" ]; then
	  firstStat=1
	fi
	#1 is up,<=0 is down, negative number is how many times this script has seen it as down

	# timestamp is an epoch populated by processor when it confirms a good connection
	myQry="select timestamp from computers where serialnum='$serialNum'"
	lastConn=`$myCmd "$myQry"`
	if [ "$lastConn" == "NULL" ] || [ "$lastConn" == "" ]; then
	  lastConn=0
	fi

	checkThresh=`date -d "10 minutes ago" "+%s"`

	if [ ${lastConn:-0} -lt $checkThresh ]; then
	  # its been quiet for more than 10 min, might be down
		#first do our own spot check to see if server is really down
		myQry="select blueskyid from computers where serialnum='$serialNum'"
		myPort=`$myCmd "$myQry"`
		sshPort=$((22000 + myPort))
		testSN=`ssh -p $sshPort -o ConnectTimeout=5 -o ConnectionAttempts=5 -o StrictHostKeyChecking=no -l bluesky -i /usr/local/bin/blueskyd localhost "/usr/bin/defaults read /var/bluesky/settings serial"`
		testExit=$?
		if [ $testExit -ne 0 ]; then
		  # we did not connect, mark down the counter
		  newStat=$(( firstStat - 1 ))
		  myQry="update computers set downup='$newStat' where serialnum='$serialNum'"
		  $myCmd "$myQry"
		  if [ ${newStat:-0} -eq -2 ]; then
			#this is the third time we have seen it as down - up to 10 min on checkin, 3 times with this script every 5 (0,-1,-2), time to alert
			sendAlert Down
			myQry="update computers set status='Alert sent for offline',datetime='$timeStamp' where serialnum='$serialNum'"
			$myCmd "$myQry"
		  fi
		  #TODO - send an extended down at larger interval?
		fi
	else
	  # server was last contacted in acceptable threshold
	  if [ ${firstStat:-0} -lt 1 ]; then
		#server was down last time, mark up
		myQry="update computers set downup='1' where serialnum='$serialNum'"
		$myCmd "$myQry"
		if [ ${firstStat:-0} -lt -1 ]; then 
		  #down alert has been sent, follow up
		  sendAlert Up
		  myQry="update computers set status='Recovered from alert',datetime='$timeStamp' where serialnum='$serialNum'"
		  $myCmd "$myQry"
		fi
	  fi
	fi

done

exit 0