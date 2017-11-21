global sshPid
global itsGone
on run
	
	set adminLoc to path to resource "blueskyadmin.pub" in bundle (path to me)
	set adminPos to POSIX path of adminLoc
	
	--do we need to turn on SSH and Screen Sharing?
	set sshState to do shell script "netstat -an | grep '*.22';exit 0"
	set vncState to do shell script "cat /Library/Preferences/com.apple.ScreenSharing.launchd; exit 0"
	if vncState is "" then
		set vncState to do shell script "ps -ax | grep ARDAgent | grep -v grep;exit 0"
	end if
	if (sshState is "") or (vncState is "") then
		display dialog "We will need you to turn on some system services to allow us to work.  Your tech will walk you through it." buttons {"OK"} default button 1
		do shell script "open /System/Library/PreferencePanes/SharingPref.prefpane"
	end if
	
	--do we need to look for proxy info
	set myPath to POSIX path of (path to me as string)
	set proxyConf to do shell script "'" & myPath & "/Contents/Resources/proxy-config' -s"
	
	--get my server
	set serverAddr to do shell script "cat '" & myPath & "/Contents/Resources/server.txt'"
	
	--will output like this:  
	--http://webcache:8080/
	try
		do shell script "mkdir ~/.ssh"
		do shell script "touch ~/.ssh/known_hosts"
		do shell script "touch ~/.ssh/config"
	end try
	
	try
		if proxyConf is not "" then
			set proxyTemp to do shell script "echo $proxyConf | cut -f 3 -d \"/\""
			set proxyServer to do shell script "echo $proxyTemp | cut -f 1 -d \":\""
			set proxyPort to do shell script "echo $proxyTemp | cut -f 2 -d \":\""
			set proxyCommand to "--proxy " & proxyConf
			set knownChk to do shell script "grep " & serverAddr & " ~/.ssh/config; exit 0"
			if knownChk is "" then
				do shell script "echo \"Host " & serverAddr & "\" >> ~/.ssh/config"
				do shell script "echo \"	ProxyCommand '" & myPath & "Contents/Resources/corkscrew' $proxyServer $proxyPort %h %p\" >> ~/.ssh/config"
			end if
		else
			set proxyCommand to ""
		end if
	on error errStr
		display dialog "Please tell your tech that there was a configuration failure: " & errStr buttons {"Quit"} default button 1
		quit
	end try
	
	try
		do shell script "rm -f ~/.ssh/bluesky_tmp"
		do shell script "rm -f ~/.ssh/bluesky_tmp.pub"
	end try
	
	try
		do shell script "ssh-keygen -q -t ssh-ed25519 -N \"\" -f ~/.ssh/bluesky_tmp -C \"tmp-`date +%s`\""
	on error errStr
		display dialog "Please tell your tech that there was a key failure: " & errStr buttons {"Quit"} default button 1
		quit
	end try
	
	set uploadResult to do shell script "pubKey=`openssl smime -encrypt -aes256 -in ~/.ssh/bluesky_tmp.pub -outform PEM " & the quoted form of adminPos & "`;curl " & proxyCommand & " -s -S -m 60 -1 --retry 4 -X POST --data-urlencode \"newpub=$pubKey\" https://" & serverAddr & "/cgi-bin/collector.php"
	if uploadResult does not contain "Installed" then
		display dialog "Please tell your tech that there was an upload failure: " & uploadResult buttons {"Quit"} default button 1
		quit
	end if
	
	delay 2
	--pick a random port
	set portNum to random number from 1950 to 1999
	set sshPort to (22000 + portNum)
	set vncPort to (24000 + portNum)
	
	do shell script "ssh -o StrictHostKeyChecking=no -c chacha20-poly1305@openssh.com -o HostKeyAlgorithms=ssh-ed25519 -m hmac-sha2-512-etm@openssh.com -o KexAlgorithms=curve25519-sha256@libssh.org -i ~/.ssh/bluesky_tmp -nNT -R " & sshPort & ":localhost:22 -R " & vncPort & ":localhost:5900 -p 3122 bluesky@" & serverAddr & " &> /dev/null & echo $!"
	set sshPid to the result
	try
		delay 2
		do shell script "ps -p " & sshPid & " | grep ssh"
		set itsGone to false
		display dialog "You are now connected with ID " & portNum & ". Quit this app to disconnect." buttons {"OK"} giving up after 29
	on error
		display dialog "Please tell your tech that connecting failed." buttons {"Quit"} default button 1
		quit
	end try
	
end run

on idle
	try
		do shell script "ps -p " & sshPid & " | grep ssh"
	on error
		if itsGone is not true then
			display dialog "The connection has become disconnected." buttons {"Quit"} default button 1
		end if
		quit
	end try
	return 5
end idle

on quit
	try
		do shell script "kill -9 " & sshPid
	end try
	try
		do shell script "rm -f ~/.ssh/bluesky_tmp"
		do shell script "rm -f ~/.ssh/bluesky_tmp.pub"
	end try
	set itsGone to true
	continue quit
	
end quit
