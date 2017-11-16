<?php
	$currDir=dirname(__FILE__);
	require("$currDir/incCommon.php");

	// get memberID of anonymous member
	$anonMemberID=strtolower($adminConfig['anonymousMember']);

	$memberID = '';
	// request to save changes?
	if($_POST['saveChanges'] != ''){
		// validate data
		$oldMemberID = makeSafe(strtolower($_POST['oldMemberID']));
		$password = makeSafe($_POST['password']);
		$email = isEmail($_POST['email']);
		$groupID = intval($_POST['groupID']);
		$isApproved = ($_POST['isApproved'] == 1 ? 1 : 0);
		$isBanned = ($_POST['isBanned'] == 1 ? 1 : 0);
		$custom1 = makeSafe($_POST['custom1']);
		$custom2 = makeSafe($_POST['custom2']);
		$custom3 = makeSafe($_POST['custom3']);
		$custom4 = makeSafe($_POST['custom4']);
		$comments = makeSafe($_POST['comments']);
		###############################

		// new member or old?
		if(!$oldMemberID){ // new member
			// make sure member name is unique
			$memberID = is_allowed_username($_POST['memberID']);
			if(!$memberID){
				echo "<div class=\"alert alert-danger\">Error: Username already exists or is invalid. Make sure you provide a username containing 4 to 20 valid characters.</div>";
				include("$currDir/incFooter.php");
			}

			// add member
			sql("INSERT INTO `membership_users` set memberID='$memberID', passMD5='".md5($password)."', email='$email', signupDate='".@date('Y-m-d')."', groupID='$groupID', isBanned='$isBanned', isApproved='$isApproved', custom1='$custom1', custom2='$custom2', custom3='$custom3', custom4='$custom4', comments='$comments'", $eo);

			if($isApproved){
				notifyMemberApproval($memberID);
			}

		}else{ // old member

			// make sure new member username, if applicable, is valid
			$memberID = makeSafe(strtolower($_POST['memberID']));
			if($oldMemberID != $memberID) $memberID = is_allowed_username($_POST['memberID']);

			if(!$memberID){
				echo "<div class=\"alert alert-danger\">Error: Username already exists or is invalid. Make sure you provide a username containing 4 to 20 valid characters.</div>";
				include("$currDir/incFooter.php");
			}

			// anonymousMember?
			if($anonMemberID==$memberID){
				$password='';
				$email='';
				$groupID=sqlValue("select groupID from membership_groups where name='".$adminConfig['anonymousGroup']."'");
				$isApproved=1;
			}

			// get current approval state
			$oldIsApproved=sqlValue("select isApproved from membership_users where lcase(memberID)='$memberID'");

			// update member
			$upQry="UPDATE `membership_users` set memberID='$memberID', passMD5=".($password!='' ? "'".md5($password)."'" : "passMD5").", email='$email', groupID='$groupID', isBanned='$isBanned', isApproved='$isApproved', custom1='$custom1', custom2='$custom2', custom3='$custom3', custom4='$custom4', comments='$comments' WHERE lcase(memberID)='$oldMemberID'";
			sql($upQry, $eo);

			// if memberID was changed, update membership_userrecords
			if($oldMemberID!=$memberID){
				sql("update membership_userrecords set memberID='$memberID' where lcase(memberID)='$oldMemberID'", $eo);
			}

			// is member was approved, notify him
			if($isApproved && !$oldIsApproved){
				notifyMemberApproval($memberID);
			}
		}

		// redirect to member editing page
		redirect("admin/pageEditMember.php?memberID=$memberID");

	}elseif($_GET['memberID']!=''){
		// we have an edit request for a member
		$memberID=makeSafe(strtolower($_GET['memberID']));
	}elseif($_GET['groupID']!=''){
		// show the form for adding a new member, and pre-select the provided group
		$groupID=intval($_GET['groupID']);
		$group_name = sqlValue("select name from membership_groups where groupID='$groupID'");
		if($group_name) $addend = " to '{$group_name}'";
	}

	include("$currDir/incHeader.php");

	if($memberID!=''){
		// fetch group data to fill in the form below
		$res=sql("select * from membership_users where lcase(memberID)='$memberID'", $eo);
		if($row=db_fetch_assoc($res)){
			// get member data
			$email=$row['email'];
			$groupID=$row['groupID'];
			$isApproved=$row['isApproved'];
			$isBanned=$row['isBanned'];
			$custom1=htmlspecialchars($row['custom1']);
			$custom2=htmlspecialchars($row['custom2']);
			$custom3=htmlspecialchars($row['custom3']);
			$custom4=htmlspecialchars($row['custom4']);
			$comments=htmlspecialchars($row['comments']);
		}else{
			// no such member exists
			echo "<div class=\"alert alert-danger\">Error: Member not found!</div>";
			$memberID='';
		}
	}

	if($memberID!='' && $memberID!=$anonMemberID && $groupID!=sqlValue("select groupID from membership_groups where name='Admins'")){
		if(sqlValue("select count(1) from membership_userpermissions where memberID='$memberID'")>0){
			$userPermissionsNote='<br><i>This user has special permissions that override his group permissions.</i><br>';
		}else{
			$userPermissionsNote='<br><i>This user inherits the <a href="pageEditGroup.php?groupID=' . $groupID . '">permissions of his group</a>.</i><br>';
		}
		$userPermissionsNote.='<input type="button" class="" value="Set special permissions for this user" onClick="if(confirm(\'If you made any changes to this member and did not save them yet, they will be lost if you continue. Are you sure you want to continue?\')){ window.location=\'pageEditMemberPermissions.php?memberID='.urlencode($memberID).'\'; }">';
	}else{
		$userPermissionsNote='';
	}
?>
<div class="page-header"><h1><?php echo ($memberID ? "Edit Member '$memberID'" : "Add New Member".$addend); ?></h1></div>
<?php if($anonMemberID==$memberID){ ?>
	<div class="alert alert-warning">Attention! This is the anonymous (guest) member.</div>
<?php }elseif($memberID==strtolower($adminConfig['adminUsername'])){ ?>
	<div class="alert alert-warning">Attention! This is the admin member. You can't change the username, password or email of this member here, but you can do so in the <a href="pageSettings.php">admin settings</a> page.</div>
<?php } ?>
<form method="post" action="pageEditMember.php" onSubmit="return jsValidateMember();" autocomplete="off">
	<input type="hidden" name="oldMemberID" value="<?php echo ($memberID ? $memberID : ""); ?>">
	<div class="table-responsive"><table class="table table-striped">
	<?php if($memberID!=strtolower($adminConfig['adminUsername'])){ ?>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Member username</div>
				</td>
			<td align="left" class="tdFormInput">
				<input type="text" name="memberID" <?php echo ($anonMemberID==$memberID ? "readonly" : "");?> id="memberID" value="<?php echo $memberID; ?>" size="20" class="formTextBox">
				<?php echo ($memberID ? "" : "<input type=\"button\" value=\"Check availability\" onClick=\"window.open('../checkMemberID.php?memberID='+document.getElementById('memberID').value, 'checkMember', 'innerHeight=100,innerWidth=400,dependent=yes,screenX=200,screenY=200,status=no');\">"); ?>
				<?php if($anonMemberID==$memberID){ ?>
				<br>The username of the guest member is read-only.
				<?php } ?>
				</td>
			</tr>
		<?php if($anonMemberID!=$memberID){ ?>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Password</div>
				</td>
			<td align="left" class="tdFormInput">
				<input type="password" name="password" id="password" value="" size="20" class="formTextBox" autocomplete="off">
				<?php echo ($memberID ? "<br>Type a password only if you want to change this member's<br>password. Otherwise, leave this field empty." : ""); ?>
				</td>
			</tr>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Confirm password</div>
				</td>
			<td align="left" class="tdFormInput">
				<input type="password" name="confirmPassword" id="confirmPassword" value="" size="20" class="formTextBox" autocomplete="off">
				</td>
			</tr>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Email</div>
				</td>
			<td align="left" class="tdFormInput">
				<input type="text" name="email" value="<?php echo $email; ?>" size="40" class="formTextBox">
				</td>
			</tr>
		<?php } ?>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Group</div>
				</td>
			<td align="left" class="tdFormInput">
				<?php 
					if($anonMemberID!=$memberID){
						echo htmlSQLSelect('groupID', "select groupID, name from membership_groups order by name", $groupID);
					}else{
						echo $adminConfig['anonymousGroup'];
					}

					echo $userPermissionsNote;
				?>
				</td>
			</tr>
		<?php if($anonMemberID!=$memberID){ ?>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Approved?</div>
				</td>
			<td align="left" class="tdFormInput">
				<input type="checkbox" name="isApproved" value="1" <?php echo ($isApproved ? "checked" : ($memberID ? "" : "checked")); ?>>
				</td>
			</tr>
		<?php } ?>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Banned?</div>
				</td>
			<td align="left" class="tdFormInput">
				<input type="checkbox" name="isBanned" value="1" <?php echo ($isBanned ? "checked" : ""); ?>>
				</td>
			</tr>
	<?php } ?>
		<?php if($adminConfig['custom1']!=''){ ?>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption"><?php echo $adminConfig['custom1']; ?></div>
				</td>
			<td align="left" class="tdFormInput">
				<input type="text" name="custom1" value="<?php echo $custom1; ?>" size="40" class="formTextBox">
				</td>
			</tr>
		<?php } ?>
		<?php if($adminConfig['custom2']!=''){ ?>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption"><?php echo $adminConfig['custom2']; ?></div>
				</td>
			<td align="left" class="tdFormInput">
				<input type="text" name="custom2" value="<?php echo $custom2; ?>" size="40" class="formTextBox">
				</td>
			</tr>
		<?php } ?>
		<?php if($adminConfig['custom3']!=''){ ?>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption"><?php echo $adminConfig['custom3']; ?></div>
				</td>
			<td align="left" class="tdFormInput">
				<input type="text" name="custom3" value="<?php echo $custom3; ?>" size="40" class="formTextBox">
				</td>
			</tr>
		<?php } ?>
		<?php if($adminConfig['custom4']!=''){ ?>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption"><?php echo $adminConfig['custom4']; ?></div>
				</td>
			<td align="left" class="tdFormInput">
				<input type="text" name="custom4" value="<?php echo $custom4; ?>" size="40" class="formTextBox">
				</td>
			</tr>
		<?php } ?>
		<tr>
			<td align="right" valign="top" class="tdFormCaption">
				<div class="formFieldCaption">Comments</div>
				</td>
			<td align="left" class="tdFormInput">
				<textarea name="comments" cols="50" rows="3" class="formTextBox"><?php echo $comments; ?></textarea>
				</td>
			</tr>
		<tr>
			<td colspan="2" align="right" class="tdFormFooter">
				<input type="submit" name="saveChanges" value="Save changes">
				</td>
			</tr>
		</table></div>
	</form>


<?php
	include("$currDir/incFooter.php");
?>