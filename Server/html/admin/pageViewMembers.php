<?php
	$currDir=dirname(__FILE__);
	require("$currDir/incCommon.php");
	include("$currDir/incHeader.php");

	// process search
	if($_GET['searchMembers']!=""){
		$searchSQL=makeSafe($_GET['searchMembers']);
		$searchHTML=htmlspecialchars($_GET['searchMembers']);
		$searchField=intval($_GET['searchField']);
		$searchFieldName=array_search($searchField, array('m.memberID'=>1, 'g.name'=>2, 'm.email'=>3, 'm.custom1'=>4, 'm.custom2'=>5, 'm.custom3'=>6, 'm.custom4'=>7, 'm.comments'=>8));
		if(!$searchFieldName){ // = search all fields
			$where="where (m.memberID like '%$searchSQL%' or g.name like '%$searchSQL%' or m.email like '%$searchSQL%' or m.custom1 like '%$searchSQL%' or m.custom2 like '%$searchSQL%' or m.custom3 like '%$searchSQL%' or m.custom4 like '%$searchSQL%' or m.comments like '%$searchSQL%')";
		}else{ // = search a specific field
			$where="where ($searchFieldName like '%$searchSQL%')";
		}
	}else{
		$searchSQL='';
		$searchHTML='';
		$searchField=0;
		$searchFieldName='';
		$where="";
	}

	// process groupID filter
	$groupID=intval($_GET['groupID']);
	if($groupID){
		if($where!=''){
			$where.=" and (g.groupID='$groupID')";
		}else{
			$where="where (g.groupID='$groupID')";
		}
	}

	// process status filter
	$status=intval($_GET['status']); // 1=waiting approval, 2=active, 3=banned, 0=any
	if($status){
		switch($status){
			case 1:
				$statusCond="(m.isApproved=0)";
				break;
			case 2:
				$statusCond="(m.isApproved=1 and m.isBanned=0)";
				break;
			case 3:
				$statusCond="(m.isApproved=1 and m.isBanned=1)";
				break;
			default:
				$statusCond="";
		}
		if($where!='' && $statusCond!=''){
			$where.=" and $statusCond";
		}else{
			$where="where $statusCond";
		}
	}

# NEXT: Add a dateAfter and dateBefore filter [??]

	$numMembers=sqlValue("select count(1) from membership_users m left join membership_groups g on m.groupID=g.groupID $where");
	if(!$numMembers){
		echo "<div class=\"status\">No matching results found.</div>";
		$noResults=TRUE;
		$page=1;
	}else{
		$noResults=FALSE;
	}

	$page=intval($_GET['page']);
	if($page<1){
		$page=1;
	}elseif($page>ceil($numMembers/$adminConfig['membersPerPage']) && !$noResults){
		redirect("admin/pageViewMembers.php?page=".ceil($numMembers/$adminConfig['membersPerPage']));
	}

	$start=($page-1)*$adminConfig['membersPerPage'];

?>
<div class="page-header"><h1>Members</h1></div>

<table class="table table-striped">
	<tr>
		<td colspan="10" align="center">
			<form method="get" action="pageViewMembers.php">
				<table class="table table-striped">
					<tr>
						<td valign="top" align="center">
							<input type="hidden" name="page" value="1">
							Search members
							<input class="formTextBox" type="text" name="searchMembers" value="<?php echo $searchHTML; ?>" size="20"> in
							<?php
								$arrFields=array(0, 1, 2, 3, 4, 5, 6, 7, 8);
								$arrFieldCaptions=array('All fields', 'Username', 'Group', 'Email', $adminConfig['custom1'], $adminConfig['custom2'], $adminConfig['custom3'], $adminConfig['custom4'], 'Comments');
								echo htmlSelect('searchField', $arrFields, $arrFieldCaptions, $searchField);
							?>
							</td>
						<td valign="bottom" rowspan="2">
							<input type="submit" value="Find">
							<input type="button" value="Reset" onClick="window.location='pageViewMembers.php';">
							</td>
						</tr>
					<tr>
						<td align="center">
							Group
							<?php
								echo htmlSQLSelect("groupID", "select groupID, name from membership_groups order by name", $groupID);
							?>
							&nbsp; &nbsp; &nbsp; 
							Status
							<?php
								$arrFields=array(0, 1, 2, 3);
								$arrFieldCaptions=array('Any', 'Waiting approval', 'Active', 'Banned');
								echo htmlSelect("status", $arrFields, $arrFieldCaptions, $status);
							?>
							</td>
						</tr>
					</table>
				</form>
			</td>
		</tr>
	<tr>
		<td class="tdHeader">&nbsp;</td>
		<td class="tdHeader"><div class="ColCaption">Username</div></td>
		<td class="tdHeader"><div class="ColCaption">Group</div></td>
		<td class="tdHeader"><div class="ColCaption">Sign up date</div></td>
		<td class="tdHeader"><div class="ColCaption"><?php echo $adminConfig['custom1']; ?></div></td>
		<td class="tdHeader"><div class="ColCaption"><?php echo $adminConfig['custom2']; ?></div></td>
		<td class="tdHeader"><div class="ColCaption"><?php echo $adminConfig['custom3']; ?></div></td>
		<td class="tdHeader"><div class="ColCaption"><?php echo $adminConfig['custom4']; ?></div></td>
		<td class="tdHeader"><div class="ColCaption">Status</div></td>
		<td class="tdHeader">&nbsp;</td>
		</tr>
<?php

	$res=sql("select lcase(m.memberID), g.name, DATE_FORMAT(m.signupDate, '".$adminConfig['MySQLDateFormat']."'), m.custom1, m.custom2, m.custom3, m.custom4, m.isBanned, m.isApproved from membership_users m left join membership_groups g on m.groupID=g.groupID $where order by m.signupDate limit $start, ".$adminConfig['membersPerPage'], $eo);
	while($row=db_fetch_row($res)){
		?>
		<tr>
			<td class="tdCaptionCell" align="left">
				<a href="pageEditMember.php?memberID=<?php echo $row[0]; ?>"><img border="0" src="images/edit_icon.gif" alt="Edit member" title="Edit member"></a>
				<a href="pageDeleteMember.php?memberID=<?php echo $row[0]; ?>" onClick="return confirm('Are you sure you want to delete user \'<?php echo $row[0]; ?>\'?');"><img border="0" src="images/delete_icon.gif" alt="Delete member" title="Delete member"></a>
				</td>
			<td class="tdCell" align="left"><?php echo thisOr($row[0]); ?></td>
			<td class="tdCell" align="left"><?php echo thisOr($row[1]); ?></td>
			<td class="tdCell" align="left"><?php echo thisOr($row[2]); ?></td>
			<td class="tdCell" align="left"><?php echo thisOr($row[3]); ?></td>
			<td class="tdCell" align="left"><?php echo thisOr($row[4]); ?></td>
			<td class="tdCell" align="left"><?php echo thisOr($row[5]); ?></td>
			<td class="tdCell" align="left"><?php echo thisOr($row[6]); ?></td>
			<td class="tdCell" align="left">
				<?php echo (($row[7] && $row[8]) ? "Banned" : ($row[8] ? "Active" : "Waiting approval")); ?>
				</td>
			<td class="tdCaptionCell" align="left">
				<?php
					if(!$row[8]){ // if member is not approved, display approve link
						?><a href="pageChangeMemberStatus.php?memberID=<?php echo $row[0]; ?>&approve=1"><img border="0" src="images/approve_icon.gif" alt="Approve this member" title="Approve this member"></a><?php
					}else{
						if($row[7]){ // if member is banned, display unban link
							?><a href="pageChangeMemberStatus.php?memberID=<?php echo $row[0]; ?>&unban=1"><img border="0" src="images/approve_icon.gif" alt="Unban this member" title="Unban this member"></a><?php
						}else{ // if member is not banned, display ban link
							?><a href="pageChangeMemberStatus.php?memberID=<?php echo $row[0]; ?>&ban=1"><img border="0" src="images/stop_icon.gif" alt="Ban this member" title="Ban this member"></a><?php
						}
					}
				?>
				<a href="pageViewRecords.php?memberID=<?php echo $row[0]; ?>"><img border="0" src="images/data_icon.gif" alt="View member's records" title="View member's records"></a>
				<?php if($adminConfig['anonymousMember']!=$row[0]){ ?>
				<a href="pageMail.php?memberID=<?php echo $row[0]; ?>"><img border="0" src="images/mail_icon.gif" alt="Send message to member" title="Send message to member"></a>
				<?php } ?>
				</td>
			</tr>
		<?php
	}
	?>
	<tr>
		<td colspan="10">
			<table width="100%" cellspacing="0">
				<tr>
				<td align="left" class="tdFooter">
					<input type="button" onClick="window.location='pageViewMembers.php?searchMembers=<?php echo $searchHTML; ?>&groupID=<?php echo $groupID; ?>&status=<?php echo $status; ?>&searchField=<?php echo $searchField; ?>&page=<?php echo ($page>1 ? $page-1 : 1); ?>';" value="Previous">
					</td>
				<td align="center" class="tdFooter">
					<?php echo "Displaying members ".($start+1)." to ".($start+db_num_rows($res))." of $numMembers"; ?>
					</td>
				<td align="right" class="tdFooter">
					<input type="button" onClick="window.location='pageViewMembers.php?searchMembers=<?php echo $searchHTML; ?>&groupID=<?php echo $groupID; ?>&status=<?php echo $status; ?>&searchField=<?php echo $searchField; ?>&page=<?php echo ($page<ceil($numMembers/$adminConfig['membersPerPage']) ? $page+1 : ceil($numMembers/$adminConfig['membersPerPage'])); ?>';" value="Next">
					</td>
			</tr></table></td>
		</tr>
	<tr>
		<td colspan="10">
			</td>
		</tr>
	<tr>
		<td colspan="10">
			<table class="table">
				<tr>
					<td colspan="2"><br><b>Key:</b></td>
					</tr>
				<tr>
					<td><img src="images/edit_icon.gif"> Edit member details.</td>
					<td><img src="images/delete_icon.gif"> Delete member.</td>
					</tr>
				<tr>
					<td><img src="images/approve_icon.gif"> Activate new/banned member.</td>
					<td><img src="images/stop_icon.gif"> Ban (suspend) member.</td>
					</tr>
				<tr>
					<td><img src="images/data_icon.gif"> View all data records entered by member.</td>
					<td><img src="images/mail_icon.gif"> Send an email message to member.</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

<?php
	include("$currDir/incFooter.php");
?>