<?php
	$currDir=dirname(__FILE__);
	require("$currDir/incCommon.php");

	// get groupID of anonymous group
	$anonGroupID=sqlValue("select groupID from membership_groups where name='".$adminConfig['anonymousGroup']."'");

	// request to save changes?
	if($_POST['saveChanges']!=''){
		// validate data
		$name=makeSafe($_POST['name']);
		$description=makeSafe($_POST['description']);
		switch($_POST['visitorSignup']){
			case 0:
				$allowSignup=0;
				$needsApproval=1;
				break;
			case 2:
				$allowSignup=1;
				$needsApproval=0;
				break;
			default:
				$allowSignup=1;
				$needsApproval=1;
		}
		###############################
		$computers_insert=checkPermissionVal('computers_insert');
		$computers_view=checkPermissionVal('computers_view');
		$computers_edit=checkPermissionVal('computers_edit');
		$computers_delete=checkPermissionVal('computers_delete');
		###############################
		$global_insert=checkPermissionVal('global_insert');
		$global_view=checkPermissionVal('global_view');
		$global_edit=checkPermissionVal('global_edit');
		$global_delete=checkPermissionVal('global_delete');
		###############################
		$connections_insert=checkPermissionVal('connections_insert');
		$connections_view=checkPermissionVal('connections_view');
		$connections_edit=checkPermissionVal('connections_edit');
		$connections_delete=checkPermissionVal('connections_delete');
		###############################

		// new group or old?
		if($_POST['groupID']==''){ // new group
			// make sure group name is unique
			if(sqlValue("select count(1) from membership_groups where name='$name'")){
				echo "<div class=\"alert alert-danger\">Error: Group name already exists. You must choose a unique group name.</div>";
				include("$currDir/incFooter.php");
			}

			// add group
			sql("insert into membership_groups set name='$name', description='$description', allowSignup='$allowSignup', needsApproval='$needsApproval'", $eo);

			// get new groupID
			$groupID=db_insert_id(db_link());

		}else{ // old group
			// validate groupID
			$groupID=intval($_POST['groupID']);

			if($groupID==$anonGroupID){
				$name=$adminConfig['anonymousGroup'];
				$allowSignup=0;
				$needsApproval=0;
			}

			// make sure group name is unique
			if(sqlValue("select count(1) from membership_groups where name='$name' and groupID!='$groupID'")){
				echo "<div class=\"alert alert-danger\">Error: Group name already exists. You must choose a unique group name.</div>";
				include("$currDir/incFooter.php");
			}

			// update group
			sql("update membership_groups set name='$name', description='$description', allowSignup='$allowSignup', needsApproval='$needsApproval' where groupID='$groupID'", $eo);

			// reset then add group permissions
			sql("delete from membership_grouppermissions where groupID='$groupID' and tableName='computers'", $eo);
			sql("delete from membership_grouppermissions where groupID='$groupID' and tableName='global'", $eo);
			sql("delete from membership_grouppermissions where groupID='$groupID' and tableName='connections'", $eo);
		}

		// add group permissions
		if($groupID){
			// table 'computers'
			sql("insert into membership_grouppermissions set groupID='$groupID', tableName='computers', allowInsert='$computers_insert', allowView='$computers_view', allowEdit='$computers_edit', allowDelete='$computers_delete'", $eo);
			// table 'global'
			sql("insert into membership_grouppermissions set groupID='$groupID', tableName='global', allowInsert='$global_insert', allowView='$global_view', allowEdit='$global_edit', allowDelete='$global_delete'", $eo);
			// table 'connections'
			sql("insert into membership_grouppermissions set groupID='$groupID', tableName='connections', allowInsert='$connections_insert', allowView='$connections_view', allowEdit='$connections_edit', allowDelete='$connections_delete'", $eo);
		}

		// redirect to group editing page
		redirect("admin/pageEditGroup.php?groupID=$groupID");

	}elseif($_GET['groupID']!=''){
		// we have an edit request for a group
		$groupID=intval($_GET['groupID']);
	}

	include("$currDir/incHeader.php");

	if($groupID!=''){
		// fetch group data to fill in the form below
		$res=sql("select * from membership_groups where groupID='$groupID'", $eo);
		if($row=db_fetch_assoc($res)){
			// get group data
			$name=$row['name'];
			$description=$row['description'];
			$visitorSignup=($row['allowSignup']==1 && $row['needsApproval']==1 ? 1 : ($row['allowSignup']==1 ? 2 : 0));

			// get group permissions for each table
			$res=sql("select * from membership_grouppermissions where groupID='$groupID'", $eo);
			while($row=db_fetch_assoc($res)){
				$tableName=$row['tableName'];
				$vIns=$tableName."_insert";
				$vUpd=$tableName."_edit";
				$vDel=$tableName."_delete";
				$vVue=$tableName."_view";
				$$vIns=$row['allowInsert'];
				$$vUpd=$row['allowEdit'];
				$$vDel=$row['allowDelete'];
				$$vVue=$row['allowView'];
			}
		}else{
			// no such group exists
			echo "<div class=\"alert alert-danger\">Error: Group not found!</div>";
			$groupID=0;
		}
	}
?>
<div class="page-header"><h1><?php echo ($groupID ? "Edit Group '$name'" : "Add New Group"); ?></h1></div>
<?php if($anonGroupID==$groupID){ ?>
	<div class="alert alert-warning">Attention! This is the anonymous group.</div>
<?php } ?>
<input type="checkbox" id="showToolTips" value="1" checked><label for="showToolTips">Show tool tips as mouse moves over options</label>
<form method="post" action="pageEditGroup.php">
	<input type="hidden" name="groupID" value="<?php echo $groupID; ?>">
	<div class="table-responsive"><table class="table table-striped">
		<tr>
			<td align="right" class="tdFormCaption" valign="top">
				<div class="formFieldCaption">Group name</div>
				</td>
			<td align="left" class="tdFormInput">
				<input type="text" name="name" <?php echo ($anonGroupID==$groupID ? "readonly" : ""); ?> value="<?php echo $name; ?>" size="20" class="formTextBox">
				<br>
				<?php if($anonGroupID==$groupID){ ?>
					The name of the anonymous group is read-only here.
				<?php }else{ ?>
					If you name the group '<?php echo $adminConfig['anonymousGroup']; ?>', it will be considered the anonymous group<br>
					that defines the permissions of guest visitors that do not log into the system.
				<?php } ?>
				</td>
			</tr>
		<tr>
			<td align="right" valign="top" class="tdFormCaption">
				<div class="formFieldCaption">Description</div>
				</td>
			<td align="left" class="tdFormInput">
				<textarea name="description" cols="50" rows="5" class="formTextBox"><?php echo $description; ?></textarea>
				</td>
			</tr>
		<?php if($anonGroupID!=$groupID){ ?>
		<tr>
			<td align="right" valign="top" class="tdFormCaption">
				<div class="formFieldCaption">Allow visitors to sign up?</div>
				</td>
			<td align="left" class="tdFormInput">
				<?php
					echo htmlRadioGroup(
						"visitorSignup",
						array(0, 1, 2),
						array(
							"No. Only the admin can add users.",
							"Yes, and the admin must approve them.",
							"Yes, and automatically approve them."
						),
						($groupID ? $visitorSignup : $adminConfig['defaultSignUp'])
					);
				?>
				</td>
			</tr>
		<?php } ?>
		<tr>
			<td colspan="2" align="right" class="tdFormFooter">
				<input type="submit" name="saveChanges" value="Save changes">
				</td>
			</tr>
		<tr>
			<td colspan="2" class="tdFormHeader">
				<table class="table table-striped">
					<tr>
						<td class="tdFormHeader" colspan="5"><h2>Table permissions for this group</h2></td>
						</tr>
					<?php
						// permissions arrays common to the radio groups below
						$arrPermVal=array(0, 1, 2, 3);
						$arrPermText=array("No", "Owner", "Group", "All");
					?>
					<tr>
						<td class="tdHeader"><div class="ColCaption">Table</div></td>
						<td class="tdHeader"><div class="ColCaption">Insert</div></td>
						<td class="tdHeader"><div class="ColCaption">View</div></td>
						<td class="tdHeader"><div class="ColCaption">Edit</div></td>
						<td class="tdHeader"><div class="ColCaption">Delete</div></td>
						</tr>
				<!-- computers table -->
					<tr>
						<td class="tdCaptionCell" valign="top">BlueSky Admin</td>
						<td class="tdCell" valign="top">
							<input onMouseOver="stm(computers_addTip, toolTipStyle);" onMouseOut="htm();" type="checkbox" name="computers_insert" value="1" <?php echo ($computers_insert ? "checked class=\"highlight\"" : ""); ?>>
							</td>
						<td class="tdCell">
							<?php
								echo htmlRadioGroup("computers_view", $arrPermVal, $arrPermText, $computers_view, "highlight");
							?>
							</td>
						<td class="tdCell">
							<?php
								echo htmlRadioGroup("computers_edit", $arrPermVal, $arrPermText, $computers_edit, "highlight");
							?>
							</td>
						<td class="tdCell">
							<?php
								echo htmlRadioGroup("computers_delete", $arrPermVal, $arrPermText, $computers_delete, "highlight");
							?>
							</td>
						</tr>
				<!-- global table -->
					<tr>
						<td class="tdCaptionCell" valign="top">Global Settings</td>
						<td class="tdCell" valign="top">
							<input onMouseOver="stm(global_addTip, toolTipStyle);" onMouseOut="htm();" type="checkbox" name="global_insert" value="1" <?php echo ($global_insert ? "checked class=\"highlight\"" : ""); ?>>
							</td>
						<td class="tdCell">
							<?php
								echo htmlRadioGroup("global_view", $arrPermVal, $arrPermText, $global_view, "highlight");
							?>
							</td>
						<td class="tdCell">
							<?php
								echo htmlRadioGroup("global_edit", $arrPermVal, $arrPermText, $global_edit, "highlight");
							?>
							</td>
						<td class="tdCell">
							<?php
								echo htmlRadioGroup("global_delete", $arrPermVal, $arrPermText, $global_delete, "highlight");
							?>
							</td>
						</tr>
				<!-- connections table -->
					<tr>
						<td class="tdCaptionCell" valign="top">Connection Log</td>
						<td class="tdCell" valign="top">
							<input onMouseOver="stm(connections_addTip, toolTipStyle);" onMouseOut="htm();" type="checkbox" name="connections_insert" value="1" <?php echo ($connections_insert ? "checked class=\"highlight\"" : ""); ?>>
							</td>
						<td class="tdCell">
							<?php
								echo htmlRadioGroup("connections_view", $arrPermVal, $arrPermText, $connections_view, "highlight");
							?>
							</td>
						<td class="tdCell">
							<?php
								echo htmlRadioGroup("connections_edit", $arrPermVal, $arrPermText, $connections_edit, "highlight");
							?>
							</td>
						<td class="tdCell">
							<?php
								echo htmlRadioGroup("connections_delete", $arrPermVal, $arrPermText, $connections_delete, "highlight");
							?>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		<tr>
			<td colspan="2" align="right" class="tdFormFooter">
				<input type="submit" name="saveChanges" value="Save changes">
				</td>
			</tr>
		</table></div>
</form>

	<script>
		$j(function(){
			var highlight_selections = function(){
				$j('input[type=radio]:checked').next().addClass('text-primary');
				$j('input[type=radio]:not(:checked)').next().removeClass('text-primary');
			}

			$j('input[type=radio]').change(function(){ highlight_selections(); });
			highlight_selections();
		});
	</script>


<?php
	include("$currDir/incFooter.php");
?>