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

# if you want any kind of email alerting, please configure this script
# it will receive a subject as "$1" and a body as "$2"
# it has been configured to pull the "To:" address from the global settings in the web admin

## RENAME to emailHelper.sh to activate after configuring the variables below

fromAddress="EMAILADDRESS"
subjectLine="$1"
messageBody="$2"
smtpServer=""
smtpAuth=""
smtpPass=""

## bail on this is if the server variable isn't set
if [ "$smtpServer" == "" ]; then
  echo "No server set up. Please edit emailHelper and try again.”
  exit 2
fi

## get the To address from mySql
myCmd="/usr/bin/mysql --defaults-file=/var/local/my.cnf BlueSky -N -B -e"
myQry="select defaultemail from global"
toAddress=`$myCmd "$myQry"`


## substitute in your preferred email method

/usr/bin/swaks -tls -a -au "$smtpAuth" -ap "$smtpPass" --server "$smtpServer" -f "$fromAddress" -t "$toAddress" --h-Subject "$subjectLine" --body "$messageBody"