<?php
	$currDir=dirname(__FILE__);
	require("$currDir/incCommon.php");

	// request to save changes?
	if($_REQUEST['saveChanges']!=''){
		// validate data
		$recID = intval($_REQUEST['recID']);
		$memberID = makeSafe(strtolower($_REQUEST['memberID']));
		$groupID = intval($_REQUEST['groupID']);
		###############################

		/* for ajax requests coming from the users' area, get the recID */
		if(is_ajax()){
			$tableName = $_REQUEST['t'];
			$pkValue = $_REQUEST['pkValue'];

			if(!in_array($tableName, array_keys(getTableList()))){
				die('Invalid table.');
			}

			if(!$pkValue){
				die('Invalid primary key value');
			}

			$recID = sqlValue("select recID from membership_userrecords where tableName='{$tableName}' and pkValue='" . makeSafe($pkValue) . "'");
			if(!$recID){
				die('Record not found ... if it was imported externally, try assigning an owner from the admin area.');
			}

			/* determine groupID if not provided */
			if(!$groupID){
				$groupID = sqlValue("select groupID from membership_users where memberID='{$memberID}'");
				if(!$groupID) die('Invalid username');
			}
		}

		// update ownership
		$upQry="UPDATE `membership_userrecords` set memberID='{$memberID}', groupID='{$groupID}' WHERE recID='{$recID}'";
		sql($upQry, $eo);

		if(is_ajax){
			echo 'OK';
			exit;
		}

		// redirect to member editing page
		redirect("admin/pageEditOwnership.php?recID=$recID");

	}elseif($_GET['recID']!=''){
		// we have an edit request for a member
		$recID=makeSafe($_GET['recID']);
	}

	include("$currDir/incHeader.php");

	if($recID!=''){
		// fetch record data to fill in the form below
		$res=sql("select * from membership_userrecords where recID='$recID'", $eo);
		if($row=db_fetch_assoc($res)){
			// get record data
			$tableName=$row['tableName'];
			$pkValue=$row['pkValue'];
			$memberID=strtolower($row['memberID']);
			$dateAdded=@date($adminConfig['PHPDateTimeFormat'], $row['dateAdded']);
			$dateUpdated=@date($adminConfig['PHPDateTimeFormat'], $row['dateUpdated']);
			$groupID=$row['groupID'];
		}else{
			// no such record exists
			die("<div class=\"alert alert-danger\">Error: Record not found!</div>");
		}
	}else{
		redirect("admin/pageViewRecords.php");
	}
?>
<div class="page-header"><h1>Edit Record Ownership</h1></div>
<form method="post" action="pageEditOwnership.php">
	<input type="hidden" name="recID" value="<?php echo $recID; ?>">
	<div class="table-responsive"><table class="table table-striped">
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Owner group</div>
				</td>
			<td align="left" class="tdFormInput">
				<?php
					echo htmlSQLSelect('groupID', "select g.groupID, g.name from membership_groups g order by name", $groupID);
				?>
				<a href="#" onClick="window.location='pageViewRecords.php?groupID='+escape(document.getElementById('groupID').value);"><img src="images/data_icon.gif" alt="View all records by this group" title="View all records by this group" border="0"></a>
				</td>
			</tr>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Owner member</div>
				</td>
			<td align="left" class="tdFormInput" width="460">
				<?php
					echo htmlSQLSelect('memberID', "select lcase(memberID), lcase(memberID) from membership_users where groupID='$groupID' order by memberID", $memberID);
				?>
				<a href="#" onClick="window.location='pageViewRecords.php?memberID='+escape(document.getElementById('memberID').value);"><img src="images/data_icon.gif" alt="View all records by this member" title="View all records by this member" border="0"></a>
				<br>If you want to switch ownership of this record to a member of another group,
				you must change the owner group and save changes first.
				</td>
			</tr>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Record created on</div>
				</td>
			<td align="left" class="tdFormInput">
				<?php echo $dateAdded; ?>
				</td>
			</tr>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Record modified on</div>
				</td>
			<td align="left" class="tdFormInput">
				<?php echo $dateUpdated; ?>
				</td>
			</tr>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Table</div>
				</td>
			<td align="left" class="tdFormInput">
				<?php echo $tableName; ?>
				<a href="pageViewRecords.php?tableName=<?php echo $tableName; ?>"><img src="images/data_icon.gif" alt="View all records of this table" title="View all records of this table" border="0"></a>
				</td>
			</tr>
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Record data</div>
				<input type="button" value="Print" onClick="window.location='pagePrintRecord.php?recID=<?php echo $recID; ?>';"> &nbsp; &nbsp;
				</td>
			<td align="left" class="tdFormInput">
				<?php 
					// get pk field name
					$pkField=getPKFieldName($tableName);

					// get field list
					if(!$res=sql("show fields from `$tableName`", $eo)){
						errorMsg("Couldn't retrieve field list from '$tableName'");
					}
					while($row=db_fetch_assoc($res)){
						$field[]=$row['Field'];
					}

					$res=sql("select * from `$tableName` where `$pkField`='" . makeSafe($pkValue, false) . "'", $eo);
					if($row=db_fetch_assoc($res)){
						?>
						<table class="table table-striped">
							<tr>
								<td class="tdHeader"><div class="ColCaption">Field name</div></td>
								<td class="tdHeader"><div class="ColCaption">Value</div></td>
								</tr>
						<?php
						include("$currDir/../language.php");
						foreach($field as $fn){
							if(@is_file("$currDir/../".$Translation['ImageFolder'].$row[$fn])){
								$op="<a href=\""."../".$Translation['ImageFolder'].$row[$fn]."\" target=\"_blank\">".htmlspecialchars($row[$fn])."</a>";
							}else{
								$op=htmlspecialchars($row[$fn]);
							}
							?>
							<tr>
								<td class="tdCaptionCell" valign="top"><?php echo $fn; ?></td>
								<td class="tdCell" valign="top">
									<?php echo $op; ?>
									</td>
								</tr>
							<?php
						}
						?>
							</table>
						<?php
					}

				?>
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