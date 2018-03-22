
(*Copyright 2016-2017 SolarWinds Worldwide, LLC

Licensed under the Apache License, Version 2.0 (the "License");
  you may not use this file except in compliance with the License.
  You may obtain a copy of the License at

      http://www.apache.org/licenses/LICENSE-2.0

  Unless required by applicable law or agreed to in writing, software
  distributed under the License is distributed on an "AS IS" BASIS,
  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  See the License for the specific language governing permissions and
  limitations under the License. *)

-- This script readies and uploads a public key to the server for admin use

-- Admin Tools require 10.11 and higher

set serverLoc to path to resource "server.txt" in bundle (path to me)
set serverPos to POSIX path of serverLoc
set serverAddr to do shell script "cat " & the quoted form of serverPos
--set serverAddr to "" -- put your server FQDN here

set adminLoc to path to resource "blueskyadmin.pub" in bundle (path to me)
set adminPos to POSIX path of adminLoc
-- or specify a different location for the file, get it from the client-files on the server

display dialog "Setting up this Mac OR copy-pasting keys from elsewhere?" buttons {"Copy-Paste", "This Mac", "Cancel"} default button "This Mac"
set myChoice to the result

if myChoice is {button returned:"This Mac"} then
	
	set passDialog to display dialog Â
		"Please enter a password to protect the key - make it obnoxiously good, it will be stored in your login Keychain:" with title Â
		"Password" with icon caution Â
		default answer Â
		"" buttons {"Cancel", "OK"} default button 2 Â
		giving up after 295 Â
		with hidden answer
	set my_password to the text returned of passDialog
	
	set userName to do shell script "whoami"
	set hostName to do shell script "hostname"
	--set optName to do shell script "scutil --get ComputerName"
	set epochTime to do shell script "date +%s"
	
	try
		do shell script "rm -f ~/.ssh/bluesky_admin"
		do shell script "rm -f ~/.ssh/bluesky_admin.pub"
	end try
	
	-- TODO: this might screw up 10.11, needs testing
	try
		set hostEntry to do shell script "grep 'Host " & serverAddr & "' ~/.ssh/config; exit 0"
		if hostEntry is "" then
			do shell script "echo 'Host " & serverAddr & "' >> ~/.ssh/config"
			do shell script "echo '      UseKeychain yes' >> ~/.ssh/config"
		end if
	end try
	
	do shell script "ssh-keygen -q -t ed25519 -N '" & my_password & "' -f ~/.ssh/bluesky_admin -C \"" & " uploaded@" & epochTime & " " & userName & "@" & hostName & "\""
	
	set uploadResult to do shell script "pubKey=`openssl smime -encrypt -aes256 -in ~/.ssh/bluesky_admin.pub -outform PEM " & the quoted form of adminPos & "`;curl -s -S -m 60 -1 --retry 4 -X POST --data-urlencode \"newpub=$pubKey\" https://" & serverAddr & "/cgi-bin/collector.php"
	
else if myChoice is {button returned:"Copy-Paste"} then
	
	set dialogTemp to display dialog "Please copy the public key here:" default answer ""
	set iOSpub to the text returned of dialogTemp
	set dialog2Temp to display dialog "Please enter a unique description for this key. We will overwrite keys with the same name." default answer "Copied from somewhere"
	set optName to the text returned of dialog2Temp
	set optName to do shell script "echo " & the quoted form of optName & " | tr [:blank:] '_'"
	set epochTime to do shell script "date +%s"
	
	
	set iOSupl to iOSpub & " pasted@" & epochTime & " " & optName
	
	set uploadResult to do shell script "pubKey=`echo '" & iOSupl & "' | openssl smime -encrypt -aes256 -outform PEM " & the quoted form of adminPos & "`;curl -s -S -m 60 -1 --retry 4 -X POST --data-urlencode \"newpub=$pubKey\" https://" & serverAddr & "/cgi-bin/collector.php"
	
else
	return 0
end if

if uploadResult contains "Installed" then
	display dialog "You are all set!" buttons "Woohoo!" default button 1
else if uploadResult contains "Upgrade" then
	display dialog "Please re-download Admin Tools and try this again." buttons "Okay" default button 1
else
	display dialog "Something went wrong. Please try again." buttons "Weak" default button 1
end if