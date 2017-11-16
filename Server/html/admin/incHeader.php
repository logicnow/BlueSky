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
		<title>Membership Management</title>

		<link id="browser_favicon" rel="shortcut icon" href="../resources/table_icons/administrator.png">

		<link rel="stylesheet" href="../resources/initializr/css/bootstrap.css">
		<!--[if gt IE 8]><!-->
			<link rel="stylesheet" href="../resources/initializr/css/bootstrap-theme.css">
		<!--<![endif]-->
		<link rel="stylesheet" href="../dynamic.css.php">

		<!--[if lt IE 9]>
			<script src="../resources/initializr/js/vendor/modernizr-2.6.2-respond-1.1.0.min.js"></script>
		<![endif]-->
		<script src="../resources/jquery/js/jquery-1.11.2.min.js"></script>
		<script>var $j = jQuery.noConflict();</script>
		<script src="toolTips.js"></script>
		<script src="../resources/initializr/js/vendor/bootstrap.min.js"></script>
		<script src="../resources/lightbox/js/prototype.js"></script>
		<script src="../resources/lightbox/js/scriptaculous.js?load=effects,builder,dragdrop,controls"></script>
		<script>

			// VALIDATION FUNCTIONS FOR VARIOUS PAGES

			function jsValidateMember(){
				var p1=document.getElementById('password').value;
				var p2=document.getElementById('confirmPassword').value;
				if(p1=='' || p1==p2){
					return true;
				}else{
					modal_window({message: '<div class="alert alert-danger">Password doesn\'t match.</div>', title: "Error" });
					return false;
				}
			}

			function jsValidateEmail(address){
				var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
				if(reg.test(address) == false){
					modal_window({ message: '<div class="alert alert-danger">Invalid Email Address</div>', title: "Error" });
					return false;
				}else{
					return true;
				}
			}

			function jsShowWait(){
				return window.confirm("Sending mails might take some time. Please don't close this page until you see the 'Done' message.");
			}

			function jsValidateAdminSettings(){
				var p1=document.getElementById('adminPassword').value;
				var p2=document.getElementById('confirmPassword').value;
				if(p1=='' || p1==p2){
					return jsValidateEmail(document.getElementById('senderEmail').value);
				}else{
					modal_window({ message: '<div class="alert alert-error">Password doesn\'t match.</div>', title: "Error" });
					return false;
				}
			}

			function jsConfirmTransfer(){
				var sg=document.getElementById('sourceGroupID').options[document.getElementById('sourceGroupID').selectedIndex].text;
				var sm=document.getElementById('sourceMemberID').value;
				var dg=document.getElementById('destinationGroupID').options[document.getElementById('destinationGroupID').selectedIndex].text;
				if(document.getElementById('destinationMemberID')){
					var dm=document.getElementById('destinationMemberID').value;
				}
				if(document.getElementById('dontMoveMembers')){
					var dmm=document.getElementById('dontMoveMembers').checked;
				}
				if(document.getElementById('moveMembers')){
					var mm=document.getElementById('moveMembers').checked;
				}

				//confirm('sg='+sg+'\n'+'sm='+sm+'\n'+'dg='+dg+'\n'+'dm='+dm+'\n'+'mm='+mm+'\n'+'dmm='+dmm+'\n');

				if(dmm && !dm){
					modal_window({ message: '<div>Please complete step 4 by selecting the member you want to transfer records to.</div>', title: "Info", close: function(){ jQuery('#destinationMemberID').focus(); } });
					return false;
				}

				if(mm && sm!='-1'){
					return window.confirm('Are you sure you want to move member \''+sm+'\' and his data from group \''+sg+'\' to group \''+dg+'\'?');
				}
				if((dmm || dm) && sm!='-1'){
					return window.confirm('Are you sure you want to move data of member \''+sm+'\' from group \''+sg+'\' to member \''+dm+'\' from group \''+dg+'\'?');
				}

				if(mm){
					return window.confirm('Are you sure you want to move all members and data from group \''+sg+'\' to group \''+dg+'\'?');
				}

				if(dmm){
					return window.confirm('Are you sure you want to move data of all members of group \''+sg+'\' to member \''+dm+'\' from group \''+dg+'\'?');
				}
			}

			function showDialog(dialogId){
				$$('.dialog-box').invoke('addClassName', 'hidden-block');
				$(dialogId).removeClassName('hidden-block');
				return false
			};

			function hideDialogs(){
				$$('.dialog-box').invoke('addClassName', 'hidden-block');
				return false
			};


			$j(function(){
				$j('input[type=submit],input[type=button]').each(function(){
					var label = $j(this).val();
					var onclick = $j(this).attr('onclick') || '';
					var name = $j(this).attr('name') || '';
					var type = $j(this).attr('type');

					$j(this).replaceWith('<button class="btn btn-primary" type="' + type + '" onclick="' + onclick + '" name="' + name + '" value="' + label + '">' + label + '</button>');
				});
			});

		</script>

		<link rel="stylesheet" href="adminStyles.css">

		<style>
			.dialog-box{
				background-color: white;
				border: 1px solid silver;
				border-radius: 10px 10px 10px 10px;
				box-shadow: 0 3px 100px silver;
				left: 30%;
				padding: 10px;
				position: absolute;
				top: 20%;
				width: 40%;
			}
			.hidden-block{
				display: none;
			}
		</style>
	</head>
	<body>
	<div class="container">

		<!-- top navbar -->
		<nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" href="pageHome.php"><span class="text-warning"><i class="glyphicon glyphicon-cog"></i> Admin Area</span></a>
			</div>

			<div class="collapse navbar-collapse navbar-ex1-collapse">
				<ul class="nav navbar-nav">
					<li class="dropdown">
						<a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="glyphicon glyphicon-globe"></i> Groups <b class="caret"></b></a>
						<ul class="dropdown-menu">
							<li><a href="pageViewGroups.php">View Groups</a></li>
							<li><a href="pageEditGroup.php">Add Group</a></li>
							<li class="divider"></li>
							<li><a href="pageEditGroup.php?groupID=<?php echo sqlValue("select groupID from membership_groups where name='" . makeSafe($adminConfig['anonymousGroup']) . "'"); ?>">Edit Anonymous Permissions</a></li>
						</ul>
					</li>

					<li class="dropdown">
						<a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="glyphicon glyphicon-user"></i> Members <b class="caret"></b></a>
						<ul class="dropdown-menu">
							<li><a href="pageViewMembers.php">View Members</a></li>
							<li><a href="pageEditMember.php">Add Member</a></li>
							<li class="divider"></li>
							<li><a href="pageViewRecords.php">View Members' Records</a></li>
						</ul>
					</li>

					<li class="dropdown">
						<a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="glyphicon glyphicon-cog"></i> Utilities <b class="caret"></b></a>
						<ul class="dropdown-menu">
							<li><a href="pageSettings.php">Admin Settings</a></li>
							<li class="divider"></li>
							<li><a href="pageRebuildFields.php">Rebuild fields</a></li>
							<li><a href="pageUploadCSV.php">Import CSV data</a></li>
							<li><a href="pageTransferOwnership.php">Batch Transfer Wizard</a></li>
							<li><a href="pageMail.php?sendToAll=1">Mail All Users</a></li>
							<li class="divider"></li>
							<li><a href="http://forums.appgini.com" target="_blank"><i class="glyphicon glyphicon-new-window"></i> AppGini Community Forum</a></li>
						</ul>
					</li>
				</ul>

				<div class="navbar-right">
					<a href="../index.php" class="btn btn-success navbar-btn">User's area</a>
					<a href="pageHome.php?signOut=1" class="btn btn-warning navbar-btn"><i class="glyphicon glyphicon-log-out"></i> Sign out</a>
				</div>
			</div>
		</nav>

		<div style="height: 80px;"></div>

		<!-- tool tips support -->
		<div id="TipLayer" style="visibility:hidden;position:absolute;z-index:1000;top:-100"></div>
		<script src="toolTipData.js"></script>
		<!-- /tool tips support -->

<?php
	if(!strstr($_SERVER['PHP_SELF'], 'pageSettings.php') && $adminConfig['adminPassword'] == md5('admin')){
		$noSignup=TRUE;
		?>
		<div class="alert alert-danger">
			<p><strong>Attention!</strong></p>
			<p>You are using the default admin
			<?php if($adminConfig['adminUsername'] == 'admin'){ ?>username and<?php } ?> password. This is a huge security
			risk. Please change <?php if($adminConfig['adminUsername'] == 'admin'){ ?> at least <?php } ?>
			the admin password from the
			<a href="pageSettings.php">Admin Settings</a> page <em>immediately</em>.</p>
		</div>
	<?php } ?>

