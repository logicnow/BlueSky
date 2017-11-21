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
  myQry="drop user 'collector'@'localhost';"
  $myCmd "$myQry"
  myQry="create user 'collector'@'localhost' identified by '$mysqlCollectorPass';"
  $myCmd "$myQry"
  myQry="grant select on BlueSky.computers to 'collector'@'localhost';"
  $myCmd "$myQry"
fi

## double-check permissions on uploaded BlueSky files
chown -R root:root /usr/local/bin/BlueSky/Server
chmod 755 /usr/local/bin/BlueSky/Server
chown www-data /usr/local/bin/BlueSky/Server/keymaster.sh
chown www-data /usr/local/bin/BlueSky/Server/processor.sh
chmod 755 /usr/local/bin/BlueSky/Server/*.sh

chown -R www-data /usr/local/bin/BlueSky/Server/html
chown www-data /usr/local/bin/BlueSky/Server/collector.php 
chmod 700 /usr/local/bin/BlueSky/Server/collector.php 
sed -i "s/CHANGETHIS/$mysqlCollectorPass/g" /usr/lib/cgi-bin/collector.php

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