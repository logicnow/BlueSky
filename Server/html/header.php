<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js"> <!--<![endif]-->
	<head>
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<meta name="description" content="">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">

		<title><?php echo ucwords('Bluesky'); ?> | <?php echo $x->TableTitle; ?></title>
		<link id="browser_favicon" rel="shortcut icon" href="resources/images/appgini-icon.png">

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
		<script src="resources/jquery/js/jquery-1.11.2.min.js"></script>
		<script>var $j = jQuery.noConflict();</script>
		<script src="resources/initializr/js/vendor/bootstrap.min.js"></script>
		<script src="resources/lightbox/js/prototype.js"></script>
		<script src="resources/lightbox/js/scriptaculous.js?load=effects,builder,dragdrop,controls"></script>
		<script src="resources/lightbox/js/lightbox.js"></script>
		<script src="resources/select2/select2.min.js"></script>
		<script src="resources/timepicker/bootstrap-timepicker.min.js"></script>
		<script src="common.js.php"></script>
		<?php if(isset($x->TableName) && is_file(dirname(__FILE__) . "/hooks/{$x->TableName}-tv.js")){ ?>
			<script src="hooks/<?php echo $x->TableName; ?>-tv.js"></script>
		<?php } ?>

	</head>
	<body>
		<div class="container">
			<?php if(!$_REQUEST['Embedded']){ ?>
				<?php echo htmlUserBar(); ?>
				<div style="height: 70px;" class="hidden-print"></div>
			<?php } ?>

			<!-- process notifications -->
			<div style="height: 60px; margin: -15px 0 -45px;">
				<?php if(function_exists('showNotifications')) echo showNotifications(); ?>
			</div>

			<?php if(is_file(dirname(__FILE__) . '/hooks/header-extras.php')){ include(dirname(__FILE__).'/hooks/header-extras.php'); } ?>
			<!-- Add header template below here .. -->

