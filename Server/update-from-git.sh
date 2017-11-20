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

# this will do a git pull and ensure files keep your configurations

## TODO - ensure this is run by root

## get variables
serverFQDN=`cat /usr/local/bin/BlueSky/Server/server.txt`
mysqlRootPass=`grep password /var/local/my.cnf | awk '{ print $NF }'`
## TODO test this
mysqlCollectorPass=`grep localhost /usr/lib/cgi-bin/collector.php | head -n 1 | awk '{ print $5 }' | tr -d ,\'`

## error for blank variables
if [ "$serverFQDN" == "" ]; then
	echo "This value cannot be empty. Please fix server.txt and try again."
	exit 2
fi
if [ "$mysqlRootPass" == "" ]; then
  echo "Something really borked the my.cnf file. May need to reset the mysql root password everywhere."
  exit 2
fi

# do the pull
cd /usr/local/bin/BlueSky
git fetch
git reset --hard origin/master

myCmd="/usr/bin/mysql --defaults-file=/var/local/my.cnf BlueSky -N -B -e"

## if git pull was ran ahead of this script, we lost collector password. need to reset
if [ "$mysqlCollectorPass" == "" ]; then
	echo "Collector creds got trashed. Will reset."
  mysqlCollectorPass=`tr -dc A-Za-z0-9 < /dev/urandom | head -c 48 | xargs`
  # TODO - test this deletion
  myQry="delete user 'collector'@'localhost';"
  $myCmd "$myQry"
  myQry="create user 'collector'@'localhost' identified by '$mysqlCollectorPass';"
  $myCmd "$myQry"
  myQry="grant select on BlueSky.computers to 'collector'@'localhost';"
  $myCmd "$myQry"
fi
sed -i "s/CHANGETHIS/$mysqlCollectorPass/g" /usr/lib/cgi-bin/collector.php

## double-check permissions on uploaded BlueSky files
chown -R root:root /usr/local/bin/BlueSky/Server
chmod 755 /usr/local/bin/BlueSky/Server
chown www-data /usr/local/bin/BlueSky/Server/keymaster.sh
chown www-data /usr/local/bin/BlueSky/Server/processor.sh
chmod 755 /usr/local/bin/BlueSky/Server/*.sh
chown -R www-data /usr/local/bin/BlueSky/Server/html
chown www-data /usr/local/bin/BlueSky/Server/collector.php 
chmod 700 /usr/local/bin/BlueSky/Server/collector.php 
chown www-data /usr/local/bin/BlueSky/Server/blueskyd

## change the keys for 2.1
# this can be removed in future versions, it's only for trailblazers who took arrows
remakePlist=0
# fix the ciphers and MACs
cipherCheck=`grep arcfour /etc/ssh/sshd_config`
if [ "$cipherCheck" != "" ]; then
	sed -i '/Ciphers chacha20-poly1305@openssh.com,aes128-ctr,aes192-ctr,aes256-ctr,arcfour256,arcfour128,arcfour/d' /etc/ssh/sshd_config
	echo 'Ciphers chacha20-poly1305@openssh.com,aes256-ctr' >> /etc/ssh/sshd_config
fi
maCheck=`grep hmac-sha1 /etc/ssh/sshd_config`
if [ "$maCheck" != "" ]; then
	sed -i '/MACs hmac-sha2-512,hmac-sha1,hmac-ripemd160,hmac-sha2-512-etm@openssh.com/d' /etc/ssh/sshd_config
	echo 'MACs hmac-sha2-512-etm@openssh.com,hmac-ripemd160' >> /etc/ssh/sshd_config
fi
# put the ed25519 key back
edKeyPresent=`grep ssh_host_ed25519_key /etc/ssh/sshd_config`
if [ "$edKeyPresent" == "" ]; then
	# trade: ecdsa goes away in favor of ed25519
	sed -i 's/HostKey \/etc\/ssh\/ssh_host_ecdsa_key/HostKey \/etc\/ssh\/ssh_host_ed25519_key/g' /etc/ssh/sshd_config
	service ssh restart
	remakePlist=1
fi
# put the rsa key back
rsaKeyPresent=`grep ssh_host_rsa_key /etc/ssh/sshd_config`
if [ "$rsaKeyPresent" == "" ]; then
    hostLine=`grep -n 'HostKeys for protocol version 2' /etc/ssh/sshd_config | awk -F : '{ print $1 }'`
    if [ "$hostLine" != "" ]; then
        # put it back into sshd_config
        head -n $hostLine /etc/ssh/sshd_config > /tmp/sshd_config
        echo 'HostKey /etc/ssh/ssh_host_rsa_key' >> /tmp/sshd_config
        (( hostLine ++ ))
        tail -n +$hostLine /etc/ssh/sshd_config >> /tmp/sshd_config
        mv /tmp/sshd_config /etc/ssh/sshd_config
        service ssh restart
        remakePlist=1
    else
        echo "Something is really wrong with the sshd_config file"
        exit 2
    fi
fi
if [ $remakePlist -eq 1 ]; then
    # remake Client/server.plist
    hostKey=`ssh-keyscan -t ed25519 localhost | awk '{ print $2,$3 }'`
    hostKeyRSA=`ssh-keyscan -t rsa localhost | awk '{ print $2,$3 }'`
    ipAddress=`curl ipinfo.io | grep '"ip":' | awk '{ print $NF }' | tr -d \",`
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">
<plist version=\"1.0\">
<dict>
    <key>address</key>
    <string>$serverFQDN</string>
    <key>serverkey</key>
    <string>[$serverFQDN]:3122,[$ipAddress]:3122 $hostKey</string>
    <key>serverkeyrsa</key>
    <string>[$serverFQDN]:3122,[$ipAddress]:3122 $hostKeyRSA</string>
</dict>
</plist>" > /usr/local/bin/BlueSky/Client/server.plist
fi

## get emailAlertAddress from mysql
myQry="select defaultemail from global"
emailAlertAddress=`$myCmd "$myQry"`

## setup credentials in /var/www/html/config.php
sed -i "s/MYSQLROOT/$mysqlRootPass/g" /var/www/html/config.php

## fail2ban conf - not making these active but updating our copies
sed -i "s/SERVERFQDN/$serverFQDN/g" /usr/local/bin/BlueSky/Server/sendEmail-whois-lines.conf
sed -i "s/EMAILADDRESS/$emailAlertAddress/g" /usr/local/bin/BlueSky/Server/jail.local

## update emailHelper-dist.  You still need to enable it.
sed -i "s/EMAILADDRESS/$emailAlertAddress/g" /usr/local/bin/BlueSky/Server/emailHelper-dist.sh

## put server fqdn into client config.disabled for proxy routing
sed -i "s/SERVER/$serverFQDN/g" /usr/local/bin/BlueSky/Client/.ssh/config.disabled

## That's all folks!
echo "All set.  You're up to date!"
exit 0