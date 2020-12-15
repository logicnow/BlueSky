#!/bin/bash

# c)2011-2014 Best Macs, Inc.
# c)2014-2015 Mac-MSP LLC
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

# This script is called by launchd every 5 minutes.
# Ensures that the connection to BlueSky is up and running, attempts repair if there is a problem.

# Set this to a different location if you'd prefer it live somewhere else
ourHome="/var/bluesky"

bVer="2.3.2"

# planting a debug flag runs bash in -x so you get all the output
if [ -e "$ourHome/.debug" ]; then
  set -x
fi

function logMe {
# gets message from first argument attaches date stamp and puts it in our log file
# if debug flag is present, echo the log message to stdout
  logMsg="$1"
  logFile="$ourHome/activity.txt"
  if [ ! -e "$logFile" ]; then
    touch "$logFile"
  fi
  dateStamp=`date '+%Y-%m-%d %H:%M:%S'`
  echo "$dateStamp - v$bVer - $logMsg" >> "$logFile"
  if [ -e "$ourHome/.debug" ]; then
    echo "$logMsg"
  fi
}

function getAutoPid {
  autoPid=`head -n 1 "$ourHome/autossh.pid"`
  if [ "$autoPid" != "" ]; then
  	autoCheck=`ps -ax | awk '{ print $1 }' | grep ^"$autoPid"`
  else
  	autoCheck=""
  fi
  if [ "$autoCheck" == "" ]; then
    #not running on advertised pid
    rm -f "$ourHome/autossh.pid"
    logMe "autossh not present on saved pid"
    autoPid=""
    #see if its running rogue
    autoProc=`ps -ax | grep "$ourHome/autossh" | grep -v grep`
    if [ "$autoProc" != "" ]; then
      autoPid=`echo "$autoProc" | awk '{ print $1 }'`
      echo "$autoPid" > "$ourHome/autossh.pid"
      logMe "found autossh rogue on $autoPid"
    fi
  else
    logMe "found autossh running on $autoPid"
  fi
}

function killShells {
  #start by taking down autossh
  getAutoPid
  if [ "$autoPid" != "" ]; then
    kill -9 "$autoPid"
  fi
  #now go after any rogue SSH processes
  shellList=`ps -ax | grep ssh | grep 'bluesky\@' | awk '{ print $1 }'`
  for shellPid in $shellList; do
    kill -9 $shellPid
  done
  #if they are still alive, ask for help
  getAutoPid
  shellList=`ps -ax | grep ssh | grep 'bluesky\@' | awk '{ print $1 }'`
  if [ "$shellList" != "" ] || [ "$autoPid" != "" ]; then
  	echo "contractKiller" > "$ourHome/.getHelp"
  	sleep 1
  fi
}

function rollLog {
  logName="$1"
  if [ -e "$ourHome/$logName" ];then
    rollCount=5
    rm -f "$ourHome/$logName.$rollCount" &> /dev/null
    while [ $rollCount -gt 0 ]; do
      let prevCount=rollCount-1
      if [ -e "$ourHome/$logName.$prevCount" ]; then
        mv "$ourHome/$logName.$prevCount" "$ourHome/$logName.$rollCount"
      fi
      if [ $prevCount -eq 0 ]; then
        mv "$ourHome/$logName" "$ourHome/$logName.$rollCount"
      fi
      rollCount=$prevCount
    done
    timeStamp=`date "+%Y-%m-%d %H:%M:%S"`
    echo "Log file created at $timeStamp" > "$ourHome/$logName"
  fi
}

function startMeUp {
  export AUTOSSH_PIDFILE="$ourHome/autossh.pid"
  export AUTOSSH_LOGFILE="$ourHome/autossh.log"
  #rollLog autossh.log
  timeStamp=`date "+%Y-%m-%d %H:%M:%S"`
  echo "$timeStamp BlueSky starting AutoSSH"
  # check for alternate SSH port
  altPort=`/usr/libexec/PlistBuddy -c "Print :altport" "$ourHome/settings.plist"  2> /dev/null`
  if [ "$altPort" == "" ]; then
    altPort=22
  else
    logMe "SSH port is set to $altPort per settings"
  fi
  # is this 10.6 which doesn't support UseRoaming or 10.12+ which doesn't need the flag?
  if [ ${osVersionMajor:-0} -eq 10 ] && [ "$osVersionMinor" != "6" ] && [ ${osVersionMinor:-0} -lt 12 ]; then
    noRoam="-o UseRoaming=no"
  fi
  ## main command right here
  $ourHome/autossh -M $monport -f \
  -c $prefCipher -m $msgAuth \
  $kexAlg \
  -o HostKeyAlgorithms=$keyAlg \
  -nNT -R $sshport:127.0.0.1:$altPort -R $vncport:127.0.0.1:5900 -p 3122 \
  $noRoam \
  -i "$ourHome/.ssh/bluesky_client" bluesky@$blueskyServer
  #echo "$!" > "$ourHome/autossh.pid"
  # are we live?
  sleep 5
  while [ ${autoTimer:-0} -lt 35 ]; do
	sshProc=`ps -ax | grep ssh | grep 'bluesky\@'`
	if [ "$sshProc" != "" ]; then
		break
	fi
	sleep 1
	(( autoTimer++ ))
  done
  # looks like it started up, lets check
  getAutoPid
  if [ "$autoPid" == "" ]; then
    logMe "ERROR - autossh wont start, check logs. Exiting."
    exit 1
  else
  	sshProc=`ps -ax | grep ssh | grep 'bluesky\@'`
  	if [ "$sshProc" != "" ]; then
	  logMe "autossh started successfully"
	else
	  logMe "ERROR - autossh is running but no tunnel, check logs. Exiting."
	  exit 1
	fi
  fi
}

function restartConnection {
  killShells
  startMeUp
}

function reKey {
  logMe "Running re-key sequence"
  # make unique ssh key pair
  rm -f "$ourHome/.ssh/bluesky_client"
  rm -f "$ourHome/.ssh/bluesky_client.pub"
  ssh-keygen -q -t $keyAlg -N "" -f "$ourHome/.ssh/bluesky_client" -C "$serialNum"
  pubKey=`cat "$ourHome/.ssh/bluesky_client.pub"`
  if [ "$pubKey" == "" ]; then
    logMe "ERROR - reKey failed and we are broken. Please reinstall."
  	exit 1
  fi
  chown bluesky "$ourHome/.ssh/bluesky_client"
  chmod 600 "$ourHome/.ssh/bluesky_client"
  chown bluesky "$ourHome/.ssh/bluesky_client.pub"
  chmod 600 "$ourHome/.ssh/bluesky_client.pub"

  # server will require encryption
  pubKey=`openssl smime -encrypt -aes256 -in ~/.ssh/bluesky_client.pub -outform PEM "$ourHome/blueskyclient.pub"`
  if [ "$pubKey" == "" ]; then
    logMe "ERROR - reKey failed and we are broken. Please reinstall."
  	exit 1
  fi

  # upload pubkey
  installResult=`curl $curlProxy -s -S -m 60 -1 --retry 4 --cacert "$ourHome/cacert.pem" -X POST --data-urlencode "newpub=$pubKey" https://$blueskyServer/cgi-bin/collector.php`
  curlExit=$?
  if [ "$installResult" != "Installed" ] || [ $curlExit -ne 0 ]; then
    logMe "ERROR - upload of new public key failed. Exiting."
    exit 1
  fi

  # get sharing name and Watchman Monitoring client group if present
  hostName=`scutil --get ComputerName`
  if [ "$hostName" == "" ]; then
 	hostName=`hostname`
  fi
  wmCG=`defaults read /Library/MonitoringClient/ClientSettings ClientGroup`
  if [ "$wmCG" != "" ]; then
  	hostName="$wmCG - $hostName"
  fi

  # upload info to get registered
  uploadResult=`curl $curlProxy -s -S -m 60 -1 --retry 4 --cacert "$ourHome/cacert.pem" -X POST --data-urlencode "serialNum=$serialNum" -d actionStep=register --data-urlencode "hostName=$hostName" https://$blueskyServer/cgi-bin/collector.php`
  curlExit=$?
  if [ "$uploadResult" != "Registered" ] || [ $curlExit -ne 0 ]; then
    logMe "ERROR - registration with server failed. Exiting."
  	exit 1
  fi

  /usr/libexec/PlistBuddy -c "Add :keytime integer `date +%s`" "$ourHome/settings.plist" 2> /dev/null
  /usr/libexec/PlistBuddy -c "Set :keytime `date +%s`" "$ourHome/settings.plist"
}

function serialMonster {
# reads serial number in settings and checks it against hardware - helpful if we are cloned or blank logic board
# sets serialNum for rest of script
savedNum=`/usr/libexec/PlistBuddy -c "Print :serial" "$ourHome/settings.plist"  2> /dev/null`
hwNum=`ioreg -l | grep IOPlatformSerialNumber | awk '{print $4}' |  cut -d \" -f 2`
if [ "$hwNum" == "" ]; then
  hwNum=`system_profiler SPHardwareDataType | grep "Serial Number" | head -n 1 | tr -d "'\";()\\" | awk '{print $NF}'`
fi
# is hardware serial a blank logic board
if [[ "$hwNum" == *"Available"* ]] || [[ "$hwNum" == *"Serial"* ]] || [[ "$hwNum" == *"Number"* ]] || [ "$hwNum" == "" ]; then
  blankBoard=1
fi

# do we match?
if [ "$savedNum" == "$hwNum" ] && [ "$hwNum" != "" ]; then
  #that was easy
  serialNum="$savedNum"
else
  if [ ${blankBoard:-0} -eq 1 ] && [[ "$savedNum" == *"MacMSP"* ]]; then
    #using the old generated hash
    serialNum="$savedNum"
  else
    #must be first run or cloned so reset
    if [ ${blankBoard:-0} -eq 1 ]; then
      #generate a random hash, but check Gruntwork first
      hwNum=`/usr/libexec/PlistBuddy -c "Print :serial" /Library/Mac-MSP/Gruntwork/settings.plist  2> /dev/null`
  	  if [ "$hwNum" == "" ] || [[ "$hwNum" != *"MacMSP"* ]]; then
      	hwNum="MacMSP`uuidgen|tr -d '-'`"
      fi
    fi
    #this may be a first run or first after a clone
    /usr/libexec/PlistBuddy -c "Add :serial string $hwNum" "$ourHome/settings.plist" 2> /dev/null
    /usr/libexec/PlistBuddy -c "Set :serial $hwNum" "$ourHome/settings.plist"
    serialNum="$hwNum"
    reKey
    #do any other first run steps here
  fi
fi
}

# make me a sandwich? make it yourself
userName=`whoami`
if [ "$userName" != "bluesky" ]; then
	logMe "ERROR - script called by wrong user"
	exit 2
fi

# are our perms screwed up?
scriptPerm=`ls -l "$ourHome/bluesky.sh" | awk '{ print $3 }'`
if [ "$scriptPerm" != "bluesky" ]; then
	echo "fixPerms"  > "$ourHome/.getHelp"
	sleep 5
fi

# get server address
blueskyServer=`/usr/libexec/PlistBuddy -c "Print :address" "$ourHome/server.plist"  2> /dev/null`
# sanity check
if [ "$blueskyServer" == "" ]; then
  logMe "ERROR: fix the server address"
  exit 1
fi

# get the version of the OS so we can ensure compatiblity
osRaw=`sw_vers -productVersion`
osVersionMajor=`echo "$osRaw" | awk -F . '{ print $1 }'`
osVersionMinor=`echo "$osRaw" | awk -F . '{ print $2 }'`

# select all of our algorithms - treating OS X 10.10 and below as insecure, defaulting to secure
if [ ${osVersionMajor:-0} -eq 10 ] && [ ${osVersionMinor:-0} -lt 11 ] && [ ${osVersionMinor:-0} -ne 0 ]; then
  keyAlg="ssh-rsa"
  serverKey="serverkeyrsa"
  prefCipher="aes256-ctr"
  kexAlg=""
  msgAuth="hmac-ripemd160"
else
  keyAlg="ssh-ed25519"
  serverKey="serverkey"
  prefCipher="chacha20-poly1305@openssh.com"
  kexAlg="-o KexAlgorithms=curve25519-sha256@libssh.org"
  msgAuth="hmac-sha2-512-etm@openssh.com"
fi

# server key will be pre-populated in the installer - put it into known hosts
serverKey=`/usr/libexec/PlistBuddy -c "Print :$serverKey" "$ourHome/server.plist"  2> /dev/null`
if [ "$serverKey" == "" ]; then
  logMe "ERROR: cant get server key - please reinstall"
  exit 1
else
  echo "$serverKey" > "$ourHome/.ssh/known_hosts"
fi

# are there any live network ports?
activeNets=`ifconfig | grep 'status\: active'`
#no network connections means we are most certainly down wait up to 2 min for live network
while [ "$activeNets" == "" ]; do
  sleep 5
  (( netCounter++ ))
  activeNets=`ifconfig | grep 'status\: active'`
  if [ ${netCounter:-0} -gt 25 ]; then
    killShells
    logMe "No active network connections. Exiting"
    exit 0
  fi
done

# get proxy info from system preferences
proxyInfo=`"$ourHome/proxy-config" -s`
if [ "$proxyInfo" != "" ]; then
  curlProxy="-x $proxyInfo"
else
  curlProxy=""
fi

# get serial number
serialMonster

# Attempt to get our port
port=`curl $curlProxy -s -S -m 60 -1 --retry 4 --cacert "$ourHome/cacert.pem" -X POST --data-urlencode "serialNum=$serialNum" -d actionStep=port https://$blueskyServer/cgi-bin/collector.php`
curlExit=$?

# Is the server up?
if [ $curlExit -ne 0 ]; then
	#can't get to the server, we might be down, try again on next cycle
	killShells
	logMe "ERROR - cant get to server. Exiting"
	exit 0
fi

# Is collector returning a database connection error?
if [ "$port" == "ERROR: cant get dbc" ]; then
	logMe "ERROR - server has a database problem. Exiting."
	exit 2
fi

# Did port check pass?
if [ "$port" == "" ]; then
	# try running off cached copy
	port=`/usr/libexec/PlistBuddy -c "Print :portcache" "$ourHome/settings.plist"  2> /dev/null`
	if [ "$port" == "" ]; then
		#no cached copy either, try rekey
		reKey
		sleep 5
    port=`curl $curlProxy -s -S -m 60 -1 --retry 4 --cacert "$ourHome/cacert.pem" -X POST --data-urlencode "serialNum=$serialNum" -d actionStep=port https://$blueskyServer/cgi-bin/collector.php`
    curlExit=$?
		if [ "$port" == "" ] || [ $curlExit -ne 0 ]; then
  		logMe "ERROR - cant reach server and have no port. Exiting."
  		exit 2
  	else
  	  # plant port cache for next time
  	    /usr/libexec/PlistBuddy -c "Add :portcache integer $port" "$ourHome/settings.plist" 2> /dev/null
    	/usr/libexec/PlistBuddy -c "Set :portcache $port" "$ourHome/settings.plist"
  	fi
	fi
else
	# plant port cache for next time
	/usr/libexec/PlistBuddy -c "Add :portcache integer $port" "$ourHome/settings.plist" 2> /dev/null
	/usr/libexec/PlistBuddy -c "Set :portcache $port" "$ourHome/settings.plist"
fi

sshport=$((22000 + port))
vncport=$((24000 + port))
monport=$((26000 + port))

#greysky:
manualProxy=`/usr/libexec/PlistBuddy -c "Print :proxy" "$ourHome/settings.plist"  2> /dev/null`
if [ "$manualProxy" != "" ]; then
	#if there is a manual proxy string in settings.plist, go with it
	confProxy="$manualProxy"
else
	#parse curl proxy output into format for corkscrew
	if [ "$proxyInfo" != "" ]; then
	  confProxy=`echo "$proxyInfo" | awk -F ':' '{ print $2,$3 }' | tr -d '/'`
	else
	  confProxy=""
	fi
fi

if [ "$confProxy" != "" ] && [ ! -e "$ourHome/.ssh/config" ]; then
  #if proxy exists, and config is disabled, enable it, restart autossh
  sed "s/proxyaddress proxyport/$confProxy/g" "$ourHome/.ssh/config.disabled" > "$ourHome/.ssh/config"
  # TODO - populate SERVER and OURHOME too
  restartConnection
elif [ "$confProxy" == "" ] && [ -e "$ourHome/.ssh/config" ]; then
  #if proxy gone, and config enabled, disable it, restart autossh
  rm -f "$ourHome/.ssh/config"
  restartConnection
fi

# if the keys aren't made at this point, we should make them
if [ ! -e "$ourHome/.ssh/bluesky_client" ]; then
	reKey
fi

# ensure autossh is alive and restart if not
getAutoPid
if [ "$autoPid" == "" ]; then
  restartConnection
fi

# ask server for the default username so we can pass on to Watchman
defaultUser=`curl $curlProxy -s -S -m 60 -1 --retry 4 --cacert "$ourHome/cacert.pem" -X POST --data-urlencode "serialNum=$serialNum" -d actionStep=user https://$blueskyServer/cgi-bin/collector.php`
if [ "$defaultUser" != "" ]; then
	/usr/libexec/PlistBuddy -c "Add :defaultuser string $defaultUser" "$ourHome/settings.plist" 2> /dev/null
	/usr/libexec/PlistBuddy -c "Set :defaultuser $defaultUser" "$ourHome/settings.plist"
fi

#autossh is running - check against server
connStat=`curl $curlProxy -s -S -m 60 -1 --retry 4 --cacert "$ourHome/cacert.pem" -X POST --data-urlencode "serialNum=$serialNum" -d actionStep=status https://$blueskyServer/cgi-bin/collector.php`
if [ "$connStat" != "OK" ]; then
  if [ "$connStat" == "selfdestruct" ]; then
    killShells
	echo "selfdestruct" > "$ourHome/.getHelp"
    exit 0
  fi
  logMe "server says we are down. restarting tunnels. Server said $connStat"
  restartConnection
  sleep 5
  connStatRetry=`curl $curlProxy -s -S -m 60 -1 --retry 4 --cacert "$ourHome/cacert.pem" -X POST --data-urlencode "serialNum=$serialNum" -d actionStep=status https://$blueskyServer/cgi-bin/collector.php`
  if [ "$connStatRetry" != "OK" ]; then
    logMe "server still says we are down. trying reKey. Server said $connStat"
    reKey
    sleep 5
    restartConnection
    sleep 5
    connStatLastTry=`curl $curlProxy -s -S -m 60 -1 --retry 4 --cacert "$ourHome/cacert.pem" -X POST --data-urlencode "serialNum=$serialNum" -d actionStep=status https://$blueskyServer/cgi-bin/collector.php`
    if [ "$connStatLastTry" != "OK" ]; then
      logMe "ERROR - server still says we are down. needs manual intervention. Server said $connStat"
      exit 1
    else
    	logMe "rekey worked. all good!"
    fi
  else
  	logMe "reconnect worked. all good!"
  fi
else
  logMe "server sees our connection.  all good!"
fi

exit 0
