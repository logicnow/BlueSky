<?php
	$currDir=dirname(__FILE__);
	require("$currDir/incCommon.php");
	include("$currDir/incHeader.php");

	/* we need the following variables:
		$sourceGroupID
		$sourceMemberID (-1 means "all")
		$destinationGroupID
		$destinationMemberID

		if $sourceGroupID!=$destinationGroupID && $sourceMemberID==-1, an additional var:
		$moveMembers (=0 or 1)
	*/

	// validate input vars
	$sourceGroupID=intval($_GET['sourceGroupID']);
	$sourceMemberID=makeSafe(strtolower($_GET['sourceMemberID']));
	$destinationGroupID=intval($_GET['destinationGroupID']);
	$destinationMemberID=makeSafe(strtolower($_GET['destinationMemberID']));
	$moveMembers=intval($_GET['moveMembers']);

	// transfer operations
	if($sourceGroupID && $sourceMemberID && $destinationGroupID && ($destinationMemberID || $moveMembers) && $_GET['beginTransfer']!=''){
		/* validate everything:
			1. Make sure sourceMemberID belongs to sourceGroupID
			2. if moveMembers is false, make sure destinationMemberID belongs to destinationGroupID
		*/
		if(!sqlValue("select count(1) from membership_users where lcase(memberID)='$sourceMemberID' and groupID='$sourceGroupID'")){
			if($sourceMemberID!=-1){
				errorMsg("Invalid source member selected.");
				include("$currDir/incFooter.php");
			}
		}
		if(!$moveMembers){
			if(!sqlValue("select count(1) from membership_users where lcase(memberID)='$destinationMemberID' and groupID='$destinationGroupID'")){
				errorMsg("Invalid destination member selected.");
				include("$currDir/incFooter.php");
			}
		}

		// get group names
		$sourceGroup=sqlValue("select name from membership_groups where groupID='$sourceGroupID'");
		$destinationGroup=sqlValue("select name from membership_groups where groupID='$destinationGroupID'");

		// begin transfer
		echo "<br><br><br>";
		if($moveMembers && $sourceMemberID!=-1){
			echo "Moving member '$sourceMemberID' and his data from group '$sourceGroup' to group '$destinationGroup' ...";

			// change source member group
			sql("update membership_users set groupID='$destinationGroupID' where lcase(memberID)='$sourceMemberID' and groupID='$sourceGroupID'", $eo);
			$newGroup=sqlValue("select name from membership_users u, membership_groups g where u.groupID=g.groupID and lcase(u.memberID)='$sourceMemberID'");

			// change group of source member's data
			sql("update membership_userrecords set groupID='$destinationGroupID' where lcase(memberID)='$sourceMemberID' and groupID='$sourceGroupID'", $eo);
			$dataRecs=sqlValue("select count(1) from membership_userrecords where lcase(memberID)='$sourceMemberID' and groupID='$destinationGroupID'");

			// status
			$status="Member '$sourceMemberID' now belongs to group '$newGroup'. Data records transfered: $dataRecs.";

		}elseif(!$moveMembers && $sourceMemberID!=-1){
			echo "Moving data of member '$sourceMemberID' from group '$sourceGroup' to member '$destinationMemberID' from group '$destinationGroup' ...";

			// change group and owner of source member's data
			$srcDataRecsBef=sqlValue("select count(1) from membership_userrecords where lcase(memberID)='$sourceMemberID' and groupID='$sourceGroupID'");
			sql("update membership_userrecords set groupID='$destinationGroupID', memberID='$destinationMemberID' where lcase(memberID)='$sourceMemberID' and groupID='$sourceGroupID'", $eo);
			$srcDataRecsAft=sqlValue("select count(1) from membership_userrecords where lcase(memberID)='$sourceMemberID' and groupID='$sourceGroupID'");

			// status
			$status="Member '$sourceMemberID' of group '$sourceGroup' had $srcDataRecsBef data records. ".($srcDataRecsAft>0 ? "No records were tranfered" : "These records now belong")." to member '$destinationMemberID' of group '$destinationGroup'.";

		}elseif($moveMembers){
			echo "Moving all members and data of group '$sourceGroup' to group '$destinationGroup' ...";

			// change source members group
			sql("update membership_users set groupID='$destinationGroupID' where groupID='$sourceGroupID'", $eo);
			$srcGroupMembers=sqlValue("select count(1) from membership_users where groupID='$sourceGroupID'");

			// change group of source member's data
			if(!$srcGroupMembers){
				$dataRecsBef=sqlValue("select count(1) from membership_userrecords where groupID='$sourceGroupID'");
				sql("update membership_userrecords set groupID='$destinationGroupID' where groupID='$sourceGroupID'", $eo);
				$dataRecsAft=sqlValue("select count(1) from membership_userrecords where groupID='$sourceGroupID'");
			}

			// status
			if($srcGroupMembers){
				$status="Operation failed. No members were transfered from group '$sourceGroup' to '$destinationGroup'.";
			}else{
				$status="All members of group '$sourceGroup' now belong to '$destinationGroup'. ";
				if($dataRecsAft){
					$status.="However, data records failed to transfer.";
				}else{
					$status.="$dataRecsBef data records were transfered.";
				}
			}

		}else{
			echo "Moving data of all members of group '$sourceGroup' to member '$destinationMemberID' from group '$destinationGroup' ...";

			// change group of source member's data
			$recsBef=sqlValue("select count(1) from membership_userrecords where lcase(memberID)='$destinationMemberID'");
			sql("update membership_userrecords set groupID='$destinationGroupID', memberID='$destinationMemberID' where groupID='$sourceGroupID'", $eo);
			$recsAft=sqlValue("select count(1) from membership_userrecords where lcase(memberID)='$destinationMemberID'");

			// status
			$status=intval($recsAft-$recsBef)." record(s) were transfered from group '$sourceGroup' to member '$destinationMemberID' of group '$destinationGroup'";

		}

		// display status and a batch bookmark for later instant reuse of the wizard
		?>
		<div class="alert alert-info"><b>STATUS:</b><br><?php echo $status; ?></div>
		<div>
			To repeat the same batch transfer again later you can
			<a href="pageTransferOwnership.php?sourceGroupID=<?php echo $sourceGroupID; ?>&amp;sourceMemberID=<?php echo urlencode($sourceMemberID); ?>&amp;destinationGroupID=<?php echo $destinationGroupID; ?>&amp;destinationMemberID=<?php echo urlencode($destinationMemberID); ?>&amp;moveMembers=<?php echo $moveMembers; ?>">bookmark or copy this link</a>.
			</div>
		<?php

		// quit
		include("$currDir/incFooter.php");
	}


	// STEP 1
	?>

	<div class="page-header"><h1>Batch Transfer Of Ownership</h1></div>

	<form method="get" action="pageTransferOwnership.php">
		<table class="table table-striped">
			<tr>
				<td class="tdHeader" colspan="2">
					<h3>STEP 1:</h3>
					The batch transfer wizard allows you to tranfer data records
					of one or all members of a group (the <i>source group</i>)
					to a member of another group (the <i>destination member</i> of the <i>destination group</i>)
					</td>
				</tr>
			<tr>
				<td class="tdFormCaption">
					Source group
					</td>
				<td class="tdCell">
					<?php
						echo htmlSQLSelect("sourceGroupID", "select distinct g.groupID, g.name from membership_groups g, membership_users u where g.groupID=u.groupID order by g.name", $sourceGroupID);
					?>
					<input type="submit" value="<?php echo ($sourceGroupID ? "Update" : "Next Step"); ?>">
					</td>
				</tr>
	<?php

	// STEP 2
		if($sourceGroupID){
			?>
			<tr>
				<td class="tdCell" colspan="2">
					This group has <?php echo sqlValue("select count(1) from membership_users where groupID='$sourceGroupID'"); ?> members, and
					<?php echo sqlValue("select count(1) from membership_userrecords where groupID='$sourceGroupID'"); ?> data records.
					</td>
				</tr>
			<tr>
				<td class="tdHeader" colspan="2">
					<h3>STEP 2:</h3>
					The source member could be one member or all members of the source group.
					</td>
				</tr>
			<tr>
				<td class="tdFormCaption">
					Source member
					</td>
				<td class="tdCell">
					<?php
						$arrVal[]='';
						$arrCap[]='';
						$arrVal[]='-1';
						$arrCap[]="All members of '".htmlspecialchars(sqlValue("select name from membership_groups where groupID='$sourceGroupID'"))."'";
						if($res=sql("select lcase(memberID), lcase(memberID) from membership_users where groupID='$sourceGroupID' order by memberID", $eo)){
							while($row=db_fetch_row($res)){
								$arrVal[]=$row[0];
								$arrCap[]=$row[1];
							}
							echo htmlSelect("sourceMemberID", $arrVal, $arrCap, $sourceMemberID);
						}
					?>
					<input type="submit" value="<?php echo ($sourceMemberID ? "Update" : "Next Step"); ?>">
					</td>
				</tr>
			<?php
		}

	// STEP 3
		if($sourceMemberID){
			?>
			<tr>
				<td class="tdCell" colspan="2">
					This member has <?php echo sqlValue("select count(1) from membership_userrecords where ".($sourceMemberID==-1 ? "groupID='$sourceGroupID'" : "memberID='$sourceMemberID'")); ?> data records.
					</td>
				</tr>
			<tr>
				<td class="tdHeader" colspan="2">
					<h3>STEP 3:</h3>
					The destination group could be the same or different from the source group. Only groups that have members are listed below.
					</td>
				</tr>
			<tr>
				<td class="tdFormCaption">
					Destination group
					</td>
				<td class="tdCell">
					<?php
						echo htmlSQLSelect("destinationGroupID", "select distinct membership_groups.groupID, name from membership_groups, membership_users where membership_groups.groupID=membership_users.groupID order by name", $destinationGroupID);
					?>
					<input type="submit" value="<?php echo ($destinationGroupID ? "Update" : "Next Step"); ?>">
					</td>
				</tr>
			<?php
		}

	// STEP 4, source group same as destination
		if($destinationGroupID && $destinationGroupID==$sourceGroupID){
			?>
			<tr>
				<td class="tdHeader" colspan="2">
					<h3>STEP 4:</h3>
					The destination member will be the new owner of the data records of the source
					member.
					</td>
				</tr>
			<tr>
				<td class="tdFormCaption">
					Destination member
					</td>
				<td class="tdCell">
					<?php
						echo htmlSQLSelect("destinationMemberID", "select lcase(memberID), lcase(memberID) from membership_users where groupID='$destinationGroupID' and lcase(memberID)!='$sourceMemberID' order by memberID", $destinationMemberID);
					?>
					</td>
				</tr>
			<tr>
				<td class="tdFormFooter" colspan="2" align="right">
					<input type="submit" name="beginTransfer" value="Begin Transfer" onClick="return jsConfirmTransfer();">
					</td>
				</tr>
			<?php

	// STEP 4, source group not same as destination
		}elseif($destinationGroupID){
			?>
			<tr>
				<td class="tdHeader" colspan="2">
					<h3>STEP 4:</h3>
					<?php
						$noMove=($sourceGroupID==sqlValue("select groupID from membership_groups where name='".$adminConfig['anonymousGroup']."'"));
						if(!$noMove){
							?>
							You could either move records from the source member(s) to a member in the
							destination group, or move the source member(s), together with
							their data records to the destination group.
							<?php
						}
					?>
					</td>
				</tr>
			<?php
				if(sqlValue("select count(1) from membership_users where groupID='$destinationGroupID'")>0){
					$destinationHasMembers=TRUE;
					?>
					<tr>
						<td class="tdCell" colspan="2">
							<input type="radio" name="moveMembers" id="dontMoveMembers" value="0" <?php echo ($moveMembers ? "" : "checked"); ?>>
							Move data records to this member:
							<?php
								echo htmlSQLSelect("destinationMemberID", "select lcase(memberID), lcase(memberID) from membership_users where groupID='$destinationGroupID' order by memberID", $destinationMemberID);
							?>
							</td>
						</tr>
					<?php
				}else{
					$destinationHasMembers=FALSE;
				}

				if(!$noMove){
					?>
					<tr>
						<td class="tdCell" colspan="2">
							<input type="radio" name="moveMembers" id="moveMembers" value="1" <?php echo ($moveMembers || !$destinationHasMembers ? "checked" : ""); ?>>
							Move source member(s) and all his/their data records to the '<?php echo sqlValue("select name from membership_groups where groupID='$destinationGroupID'"); ?>' group.
							</td>
						</tr>
					<?php
				}
			?>
			<tr>
				<td class="tdFormFooter" colspan="2" align="right">
					<input type="submit" name="beginTransfer" value="Begin Transfer" onClick="return jsConfirmTransfer();">
					</td>
				</tr>
			<?php
		}
	?>
			</table>
		</form>


	<?php


?>

<?php
	include("$currDir/incFooter.php");
?>