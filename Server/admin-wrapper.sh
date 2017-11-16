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

# This wrapper script is called by requiring it in /home/admin/.ssh/authorized_keys
# It prevents admin users from shelling directly into the server with their BlueSky creds

myCmd="/usr/bin/mysql --defaults-file=/var/local/my.cnf --default-character-set=utf8 BlueSky -N -B -e"

# grab things necessary for all phases
keyUsed="TBD" 
startTime=`date '+%Y-%m-%d %H:%M:%S %Z'`
sourceIP=`tail /var/log/auth.log | grep 'for admin' | tail -n 1 | awk -F 'for admin from ' '{ print $2 }' | awk '{ print $1 }'`

#TODO - use fingerprint from auth.log against authorized_keys to get description for $keyUsed
#Nov 15 20:45:53 redsky sshd[2728]: Accepted publickey for admin from 38.98.37.19 port 62275 ssh2: ECDSA SHA256:Sahm5Rft8nvUQ5425YgrrSNGosZA4hf/P2NmhRr2NL0
# 256 SHA256:Sahm5Rft8nvUQ5425YgrrSNGosZA4hf/P2NmhRr2NL0 uploaded@1510761187 sysadmin@Sidekick.local (ECDSA)
#fingerPrint=`ssh-keygen -l -f /home/$targetLoc/newkeys/$tmpFile | awk '{ print $2 }' | cut -d : -f 2`


function writeAudit {
	# creates a record in mysql for tracking admin activity
	# $1 should be error description, if any
	myQry="insert into connections (startTime,sourceIP,adminkey,targetPort,notes) VALUES ('$startTime','$sourceIP','$keyUsed','$targetPort','$1');"
	$myCmd "$myQry"
    myQry="select id from connections where startTime='$startTime' and adminkey='$keyUsed'"
	auditID=`$myCmd "$myQry"`
}

function closeAudit {
	# closes the previous mysql record with an exit code and finish time
	# $1 should be exit code
	endTime=`date '+%Y-%m-%d %H:%M:%S %Z'`
	myQry="update connections set endTime='$endTime',exitStatus='$1' where id='$auditID'"
	$myCmd "$myQry"
}

# no command equals no access, punk
if [ "${SSH_ORIGINAL_COMMAND:=UNSET}" == "UNSET" ]; then
	echo "shell access is not permitted for BlueSky"
	writeAudit "Tried For Shell Access"
	closeAudit 127
	exit 127
fi

command="$SSH_ORIGINAL_COMMAND"; export command
commandRecord=`echo "$SSH_ORIGINAL_COMMAND" | head -n 1`
targetPortRaw=`echo "$commandRecord" | awk '{ print $NF }'`
targetPort=`expr $targetPortRaw - 22000`
testCmd[1]="/bin/nc localhost 2...."
testCmd[2]="/usr/bin/ssh localhost -p 2.*"

for thisCmd in "${testCmd[@]}"; do
  validCmd=`echo "$command" | grep ^"$thisCmd"$`
  if [ "$validCmd" == "" ]; then
    matchCmd="false"
  else
    semiCheck=`echo $command | grep ";"`
    ampCheck=`echo "$command" | grep '&'`
    pipeCheck=`echo "$command" | grep '|'`
    if [ "$semiCheck" == "" ] && [ "$ampCheck" == "" ] && [ "$pipeCheck" == "" ]; then
            matchCmd="true"
            break
    else
            matchCmd="false"
    fi
  fi
done

if [ "$matchCmd" == "true" ]; then
	writeAudit "Valid Connection"
    eval $command
    closeAudit $?
else
	echo "invalid command"
	writeAudit "Invalid Command"
	closeAudit 127
	exit 127
fi