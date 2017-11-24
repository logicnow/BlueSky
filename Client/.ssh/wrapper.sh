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

# This script ensures that all incoming SSH connections originated by the server
# are only allowed to read the expected serial number either from system_profiler
# or the generated hash in settings.plist

if [ "${SSH_ORIGINAL_COMMAND:=UNSET}" == "UNSET" ]; then
	echo "shell access is not permitted for BlueSky"
	exit 127
fi

command="$SSH_ORIGINAL_COMMAND"; export command
testCmd[1]="/usr/bin/defaults read /var/bluesky/settings serial"
testCmd[2]="/usr/libexec/PlistBuddy -c 'Print serial' /var/bluesky/settings.plist"

for thisCmd in "${testCmd[@]}"; do
  if [ "$command" == "$thisCmd" ]; then
    matchCmd="true"
    break
  else
    matchCmd="false"
  fi
done

if [ "$matchCmd" == "true" ]; then
  eval $command
else
	echo "invalid command"
	exit 127
fi