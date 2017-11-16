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

# find ssh keys from temp app and purge them if too old

tempList=`grep "tmp-" /home/bluesky/.ssh/authorized_keys | awk -F '-' '{ print $NF }'`
if [ "$tempList" != "" ]; then
myEpoch=`date +%s`
expirEpoch=`expr $myEpoch - 14400`
for thisLine in $tempList; do
  if [ $thisLine -lt $expirEpoch ]; then
    sed -i "/$thisLine/d" /home/bluesky/.ssh/authorized_keys
  fi
done
fi
exit 0