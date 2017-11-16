<?php ob_start(); ?>
<center>

<?php

	$currDir=dirname(__FILE__);
	include("$currDir/defaultLang.php");
	include("$currDir/language.php");
	include("$currDir/lib.php");

	$username = is_allowed_username($_GET['memberID']);
	if($username){
		echo "<b>".str_replace("<MemberID>", $username, $Translation['user available'])."</b><!-- AVAILABLE -->";
	}else{
		echo "<b>".str_replace("<MemberID>", strip_tags($_GET['memberID']), $Translation['username invalid'])."</b><!-- NOT AVAILABLE -->";
	}
?>

<br><br><input type="button" value="Close" onClick="window.close();">
</center>
