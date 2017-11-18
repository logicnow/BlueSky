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

## starts things off - this is called by cron at reboot to start inoticoming
# could be a place for other startup tasks too
# https://youtu.be/XfdiXBA7f6U - it's whatever it wants to be

inoticoming /home/bluesky/newkeys --suffix .pub /usr/local/bin/BlueSky/Server/gatekeeper.sh {} \;
inoticoming /home/admin/newkeys --suffix .pub /usr/local/bin/BlueSky/Server/gatekeeper.sh {} \;
