<?php
	$currDir=dirname(__FILE__);
	include("$currDir/defaultLang.php");
	include("$currDir/language.php");
	include("$currDir/lib.php");
	
	$selected_ids=$_REQUEST['selected_ids'];
	$each_id = explode(",", $selected_ids);
	
	$selfdestruct=$_REQUEST['selfdestruct'];
	
foreach ($each_id as $id) {
	$query="UPDATE BlueSky.computers SET selfdestruct = '$selfdestruct' WHERE computers.id =$id;";
    sql($query,$eo);

}
	
?>
<html>
<head>
<link rel="stylesheet" href="resources/initializr/css/bootstrap.css">
		<!--[if gt IE 8]><!-->
			<link rel="stylesheet" href="resources/initializr/css/bootstrap-theme.css">
		<!--<![endif]-->
		<link rel="stylesheet" href="resources/lightbox/css/lightbox.css" media="screen">
		<link rel="stylesheet" href="resources/select2/select2.css" media="screen">
		<link rel="stylesheet" href="resources/timepicker/bootstrap-timepicker.min.css" media="screen">
		<link rel="stylesheet" href="dynamic.css.php">

		<!--[if lt IE 9]>
			<script src="resources/initializr/js/vendor/modernizr-2.6.2-respond-1.1.0.min.js"></script>
		<![endif]-->
		<script src="resources/jquery/js/jquery-1.10.1.min.js"></script>
		<script>var $j = jQuery.noConflict();</script>
		<script src="resources/initializr/js/vendor/bootstrap.min.js"></script>
		<script src="resources/lightbox/js/prototype.js"></script>
		<script src="resources/lightbox/js/scriptaculous.js?load=effects,builder,dragdrop,controls"></script>
		<script src="resources/lightbox/js/lightbox.js"></script>
		<script src="resources/select2/select2.min.js"></script>
		<script src="resources/timepicker/bootstrap-timepicker.min.js"></script>
		<script src="common.js.php"></script>

</head>
	<div class="container">
		
						<div style="height: 70px;" class="hidden-print"></div>
			
			<!-- process notifications -->
			
			<!-- Add header template below here .. -->

<div class="panel panel-success"><div class="panel-heading"><h3 class="panel-title">All Records Updated Successfully</h3></div><div class="panel-body"><p class="text-danger">All Records Updated Successfully

</p><div class="text-center">
<a class="btn btn-success btn-lg vspacer-lg" onclick="window.parent.jQuery('.modal').modal('hide');"> Ok </a></div></div></div>			<!-- Add footer template above here -->
	
				
			
		</div> <!-- /div class="container" -->
	</body>
</html>