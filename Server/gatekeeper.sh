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

# called by inoticoming when a good public key is copied to /home/*/newkeys by the keymaster

tmpFile="$1"

fileLoc=`ls /home/admin/newkeys/$1 2>/dev/null`

if [ "$fileLoc" != "" ]; then
	targetLoc="admin"
	prefixCode="command=\"/usr/local/bin/BlueSky/Server/$targetLoc-wrapper.sh\""
else
	targetLoc="bluesky"
	prefixCode="command=\"/usr/local/bin/BlueSky/Server/$targetLoc-wrapper.sh\",no-X11-forwarding,no-agent-forwarding,no-pty"
fi

pubKey=`cat "/home/$targetLoc/newkeys/$tmpFile"`
serialNum=`echo "$pubKey" | awk '{ print $NF }'`
# 256 SHA256:Sahm5Rft8nvUQ5425YgrrSNGosZA4hf/P2NmhRr2NL0 uploaded@1510761187 sysadmin@Sidekick.local (ECDSA)
fingerPrint=`ssh-keygen -l -f /home/$targetLoc/newkeys/$tmpFile | awk '{ print $2 }' | cut -d : -f 2`

#remove previous keys with same serial
if [ "$serialNum" != "" ]; then
	sed -i "/$serialNum/d" /home/$targetLoc/.ssh/authorized_keys
fi
# install it
echo "$prefixCode $pubKey" >> /home/$targetLoc/.ssh/authorized_keys

rm -f "/home/$targetLoc/newkeys/$tmpFile"

# add to admin keys table
if [ "$targetLoc" == "admin" ]; then
	adminKeys=`cat /home/admin/.ssh/authorized_keys | awk '{ print $NF }'`
	myCmd="/usr/bin/mysql --defaults-file=/var/local/my.cnf BlueSky -N -B -e"
	myQry="update global set adminkeys='$adminKeys'"
	$myCmd "$myQry"
fi


exit 0