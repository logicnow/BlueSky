global serverAddr
on set_server()
	set serverLoc to path to resource "server.txt" in bundle (path to me)
	set serverPos to POSIX path of serverLoc
	set serverAddr to do shell script "cat " & the quoted form of serverPos
end set_server

on open location this_URL
	set_server()
	
	-- When the link is clicked in thewebpage, this handler will be passed 
	-- the URL that triggered the action, similar to:
	--> bluesky://com.solarwindsmsp.bluesky?key=value&key=value
	
	-- EXTRACT ARGUMENTS
	set x to the offset of "?" in this_URL
	set the argument_string to text from (x + 1) to -1 of this_URL
	set AppleScript's text item delimiters to "&"
	set these_arguments to every text item of the argument_string
	set AppleScript's text item delimiters to ""
	set userName to ""
	
	-- PROCESS ACTIONS
	-- This loop will execute scripts located within the Resources folder
	-- of this applet depending on the key and value passed in the URL
	repeat with i from 1 to the count of these_arguments
		set this_pair to item i of these_arguments
		set AppleScript's text item delimiters to "="
		copy every text item of this_pair to {this_key, this_value}
		set AppleScript's text item delimiters to ""
		if this_key is "blueSkyID" then
			set blueSkyID to this_value
		else if this_key is "user" then
			set userName to this_value
		else if this_key is "action" then
			set actionStep to this_value
		end if
	end repeat
	--here we go
	if userName is "" then
		set dialogTemp to display dialog "Please enter the user name on the remote computer:" default answer "" with icon path to resource "applet.icns" in bundle (path to me)
		set userName to the text returned of dialogTemp
	end if
	set sshPort to (22000 + blueSkyID)
	set vncPort to (24000 + blueSkyID)
	if actionStep is "ssh" then
		remote_shell(blueSkyID, sshPort, vncPort, serverAddr, userName)
	else if actionStep is "vnc" then
		screen_share(blueSkyID, sshPort, vncPort, serverAddr, userName)
	else if actionStep is "scp" then
		file_upload(blueSkyID, sshPort, serverAddr, userName)
	end if
end open location


set_server()
-- This handler will load, then execute, a script file 
-- located in the Resources folder of this applet.
-- This method allows you to change property values
-- within the loaded script before execution,
-- or to execute handlers within the loaded script.

set serverLoc to path to resource "server.txt" in bundle (path to me)
set serverPos to POSIX path of serverLoc
set serverAddr to do shell script "cat " & the quoted form of serverPos
--hard code and uncomment below if you'd rather
--set serverAddr to "serverFQDN"

set dialogTemp to display dialog "Please enter the BlueSky ID number you want to connect with:" default answer "" with icon path to resource "applet.icns" in bundle (path to me)
set blueSkyID to the text returned of dialogTemp

display dialog "Please choose the action that you would like to perform." buttons {"Screen Share", "File Upload", "Remote Shell"} default button "Remote Shell" with icon path to resource "applet.icns" in bundle (path to me)
set myChoice to the result

set sshPort to (22000 + blueSkyID)
set vncPort to (24000 + blueSkyID)

set dialogTemp to display dialog "Please enter the user name on the remote computer:" default answer "" with icon path to resource "applet.icns" in bundle (path to me)
set userName to the text returned of dialogTemp

if myChoice is {button returned:"Remote Shell"} then
	remote_shell(blueSkyID, sshPort, vncPort, serverAddr, userName)
else if myChoice is {button returned:"Screen Share"} then
	screen_share(blueSkyID, sshPort, vncPort, serverAddr, userName)
else if myChoice is {button returned:"File Upload"} then
	file_upload(blueSkyID, sshPort, serverAddr, userName)
else
	return 0
end if



on remote_shell(blueSkyID, sshPort, vncPort, serverAddr, userName)
	tell application "Terminal"
		activate
		do script "ssh -t -o \"ProxyCommand ssh -p 3122 -i ~/.ssh/bluesky_admin admin@" & serverAddr & " /bin/nc %h %p\" -o \"LocalForward " & vncPort & " localhost:5900\" -o \"StrictHostKeyChecking=no\" -p " & sshPort & " " & userName & "@localhost"
	end tell
end remote_shell

on screen_share(blueSkyID, sshPort, vncPort, serverAddr, userName)
	set vncCheck to do shell script "ps -ax | grep ssh | grep " & vncPort & " | grep -v grep;exit 0"
	if vncCheck is "" then
		tell application "Terminal"
			activate
			do script "ssh -t -o \"ProxyCommand ssh -p 3122 -i ~/.ssh/bluesky_admin admin@" & serverAddr & " /bin/nc %h %p\" -o \"LocalForward " & vncPort & " localhost:5900\" -o \"StrictHostKeyChecking=no\" -p " & sshPort & " " & userName & "@localhost"
		end tell
		--delay 10
		--seems to be broken in Dos Equis
		do shell script "sleep 10"
		set vncCheck2 to do shell script "ps -ax | grep ssh | grep " & vncPort & " | grep -v grep;exit 0"
		if vncCheck2 is "" then
			return 0
		end if
		display dialog "Click OK after you are logged in to SSH to proceed to VNC login." default button 1 buttons "OK" giving up after 295 with icon path to resource "applet.icns" in bundle (path to me)
	end if
	tell application "Screen Sharing"
		activate
		GetURL "vnc://" & userName & "@localhost:" & vncPort
	end tell
end screen_share

on file_upload(blueSkyID, sshPort, serverAddr, userName)
	display dialog "Do you want to upload a single file or a folder?" buttons {"File", "Folder", "Cancel"} default button "Folder" with icon path to resource "applet.icns" in bundle (path to me)
	set myChoice to the result
	
	if myChoice is {button returned:"Folder"} then
		set the source_folder to choose folder with prompt "Select the folder to be uploaded:"
		set posixSrc to the POSIX path of source_folder
		tell application "Terminal"
			activate
			do script "scp -r -C -P " & sshPort & " -o \"StrictHostKeyChecking=no\" -o \"ProxyCommand ssh -p 3122 -i ~/.ssh/bluesky_admin admin@" & serverAddr & " /bin/nc %h %p\" " & the quoted form of posixSrc & " " & userName & "@localhost:/tmp && exit	"
		end tell
	else if myChoice is {button returned:"File"} then
		set the source_file to choose file with prompt "Select the file to be uploaded:"
		set posixSrc to the POSIX path of source_file
		tell application "Terminal"
			activate
			do script "scp -C -P " & sshPort & " -o \"StrictHostKeyChecking=no\" -o \"ProxyCommand ssh -p 3122 -i ~/.ssh/bluesky_admin admin@" & serverAddr & " /bin/nc %h %p\" " & the quoted form of posixSrc & " " & userName & "@localhost:/tmp && exit"
		end tell
	else
		return 0
	end if
end file_upload
