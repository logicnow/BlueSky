<?php
	$currDir=dirname(__FILE__);
	require("$currDir/incCommon.php");

	// validate input
	$groupID=intval($_GET['groupID']);

	// make sure group has no members
	if(sqlValue("select count(1) from membership_users where groupID='$groupID'")){
		errorMsg("Can't delete this group. Please remove members first.");
		include("$currDir/incFooter.php");
	}

	// make sure group has no records
	if(sqlValue("select count(1) from membership_userrecords where groupID='$groupID'")){
		errorMsg("Can't delete this group. Please transfer its data records to another group first..");
		include("$currDir/incFooter.php");
	}


	sql("delete from membership_groups where groupID='$groupID'", $eo);
	sql("delete from membership_grouppermissions where groupID='$groupID'", $eo);

	if($_SERVER['HTTP_REFERER']){
		redirect($_SERVER['HTTP_REFERER'], TRUE);
	}else{
		redirect("admin/pageViewGroups.php");
	}

?>