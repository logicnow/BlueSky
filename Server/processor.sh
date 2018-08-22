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

# This script runs server-side, gets the data from collector.php, parses, writes to MySQL, and returns action step.

# grab the inputs
serialNum="$1"
actionStep="$2"
hostName="$3"

# did we get everything?
if [ "$serialNum" == "" ] || [ "$actionStep" == "" ]; then
    echo "ERROR: badinput"
    exit 1
fi

function allGood {
	echo "OK"
	myQry="update computers set status='Connection is good',datetime='$timeStamp' where serialnum='$serialNum'"
	$myCmd "$myQry"
	timeEpoch=`date +%s`
	myQry="update computers set timestamp='$timeEpoch' where serialnum='$serialNum'"
	$myCmd "$myQry"
}

function snMismatch {
	echo "Serial mismatch. Returned: $testConn"
	myQry="update computers set status='ERROR: serial mismatch returned $testConn',datetime='$timeStamp' where serialnum='$serialNum'"
	$myCmd "$myQry"
}

myCmd="/usr/bin/mysql --defaults-file=/var/local/my.cnf BlueSky -N -B -e"
timeStamp=`date '+%Y-%m-%d %H:%M:%S %Z'`

case "$actionStep" in

"register")
# adds the computer to the database
myQry="select id from computers where serialnum='$serialNum'"
compRec=`$myCmd "$myQry"`

#get the default bluesky user name
loginName=`cat /usr/local/bin/BlueSky/Server/defaultLogin.txt 2> /dev/null`

if [ "$compRec" == "" ]; then

  #fetch unique ID
  myQry="SELECT MIN(t1.blueskyid + 1) AS nextID FROM computers t1 LEFT JOIN computers t2 ON t1.blueskyid + 1 = t2.blueskyid WHERE t2.blueskyid IS NULL"
  bluId=`$myCmd "$myQry"`

  if [ "$bluId" == "NULL" ] || [ "$bluId" == "" ]; then
  	bluId=1
  fi

  #safety check
  if [ ${bluId:-0} -gt 1949 ]; then
    echo "ERROR: maximum limit reached"
    exit 1
  fi
  #set insert
  myQry="insert into computers (serialnum,hostname,sharingname,registered,blueskyid,username) VALUES ('$serialNum','$hostName','$hostName','$timeStamp','$bluId','$loginName');"
else
  # if we have a default user name, and if the existing field is blank, then set it
  if [ "$loginName" != "" ]; then
	  myQry="select username from computers where id='$compRec'"
	  existingUser=`$myCmd "$myQry"`
	  if [ "$existingUser" == "" ] || [ "$existingUser" == "NULL" ]; then
		myQry="update computers set username='$loginName' where id='$compRec'"
		$myCmd "$myQry"
	  fi
  fi
  #set update
  myQry="update computers set registered='$timeStamp', sharingname='$hostName' where id='$compRec'"
fi
# above if/then should end in the appropriate query - either insert for new, or update for existing
$myCmd "$myQry"
if [ $? -eq 0 ]; then
  echo "Registered"
fi
;;

"port")
# looks up the port number and sends it
myQry="select blueskyid from computers where serialnum='$serialNum'"
myPort=`$myCmd "$myQry"`
if [ "$myPort" != "" ]; then
  echo "$myPort"
fi
;;

"user")
# looks up the default user if any and sends it
myQry="select username from computers where serialnum='$serialNum'"
myUser=`$myCmd "$myQry"`
if [ "$myUser" != "" ] && [ "$myUser" != "NULL" ]; then
  echo "$myUser"
else
  # TODO: put default login in global table
  myUser=`cat /usr/local/bin/BlueSky/Server/defaultLogin.txt`
  if [ "$myUser" != "" ]; then
    echo "$myUser"
  fi
fi
;;

"status")
# attempts an ssh connection back through the tunnel
# also sends self destruct, notify mail

# self destruct
myQry="select selfdestruct from computers where serialnum='$serialNum'"
selfDestruct=`$myCmd "$myQry"`
#TODO - read notes and only concat if empty
if [ "$selfDestruct" == "1" ]; then
	echo "selfdestruct"
	myQry="update computers set status='Remote removal initiated',datetime='$timeStamp' where serialnum='$serialNum'"
	$myCmd "$myQry"
	myQry="update computers set selfdestruct=0 where serialnum='$serialNum'"
	$myCmd "$myQry"
	exit 0
fi

# can we hit it?
myQry="select blueskyid from computers where serialnum='$serialNum'"
myPort=`$myCmd "$myQry"`
sshPort=$((22000 + myPort))
testConn=`ssh -p $sshPort -o StrictHostKeyChecking=no -o ConnectTimeout=10 -l bluesky -o BatchMode=yes -i /usr/local/bin/BlueSky/Server/blueskyd localhost "/usr/bin/defaults read /var/bluesky/settings serial"`
testExit=$?
if [ $testExit -eq 0 ]; then
  if [ "$testConn" == "$serialNum" ]; then
    allGood
  else
    snMismatch
  fi
else #either down or defaults is messed up, try using PlistBuddy
	testConn2=`ssh -p $sshPort -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -l bluesky -i /usr/local/bin/BlueSky/Server/blueskyd localhost "/usr/libexec/PlistBuddy -c 'Print serial' /var/bluesky/settings.plist" 2>&1`
	testExit2=$?
	if [ $testExit2 -eq 0 ]; then
		if [ "$testConn2" == "$serialNum" ]; then
			allGood
		else
			snMismatch
		fi
	else #it's down - lets find out why
		if [[ $testConn2 = *"ssh_exchange_identification"* ]]; then
			# PKI exchange issue for bluesky user - lets return OK to keep tunnel up.
			echo "OK"
			myQry="update computers set status='ERROR: tunnel issue TO client',datetime='$timeStamp' where serialnum='$serialNum'"
		elif [[ $testConn2 = *"Permission denied"* ]]; then
			# Most likely prompting for password auth - key issue - lets return OK to keep tunnel up.
			echo "OK"
			myQry="update computers set status='ERROR: cannot verify serial number',datetime='$timeStamp' where serialnum='$serialNum'"
		else
			echo "Cannot connect."
			myQry="update computers set status='ERROR: no tunnel established',datetime='$timeStamp' where serialnum='$serialNum'"
		fi
		$myCmd "$myQry"
	fi
fi

# notify
myQry="select notify from computers where serialnum='$serialNum'"
notifyMe=`$myCmd "$myQry"`
if [ "$notifyMe" == "1" ]; then
	myQry="select email from computers where serialnum='$serialNum'"
	emailAddr=`$myCmd "$myQry"`
	if [ "$emailAddr" == "" ] || [ "$emailAddr" == "NULL" ]; then
		myQry="select defaultemail from computers"
		emailAddr=`$myCmd "$myQry"`
	fi
	myQry="select hostname from computers where serialnum='$serialNum'"
	hostName=`$myCmd "$myQry"`
	myQry="select status from computers where serialnum='$serialNum'"
	currStat=`$myCmd "$myQry"`
	myQry="select status from username where serialnum='$serialNum'"
	myUser=`$myCmd "$myQry"`

	if [ -e /usr/local/bin/BlueSky/Server/emailHelper.sh ]; then
		serverFQDN=`cat /usr/local/bin/BlueSky/Server/server.txt`
		/usr/local/bin/BlueSky/Server/emailHelper.sh "BlueSky Notification $serialNum" "You requested to be notified when we next saw $hostName with serial number $serialNum, ID: $myPort.
https://$serverFQDN/blu=$myPort
SSH bluesky://com.solarwindsmsp.bluesky.admin?blueSkyID=$myPort&user=$myUser&action=ssh
VNC bluesky://com.solarwindsmsp.bluesky.admin?blueSkyID=$myPort&user=$myUser&action=vnc
SCP bluesky://com.solarwindsmsp.bluesky.admin?blueSkyID=$myPort&user=$myUser&action=scp"
	fi

	myQry="update computers set notify=0 where serialnum='$serialNum'"
	$myCmd "$myQry"
fi

;;

esac

exit 0
