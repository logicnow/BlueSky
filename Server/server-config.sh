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

# sets up a base install of Ubuntu Server with configuration needed for BlueSky
# DO NOT RUN this on migrated BlueSky 1.x servers

## get variables
# you can fill these in here or the script will ask for them
serverFQDN=""
webAdminPassword=""
mysqlRootPass=""
emailAlertAddress=""

## ask for blank variables

if [ "$serverFQDN" == "" ]; then
	echo "Please enter a fully qualified domain name for this server."
	read serverFQDN
	if [ "$serverFQDN" == "" ]; then
		echo "This value cannot be empty. Please try again."
		exit 2
	fi
fi
if [ "$webAdminPassword" == "" ]; then
	echo "Please enter a password for logging into the web admin."
	read webAdminPassword
	if [ "$webAdminPassword" == "" ]; then
		echo "This value cannot be empty. Please try again."
		exit 2
	fi
fi
	echo "Please enter the password again."
	read webPassConf
	if [ "$webAdminPassword" != "$webPassConf" ]; then
		echo "Sorry the passwords don't match. Please try again."
		exit 2
	fi
if [ "$emailAlertAddress" == "" ]; then
	echo "Please enter an email address where you will receive alerts."
	read emailAlertAddress
	if [ "$emailAlertAddress" == "" ]; then
		echo "This value cannot be empty. Please try again."
		exit 2
	fi
fi
if [ "$mysqlRootPass" == "" ]; then
	echo "Please enter a root password for MySQL. Leave it blank and we'll generate one."
	read mysqlRootPass
	if [ "$mysqlRootPass" == "" ]; then
		mysqlRootPass=`tr -dc A-Za-z0-9 < /dev/urandom | head -c 48 | xargs`
	fi
fi

## variables no one will care about
mysqlCollectorPass=`tr -dc A-Za-z0-9 < /dev/urandom | head -c 48 | xargs`

## double-check permissions on uploaded BlueSky files
chown -R root:root /usr/local/bin/BlueSky/Server
chmod 755 /usr/local/bin/BlueSky/Server
chown www-data /usr/local/bin/BlueSky/Server/keymaster.sh
chown www-data /usr/local/bin/BlueSky/Server/processor.sh
chmod 755 /usr/local/bin/BlueSky/Server/*.sh

## write server FQDN to a file for easy reference in case hostname changes
echo "$serverFQDN" > /usr/local/bin/BlueSky/Server/server.txt
echo "$serverFQDN" > /usr/local/bin/BlueSky/Admin\ Tools/server.txt

## reconfigure sshd_config to meet our specifications
sed -i 's/Port 22/Port 22\nPort 3122/g' /etc/ssh/sshd_config
echo 'Ciphers chacha20-poly1305@openssh.com,aes128-ctr,aes192-ctr,aes256-ctr,arcfour256,arcfour128,arcfour' >> /etc/ssh/sshd_config
echo 'MACs hmac-sha2-512,hmac-sha1,hmac-ripemd160' >> /etc/ssh/sshd_config
sed -i '/HostKey \/etc\/ssh\/ssh_host_dsa_key/d' /etc/ssh/sshd_config
sed -i '/HostKey \/etc\/ssh\/ssh_host_rsa_key/d' /etc/ssh/sshd_config
sed -i '/HostKey \/etc\/ssh\/ssh_host_ed25519_key/d' /etc/ssh/sshd_config
service sshd restart

## setup local firewall
ufw allow 3122
ufw enable
ufw allow 80 
ufw allow 443

## install software
apt-get update
sudo debconf-set-selections <<< "mysql-server mysql-server/root_password password $mysqlRootPass"
sudo debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $mysqlRootPass"
apt-get -y install apache2 fail2ban mysql-server php-mysql php libapache2-mod-php php-mcrypt php-mysql inoticoming swaks

## setup user accounts/folders
groupadd admin 2> /dev/null # will already be there on DO
useradd -m -g admin admin
useradd -m bluesky
passwd -d admin
passwd -d bluesky
usermod -s /bin/bash admin
usermod -s /bin/bash bluesky
mkdir -p /home/admin/.ssh
mkdir -p /home/bluesky/.ssh
mkdir -p /home/admin/newkeys
mkdir -p /home/bluesky/newkeys
chown www-data /home/admin/newkeys
chown www-data /home/bluesky/newkeys
chown -R admin /home/admin/.ssh
chown -R bluesky /home/bluesky/.ssh

## configure apache2
a2enmod ssl
a2enmod cgi
a2ensite default-ssl
sed -i "s/ServerAdmin webmaster@localhost/ServerAdmin $emailAlertAddress/g" /etc/apache2/sites-enabled/default-ssl.conf

#read the top half
head -n 5 /etc/apache2/sites-enabled/default-ssl.conf > /tmp/default-ssl.conf 
#put this in
echo "    ServerName $serverFQDN" >> /tmp/default-ssl.conf
echo '        SSLProtocol All -SSLv2 -SSLv3' >> /tmp/default-ssl.conf
#write the bottom half
tail -n +6 /etc/apache2/sites-enabled/default-ssl.conf  >> /tmp/default-ssl.conf
#make backup and move it in place
mv /etc/apache2/sites-enabled/default-ssl.conf /tmp/default-ssl.conf.backup
mv /tmp/default-ssl.conf /etc/apache2/sites-enabled/default-ssl.conf

## setup port 80 redirect to 443
echo "<VirtualHost *:80>
Redirect permanent / https://$serverFQDN/" > /tmp/000-default.conf
tail -n +2 /etc/apache2/sites-enabled/000-default.conf  >> /tmp/000-default.conf
mv /etc/apache2/sites-enabled/000-default.conf /tmp/000-default.conf.backup
mv /tmp/000-default.conf /etc/apache2/sites-enabled/000-default.conf

service apache2 restart

## move web site to /var/www/html
mv /var/www/html /var/www/html.old
ln -s /usr/local/bin/BlueSky/Server/html /var/www/html
chown -R www-data /usr/local/bin/BlueSky/Server/html

## configure cron jobs
echo "@reboot /usr/local/bin/BlueSky/Server/startGozer.sh" > /tmp/mycron
echo "*/30 * * * *  /usr/local/bin/BlueSky/Server/purgeTemp.sh" >> /tmp/mycron
echo "*/5 * * * * /usr/local/bin/BlueSky/Server/serverup.sh" >> /tmp/mycron
crontab /tmp/mycron
/usr/local/bin/BlueSky/Server/startGozer.sh

## setup collector.php
ln -s /usr/local/bin/BlueSky/Server/collector.php /usr/lib/cgi-bin/collector.php
chown www-data /usr/local/bin/BlueSky/Server/collector.php 
chmod 700 /usr/local/bin/BlueSky/Server/collector.php 
sed -i "s/CHANGETHIS/$mysqlCollectorPass/g" /usr/lib/cgi-bin/collector.php

## setup my.cnf
echo "[client]
user = root
password = $mysqlRootPass
host = 127.0.0.1" > /var/local/my.cnf
chown root:www-data /var/local/my.cnf
chmod 640 /var/local/my.cnf

# setup database
/usr/bin/mysql --defaults-file=/var/local/my.cnf -N -B -e 'create database BlueSky;'
/usr/bin/mysql --defaults-file=/var/local/my.cnf BlueSky < /usr/local/bin/BlueSky/Server/myBlueSQL.sql 
myCmd="/usr/bin/mysql --defaults-file=/var/local/my.cnf BlueSky -N -B -e"

## setup credentials in /var/www/html/config.php
sed -i "s/MYSQLROOT/$mysqlRootPass/g" /var/www/html/config.php

## setup credentials in membership_users table
myQry="update membership_users set passMD5=MD5('$webAdminPassword'),email='$emailAlertAddress' where memberID='admin'"
$myCmd "$myQry"

## set collector mysql perms
myQry="create user 'collector'@'localhost' identified by '$mysqlCollectorPass';"
$myCmd "$myQry"
myQry="grant select on BlueSky.computers to 'collector'@'localhost';"
$myCmd "$myQry"

## fail2ban conf
sed -i "s/SERVERFQDN/$serverFQDN/g" /usr/local/bin/BlueSky/Server/sendEmail-whois-lines.conf
cp /usr/local/bin/BlueSky/Server/sendEmail-whois-lines.conf /etc/fail2ban/action.d/sendEmail-whois-lines.conf
sed -i "s/EMAILADDRESS/$emailAlertAddress/g" /usr/local/bin/BlueSky/Server/jail.local
cp /usr/local/bin/BlueSky/Server/jail.local /etc/fail2ban
service fail2ban start

## add emailAlertAddress to mysql for alerting
myQry="update global set defaultemail='$emailAlertAddress'"
$myCmd "$myQry"

## update emailHelper-dist.  You still need to enable it.
sed -i "s/EMAILADDRESS/$emailAlertAddress/g" /usr/local/bin/BlueSky/Server/emailHelper-dist.sh

## Run setup for client files
/usr/local/bin/BlueSky/Server/client-config.sh

## That's all folks!
echo "All set.  Please be sure to generate a CSR and/or install a verifiable SSL certificate"
echo "in Apache by editing SSL paths in /etc/apache2/sites-enabled/default-ssl.conf"
echo "BlueSky will not connect to servers with self-signed or invalid certificates."
echo "And configure /usr/local/bin/BlueSky/Server/emailHelper.sh with your preferred SMTP setup."
exit 0