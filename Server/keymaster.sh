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

# receives what should be a public key for addition to bluesky
# checks it and then hands it off to gatekeeper by way of inoticoming

dataUp="$1"
tmpName=`uuidgen`

# decrypt. if admin fails, try client. if both fail, reject it.  Whichever one passes, note the type.
echo "$dataUp" | openssl smime -decrypt -inform PEM -inkey /usr/local/bin/BlueSky/Server/blueskyclient.key -out /tmp/$tmpName.pub
if [ $? -ne 0 ]; then
	echo "$dataUp" | openssl smime -decrypt -inform PEM -inkey /usr/local/bin/BlueSky/Server/blueskyadmin.key -out /tmp/$tmpName.pub
	if [ $? -ne 0 ]; then
		echo "Invalid"
		exit 0
	else
		targetLoc="admin"
	fi
else
	targetLoc="bluesky"
fi

pubKey=`cat /tmp/$tmpName.pub`

keyValid=`ssh-keygen -l -f /tmp/$tmpName.pub`
# keyValid contains the hash that will appear in auth.log
# 256 SHA256:Sahm5Rft8nvUQ5425YgrrSNGosZA4hf/P2NmhRr2NL0 uploaded@1510761187 sysadmin@Sidekick.local (ECDSA)
fingerPrint=`echo "$keyValid" | awk '{ print $2 }' | cut -d : -f 2`
if [[ "$keyValid" == *"ECDSA"* ]]; then
  mv /tmp/$tmpName.pub /home/$targetLoc/newkeys/$tmpName.pub
  echo "Installed"
  if [ "$targetLoc" == "admin" ] && [ -e /usr/local/bin/BlueSky/Server/emailHelper.sh ]; then
    #email the subscriber about it
    keyID=`echo "$pubKey" | awk '{ print $NF }'`
    /usr/local/bin/BlueSky/Server/emailHelper.sh "BlueSky Admin Key Registered" "A new admin key with identifier $keyID was registered in your server. If you did not expect this, please invoke Emergency Stop."
  fi
else
#  rm -f /tmp/$tmpName.pub
  echo "Invalid"
fi

exit 0