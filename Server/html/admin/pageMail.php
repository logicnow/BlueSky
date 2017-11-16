<?php
	$currDir=dirname(__FILE__);
	require("$currDir/incCommon.php");
	include("$currDir/incHeader.php");

	// check configured sender
	if(!isEmail($adminConfig['senderEmail'])){
		?>
		<div class="alert alert-danger">
			You can not send emails currently. 
			The configured sender email address is not valid.
			Please <a href="pageSettings.php">correct it first</a> then try again.
			</div>
		<?php
		include("$currDir/incFooter.php");
	}

	// determine and validate recipients
	if($_POST['saveChanges']==''){
		$memberID=makeSafe(strtolower($_GET['memberID']));
		$groupID=intval($_GET['groupID']);
		$sendToAll=intval($_GET['sendToAll']);

		$isGroup=($memberID!='' ? FALSE : TRUE);
		$recipient=($sendToAll ? "All groups" : ($isGroup ? sqlValue("select name from membership_groups where groupID='$groupID'") : sqlValue("select memberID from membership_users where lcase(memberID)='$memberID'")));
		if(!$recipient){
			?>
			<div class="alert alert-danger">
				Couldn't find recipient. Please make sure you provide a valid recipient.
				</div>
			<?php
			include("$currDir/incFooter.php");
		}
	}else{
	// begin sending emails
		$memberID=makeSafe(strtolower($_POST['memberID']));
		$groupID=intval($_POST['groupID']);
		$sendToAll=intval($_POST['sendToAll']);

		$mailSubject=(get_magic_quotes_gpc() ? $_POST['mailSubject'] : addslashes($_POST['mailSubject']));
		$mailMessage=(get_magic_quotes_gpc() ? $_POST['mailMessage'] : addslashes($_POST['mailMessage']));
		$mailMessage=str_replace("\n", "\\n", $mailMessage);
		$mailMessage=str_replace("\r", "\\r", $mailMessage);

		// validate that subject is a single line
		if(preg_match("/(%0A|%0D|\n+|\r+)/i", $mailSubject)){
			echo "<div class=\"status\">Invalid subject line.</div>";
			exit;
		}

		$isGroup=($memberID!='' ? FALSE : TRUE);
		$recipient=($sendToAll ? "All groups" : ($isGroup ? sqlValue("select name from membership_groups where groupID='$groupID'") : sqlValue("select lcase(memberID) from membership_users where lcase(memberID)='$memberID'")));
		if(!$recipient){
			?>
			<div class="alert alert-danger">
				Couldn't find recipient. Please make sure you provide a valid recipient.
				</div>
			<?php
			include("$currDir/incFooter.php");
		}

		// create a recipients array
		if($sendToAll){
			$res=sql("select email from membership_users", $eo);
		}elseif($isGroup){
			$res=sql("select email from membership_users where groupID='$groupID'", $eo);
		}else{
			$res=sql("select email from membership_users where lcase(memberID)='$memberID'", $eo);
		}
		while($row=db_fetch_row($res)){
			$to[]=$row[0];
		}

		// check that there is at least 1 recipient
		if(count($to)<1){
			?>
			<div class="alert alert-danger">
				Couldn't find any recipients. Please make sure you provide a valid recipient.
				</div>
			<?php
			include("$currDir/incFooter.php");
		}

		// save mail queue
		$queueFile=md5(microtime());
		$currDir=dirname(__FILE__);
		if(!$fp=fopen("$currDir/$queueFile.php", "w")){
			?>
			<div class="alert alert-danger">
				Couldn't save mail queue. Please make sure the directory '<?php echo $currDir; ?>' is writeable (chmod 755 or chmod 777).
				</div>
			<?php
			include("$currDir/incFooter.php");
		}else{
			fwrite($fp, "<?php\n");
			foreach($to as $recip){
				fwrite($fp, "\t\$to[]='$recip';\n");
			}
			fwrite($fp, "\t\$mailSubject=\"$mailSubject\";\n");
			fwrite($fp, "\t\$mailMessage=\"$mailMessage\";\n");
			fwrite($fp, "?>");
			fclose($fp);
		}

		// redirect to mail queue processor
		redirect("admin/pageSender.php?queue=$queueFile");
		include("$currDir/incFooter.php");
	}


?>

<div class="page-header"><h1>Send mail message to a member/group</h1></div>

<?php if($sendToAll){ ?>
	<div class="alert alert-warning"><u>Attention!</u><br>You are sending an email to all members. This could take a lot of time and affect your server performance. If you have a huge number of members, we don't recommend sending an email to all of them at once.</div>
<?php } ?>

<form method="post" action="pageMail.php">
	<input type="hidden" name="memberID" value="<?php echo $memberID; ?>">
	<input type="hidden" name="groupID" value="<?php echo $groupID; ?>">
	<input type="hidden" name="sendToAll" value="<?php echo $sendToAll; ?>">
	<table class="table table-striped">
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">From</div>
				</td>
			<td align="left" class="tdFormInput">
				<?php echo $adminConfig['senderName']." &lt;".$adminConfig['senderEmail']."&gt;"; ?>
				<br><a href="pageSettings.php">Change this setting</a>
				</td>
			</tr>

		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">To</div>
				</td>
			<td align="left" class="tdFormInput">
				<a href="<?php echo ($sendToAll ? "pageViewMembers.php" : ($isGroup ? "pageViewMembers.php?groupID=$groupID" : "pageEditMember.php?memberID=$memberID")); ?>"><img src="images/<?php echo (($isGroup||$sendToAll) ? "members_icon.gif" : "member_icon.gif"); ?>" border="0"></a> <?php echo $recipient; ?>
				</td>
			</tr>

		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Subject</div>
				</td>
			<td align="left" class="tdFormInput">
				<input type="text" name="mailSubject" value="" size="60" class="formTextBox">
				</td>
			</tr>

		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Message</div>
				</td>
			<td align="left" class="tdFormInput">
				<textarea name="mailMessage" cols="60" rows="10" class="formTextBox"></textarea>
				</td>
			</tr>

		<tr>
			<td colspan="2" align="right" class="tdFormFooter">
				<input type="submit" name="saveChanges" value="Send Message" onClick="return jsShowWait();">
				</td>
			</tr>
		</table>
</form>
<?php
	include("$currDir/incFooter.php");
?>