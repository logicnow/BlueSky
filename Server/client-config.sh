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

# sets up client and admin keys as well as client's server.plist and auth_key

# reads option to only do one set of keys or the other
reKey="$1"
mkdir -p /usr/local/bin/BlueSky/Client/.ssh 2> /dev/null
hostName=`grep ServerName /etc/apache2/sites-enabled/default-ssl.conf | awk '{ print $NF }'`
if [ "$hostName" == "" ]; then
	echo "Server FQDN is not readable from apache. Please double check your server setup."
	exit 2
fi

# safety check if these files are there
if [ -e /usr/local/bin/BlueSky/Server/blueskyd ] && [ "$reKey" == "" ]; then
	echo "This server has already been configured.  Please use --client or --admin to re-key the client apps."
	echo "If you are trying to set up the server again, please delete /usr/local/bin/BlueSky/Server/blueskyd* and try again."
	exit 1
fi

if [ "$reKey" != "--admin" ]; then
	# make blueskyclient pair - used for encrypting uploaded SSH keys to the server for clients
	openssl req -x509 -nodes -days 100000 -newkey rsa:2048 -keyout /usr/local/bin/BlueSky/Server/blueskyclient.key -out /usr/local/bin/BlueSky/Client/blueskyclient.pub -subj '/'
fi

if [ "$reKey" != "--client" ]; then
	# make blueskyadmin pair - used for encrypting uploaded SSH keys to the server for admins
	openssl req -x509 -nodes -days 100000 -newkey rsa:2048 -keyout /usr/local/bin/BlueSky/Server/blueskyadmin.key -out /usr/local/bin/BlueSky/Admin\ Tools/blueskyadmin.pub -subj '/'
fi

# only do these if reKey is not set and the blueskyd file is not present
if [ "$reKey" == "" ]; then
	# make bluesky-server-check keys - used for allowing the server to SSH in and validate the tunnel
	# still using RSA here so we can shell into older Macs
	ssh-keygen -q -t rsa -N '' -f /usr/local/bin/BlueSky/Server/blueskyd -C "$hostName"
	chown www-data /usr/local/bin/BlueSky/Server/blueskyd
	echo command=\"/var/bluesky/.ssh/wrapper.sh\",no-port-forwarding,no-X11-forwarding,no-agent-forwarding,no-pty `cat /usr/local/bin/BlueSky/Server/blueskyd.pub` > /usr/local/bin/BlueSky/Client/.ssh/authorized_keys

	# create server.plist
	hostKey=`ssh-keyscan -t ecdsa-sha2-nistp256 localhost | awk '{ print $2,$3 }'`
	ipAddress=`curl ipinfo.io | grep '"ip":' | awk '{ print $NF }' | tr -d \",`
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\">
<dict>
	<key>address</key>
	<string>$hostName</string>
	<key>serverkey</key>
	<string>[$hostName]:3122,[$ipAddress]:3122 $hostKey</string>
</dict>
</plist>" > /usr/local/bin/BlueSky/Client/server.plist
fi
