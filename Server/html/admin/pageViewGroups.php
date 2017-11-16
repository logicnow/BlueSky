<?php
	$currDir=dirname(__FILE__);
	require("$currDir/incCommon.php");
	include("$currDir/incHeader.php");

	if($_GET['searchGroups']!=""){
		$searchSQL=makeSafe($_GET['searchGroups']);
		$searchHTML=htmlspecialchars($_GET['searchGroups']);
		$where="where name like '%$searchSQL%' or description like '%$searchSQL%'";
	}else{
		$searchSQL='';
		$searchHTML='';
		$where="";
	}

	$numGroups=sqlValue("select count(1) from membership_groups $where");
	if(!$numGroups && $searchSQL!=''){
		echo "<div class=\"status\">No matching results found.</div>";
		$noResults=TRUE;
		$page=1;
	}else{
		$noResults=FALSE;
	}

	$page=intval($_GET['page']);
	if($page<1){
		$page=1;
	}elseif($page>ceil($numGroups/$adminConfig['groupsPerPage']) && !$noResults){
		redirect("admin/pageViewGroups.php?page=".ceil($numGroups/$adminConfig['groupsPerPage']));
	}

	$start=($page-1)*$adminConfig['groupsPerPage'];

?>
<div class="page-header"><h1>Groups</h1></div>

<table class="table table-striped">
	<tr>
		<td colspan="5" align="center">
			<form method="get" action="pageViewGroups.php">
				<input type="hidden" name="page" value="1">
				Search groups
				<input class="formTextBox" type="text" name="searchGroups" value="<?php echo $searchHTML; ?>" size="20">
				<input type="submit" value="Find">
				<input type="button" value="Reset" onClick="window.location='pageViewGroups.php';">
				</form>
			</td>
		</tr>
	<tr>
		<td class="tdHeader">&nbsp;</td>
		<td class="tdHeader"><div class="ColCaption">Group</div></td>
		<td class="tdHeader"><div class="ColCaption">Description</div></td>
		<td class="tdHeader"><div class="ColCaption">Members count</div></td>
		<td class="tdHeader">&nbsp;</td>
		</tr>
<?php

	$res=sql("select groupID, name, description from membership_groups $where limit $start, ".$adminConfig['groupsPerPage'], $eo);
	while($row=db_fetch_row($res)){
		$groupMembersCount=sqlValue("select count(1) from membership_users where groupID='$row[0]'");
		?>
		<tr>
			<td class="tdCaptionCell" align="left">
				<a href="pageEditGroup.php?groupID=<?php echo $row[0]; ?>"><img border="0" src="images/edit_icon.gif" alt="Edit group" title="Edit group"></a>
				<?php
					if(!$groupMembersCount){
						?>
						<a href="pageDeleteGroup.php?groupID=<?php echo $row[0]; ?>" onClick="return confirm('Are you sure you want to completely delete this group?');"><img border="0" src="images/delete_icon.gif" alt="Delete group" title="Delete group"></a>
						<?php
					}else{
						echo "&nbsp; &nbsp;";
					}
				?>
				</td>
			<td class="tdCell" align="left"><a href="pageEditGroup.php?groupID=<?php echo $row[0]; ?>"><?php echo $row[1]; ?></a></td>
			<td class="tdCell" align="left"><?php echo thisOr($row[2]); ?></td>
			<td align="right" class="tdCell">
				<?php echo $groupMembersCount; ?>
				</td>
			<td class="tdCaptionCell" align="left">
				<a href="pageEditMember.php?groupID=<?php echo $row[0]; ?>"><img border="0" src="images/add_icon.gif" alt="Add new member" title="Add new member"></a>
				<a href="pageViewRecords.php?groupID=<?php echo $row[0]; ?>"><img border="0" src="images/data_icon.gif" alt="View group records" title="View group records"></a>
				<?php if($groupMembersCount){ ?>
				<a href="pageViewMembers.php?groupID=<?php echo $row[0]; ?>"><img border="0" src="images/members_icon.gif" alt="View group members" title="View group members"></a>
				<a href="pageMail.php?groupID=<?php echo $row[0]; ?>"><img border="0" src="images/mail_icon.gif" alt="Send message to group" title="Send message to group"></a>
				<?php } ?>
				</td>
			</tr>
		<?php
	}
	?>
	<tr>
		<td colspan="5">
			<table width="100%" cellspacing="0">
				<tr>
				<td align="left" class="tdFooter">
					<input type="button" onClick="window.location='pageViewGroups.php?searchGroups=<?php echo $searchHTML; ?>&page=<?php echo ($page>1 ? $page-1 : 1); ?>';" value="Previous">
					</td>
				<td align="center" class="tdFooter">
					<?php echo "Displaying groups ".($start+1)." to ".($start+db_num_rows($res))." of $numGroups"; ?>
					</td>
				<td align="right" class="tdFooter">
					<input type="button" onClick="window.location='pageViewGroups.php?searchGroups=<?php echo $searchHTML; ?>&page=<?php echo ($page<ceil($numGroups/$adminConfig['groupsPerPage']) ? $page+1 : ceil($numGroups/$adminConfig['groupsPerPage'])); ?>';" value="Next">
					</td>
			</tr></table></td>
		</tr>
	<tr>
		<td colspan="5">
			<table class="table">
				<tr>
					<td colspan="2"><br><b>Key:</b></td>
					</tr>
				<tr>
					<td><img src="images/edit_icon.gif"> Edit group details and permissions.</td>
					<td><img src="images/delete_icon.gif"> Delete group.</td>
					</tr>
				<tr>
					<td><img src="images/add_icon.gif"> Add a new member to group.</td>
					<td><img src="images/data_icon.gif"> View all data records entered by the group's members.</td>
					</tr>
				<tr>
					<td><img src="images/members_icon.gif"> List all members of a group.</td>
					<td><img src="images/mail_icon.gif"> Send an email message to all members of a group.</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

<?php
	include("$currDir/incFooter.php");
?>