<?php
	$currDir=dirname(__FILE__);
	include("$currDir/defaultLang.php");
	include("$currDir/language.php");
	include("$currDir/lib.php");
	include_once("$currDir/header.php");

	$adminConfig = config('adminConfig');

	if(!$cg = sqlValue("select count(1) from membership_groups where allowSignup=1")){
		$noSignup = true;
		echo error_message($Translation['sign up disabled']);
		exit;
	}

	if($_POST['signUp'] != ''){
		// receive data
		$memberID = is_allowed_username($_POST['newUsername']);
		$email = isEmail($_POST['email']);
		$password = $_POST['password'];
		$confirmPassword = $_POST['confirmPassword'];
		$groupID = intval($_POST['groupID']);
		$custom1 = makeSafe($_POST['custom1']);
		$custom2 = makeSafe($_POST['custom2']);
		$custom3 = makeSafe($_POST['custom3']);
		$custom4 = makeSafe($_POST['custom4']);

		// validate data
		if(!$memberID){
			echo error_message($Translation['username invalid']);
			exit;
		}
		if(strlen($password) < 4 || trim($password) != $password){
			echo error_message($Translation['password invalid']);
			exit;
		}
		if($password != $confirmPassword){
			echo error_message($Translation['password no match']);
			exit;
		}
		if(!$email){
			echo error_message($Translation['email invalid']);
			exit;
		}
		if(!sqlValue("select count(1) from membership_groups where groupID='$groupID' and allowSignup=1")){
			echo error_message($Translation['group invalid']);
			exit;
		}

		// save member data
		$needsApproval = sqlValue("select needsApproval from membership_groups where groupID='$groupID'");
		sql("INSERT INTO `membership_users` set memberID='$memberID', passMD5='".md5($password)."', email='$email', signupDate='".@date('Y-m-d')."', groupID='$groupID', isBanned='0', isApproved='".($needsApproval==1 ? '0' : '1')."', custom1='$custom1', custom2='$custom2', custom3='$custom3', custom4='$custom4', comments='member signed up through the registration form.'", $eo);

		// admin mail notification
		/* ---- application name as provided in AppGini is used here ---- */
		if($adminConfig['notifyAdminNewMembers'] == 2 && !$needsApproval){
			@mail($adminConfig['senderEmail'], '[Bluesky] New member signup', "A new member has signed up for Bluesky.\n\nMember name: $memberID\nMember group: ".sqlValue("select name from membership_groups where groupID='$groupID'")."\nMember email: $email\nIP address: {$_SERVER['REMOTE_ADDR']}\nCustom fields:\n" . ($adminConfig['custom1'] ? "{$adminConfig['custom1']}: $custom1\n" : '') . ($adminConfig['custom2'] ? "{$adminConfig['custom2']}: $custom2\n" : '') . ($adminConfig['custom3'] ? "{$adminConfig['custom3']}: $custom3\n" : '') . ($adminConfig['custom4'] ? "{$adminConfig['custom4']}: $custom4\n" : ''), "From: {$adminConfig['senderEmail']}\r\n\r\n");
		}elseif($adminConfig['notifyAdminNewMembers'] >= 1 && $needsApproval){
			@mail($adminConfig['senderEmail'], '[Bluesky] New member awaiting approval', "A new member has signed up for Bluesky.\n\nMember name: $memberID\nMember group: ".sqlValue("select name from membership_groups where groupID='$groupID'")."\nMember email: $email\nIP address: {$_SERVER['REMOTE_ADDR']}\nCustom fields:\n" . ($adminConfig['custom1'] ? "{$adminConfig['custom1']}: $custom1\n" : '') . ($adminConfig['custom2'] ? "{$adminConfig['custom2']}: $custom2\n" : '') . ($adminConfig['custom3'] ? "{$adminConfig['custom3']}: $custom3\n" : '') . ($adminConfig['custom4'] ? "{$adminConfig['custom4']}: $custom4\n" : ''), "From: {$adminConfig['senderEmail']}\r\n\r\n");
		}

		// hook: member_activity
		if(function_exists('member_activity')){
			$args = array();
			member_activity(getMemberInfo($memberID), ($needsApproval ? 'pending' : 'automatic'), $args);
		}

		// redirect to thanks page
		$redirect = ($needsApproval ? '' : '?redir=1');
		redirect("membership_thankyou.php$redirect");

		exit;
	}

	// drop-down of groups allowing self-signup
	$groupsDropDown = preg_replace('/<option.*?value="".*?><\/option>/i', '', htmlSQLSelect('groupID', "select groupID, concat(name, if(needsApproval=1, ' *', ' ')) from membership_groups where allowSignup=1 order by name", ($cg == 1 ? sqlValue("select groupID from membership_groups where allowSignup=1 order by name limit 1") : 0 )));
	$groupsDropDown = str_replace('<select ', '<select class="form-control" ', $groupsDropDown);
?>

<?php if(!$noSignup){ ?>
	<div class="row">
		<div class="hidden-xs col-sm-4 col-md-6 col-lg-8" id="signup_splash">
			<!-- customized splash content here -->
		</div>

		<div class="col-sm-8 col-md-6 col-lg-4">
			<div class="panel panel-success">

				<div class="panel-heading">
					<h1 class="panel-title"><strong><?php echo $Translation['sign up here']; ?></strong></h1>
				</div>

				<div class="panel-body">
					<form method="post" action="membership_signup.php" onSubmit="return jsValidateSignup();">
						<div class="form-group">
							<label for="username" class="control-label"><?php echo $Translation['username']; ?></label>
							<input class="form-control input-lg" type="text" required="" placeholder="<?php echo $Translation['username']; ?>" id="username" name="newUsername">
							<span id="usernameAvailable" class="help-block invisible"><i class="glyphicon glyphicon-ok"></i> <?php echo str_ireplace(array("'", '"', '<memberid>'), '', $Translation['user available']); ?></span>
							<span id="usernameNotAvailable" class="help-block invisible"><i class="glyphicon glyphicon-remove"></i> <?php echo str_ireplace(array("'", '"', '<memberid>'), '', $Translation['username invalid']); ?></span>
						</div>

						<div class="row">
							<div class="col-sm-6">
								<div class="form-group">
									<label for="password" class="control-label"><?php echo $Translation['password']; ?></label>
									<input class="form-control" type="password" required="" placeholder="<?php echo $Translation['password']; ?>" id="password" name="password">
								</div>
							</div>
							<div class="col-sm-6">
								<div class="form-group">
									<label for="confirmPassword" class="control-label"><?php echo $Translation['confirm password']; ?></label>
									<input class="form-control" type="password" required="" placeholder="<?php echo $Translation['confirm password']; ?>" id="confirmPassword" name="confirmPassword">
								</div>
							</div>
						</div>

						<div class="form-group">
							<label for="email" class="control-label"><?php echo $Translation['email']; ?></label>
							<input class="form-control" type="text" required="" placeholder="<?php echo $Translation['email']; ?>" id="email" name="email">
						</div>

						<div class="form-group">
							<label for="group" class="control-label"><?php echo $Translation['group']; ?></label>
							<?php echo $groupsDropDown; ?>
							<span class="help-block"><?php echo $Translation['groups *']; ?></span>
						</div>

						<?php
							if(!$adminConfig['hide_custom_user_fields_during_signup']){
								for($cf = 1; $cf <= 4; $cf++){
									if($adminConfig['custom'.$cf] != ''){
										?>
										<div class="row form-group">
											<div class="col-sm-3"><label class="control-label" for="custom<?php echo $cf; ?>"><?php echo $adminConfig['custom'.$cf]; ?></label></div>
											<div class="col-sm-9"><input class="form-control" type="text" placeholder="<?php echo $adminConfig['custom'.$cf]; ?>" id="custom<?php echo $cf; ?>" name="custom<?php echo $cf; ?>"></div>
										</div>
										<?php
									}
								}
							}
						?>

						<div class="row">
							<div class="col-sm-offset-3 col-sm-6">
								<button class="btn btn-primary btn-lg btn-block" value="signUp" id="submit" type="submit" name="signUp"><?php echo $Translation['sign up']; ?></button>
							</div>
						</div>

					</form>
				</div> <!-- /div class="panel-body" -->
			</div> <!-- /div class="panel ..." -->
		</div> <!-- /div class="col..." -->
	</div> <!-- /div class="row" -->

	<script>
		$j(function() {
			show_one_block_only('usernameAvailable', 'usernameNotAvailable');
			$j('#username').focus();

			$j('#usernameAvailable, #usernameNotAvailable').click(function(){ $j('#username').focus(); });

			$j('#username').keyup(function(){
				if($j('#username').val().length){
					checkUser();
				}
			});

			$('username').observe('blur', function(){
				if(!$F('username').length){
					$('username').up().addClassName('has-error');
					show_one_block_only('usernameAvailable', 'usernameNotAvailable');
					return;
				}else{
					$('username').up().removeClassName('has-error');
				}
				checkUser();
			});

			/* password strength feedback */
			jQuery('#password').bind('keyup blur', function(){
				var ps = passwordStrength(jQuery('#password').val(), jQuery('#username').val());

				if(ps == 'strong'){
					jQuery('#password').parents('.form-group').removeClass('has-error has-warning').addClass('has-success');
					jQuery('#password').attr('title', '<?php echo htmlspecialchars($Translation['Password strength: strong']); ?>');
				}else if(ps == 'good'){
					jQuery('#password').parents('.form-group').removeClass('has-success has-error').addClass('has-warning');
					jQuery('#password').attr('title', '<?php echo htmlspecialchars($Translation['Password strength: good']); ?>');
				}else{
					jQuery('#password').parents('.form-group').removeClass('has-success has-warning').addClass('has-error');
					jQuery('#password').attr('title', '<?php echo htmlspecialchars($Translation['Password strength: weak']); ?>');
				}
			});

			/* inline feedback of confirm password */
			jQuery('#confirmPassword').bind('keyup blur', function(){
				if(jQuery('#confirmPassword').val() != jQuery('#password').val() || !jQuery('#confirmPassword').val().length){
					jQuery('#confirmPassword').parent().removeClass('has-success').addClass('has-error');
				}else{
					jQuery('#confirmPassword').parent().removeClass('has-error').addClass('has-success');
				}
			});

			/* inline feedback of email */
			$('email').observe('change', function(){
				if(validateEmail($F('email'))){
					$('email').up().removeClassName('has-error').addClassName('has-success');
				}else{
					$('email').up().removeClassName('has-success').addClassName('has-error');
				}
			});
		});

		var uaro; // user availability request object
		function checkUser(){
			// abort previous request, if any
			if(uaro != undefined) uaro.transport.abort();

			uaro = new Ajax.Request(
				'checkMemberID.php', {
					method: 'get',
					parameters: { 'memberID': $F('username') },
					onCreate: function(){
						$('usernameAvailable').addClassName('invisible');
						$('usernameNotAvailable').addClassName('invisible');
						$('usernameNotAvailable').up().removeClassName('has-error').removeClassName('has-success');
						show_one_block_only('usernameAvailable', 'usernameNotAvailable');
					},
					onSuccess: function(resp){
						var ua=resp.responseText;
						if(ua.match(/\<!-- AVAILABLE --\>/)){
							$('usernameAvailable').removeClassName('invisible').removeClassName('hidden');
							$('usernameAvailable').up().addClassName('has-success');
						}else{
							$('usernameNotAvailable').removeClassName('invisible').removeClassName('hidden');
							$('usernameNotAvailable').up().addClassName('has-error');
						}
						show_one_block_only('usernameAvailable', 'usernameNotAvailable');
					}
				}
			);
		}

		function show_one_block_only(id1, id2){
			var id1_invisible = jQuery('#' + id1).hasClass('invisible');
			var id2_invisible = jQuery('#' + id2).hasClass('invisible');
			var id1_hidden = jQuery('#' + id1).hasClass('hidden');
			var id2_hidden = jQuery('#' + id2).hasClass('hidden');

			     if( id1_invisible &&  id2_invisible &&  id1_hidden &&  id2_hidden) jQuery('#' + id1).removeClass('hidden');
			else if( id1_invisible &&  id2_invisible &&  id1_hidden && !id2_hidden) /* do nothing */;
			else if( id1_invisible &&  id2_invisible && !id1_hidden &&  id2_hidden) /* do nothing */;
			else if( id1_invisible &&  id2_invisible && !id1_hidden && !id2_hidden) jQuery('#' + id2).addClass('hidden');
			else if( id1_invisible && !id2_invisible &&  id1_hidden &&  id2_hidden) jQuery('#' + id2).removeClass('hidden');
			else if( id1_invisible && !id2_invisible &&  id1_hidden && !id2_hidden) /* do nothing */;
			else if( id1_invisible && !id2_invisible && !id1_hidden &&  id2_hidden) { jQuery('#' + id1).addClass('hidden'); jQuery('#' + id2).removeClass('hidden'); }
			else if( id1_invisible && !id2_invisible && !id1_hidden && !id2_hidden) jQuery('#' + id1).addClass('hidden');
			else if(!id1_invisible &&  id2_invisible &&  id1_hidden &&  id2_hidden) jQuery('#' + id1).removeClass('hidden');
			else if(!id1_invisible &&  id2_invisible &&  id1_hidden && !id2_hidden) { jQuery('#' + id1).removeClass('hidden'); jQuery('#' + id2).addClass('hidden'); }
			else if(!id1_invisible &&  id2_invisible && !id1_hidden &&  id2_hidden) /* do nothing */;
			else if(!id1_invisible &&  id2_invisible && !id1_hidden && !id2_hidden) jQuery('#' + id2).addClass('hidden');
			else if(!id1_invisible && !id2_invisible &&  id1_hidden &&  id2_hidden) jQuery('#' + id1).removeClass('hidden');
			else if(!id1_invisible && !id2_invisible &&  id1_hidden && !id2_hidden) /* do nothing */;
			else if(!id1_invisible && !id2_invisible && !id1_hidden &&  id2_hidden) /* do nothing */;
			else if(!id1_invisible && !id2_invisible && !id1_hidden && !id2_hidden) jQuery('#' + id2).addClass('hidden');
		}

		/* validate data before submitting */
		function jsValidateSignup(){
			var p1 = $F('password');
			var p2 = $F('confirmPassword');
			var user = $F('username');
			var email = $F('email');

			/* passwords not matching? */
			if(p1 != p2){
				modal_window({ message: '<div class="alert alert-danger"><?php echo addslashes($Translation['password no match']); ?></div>', title: "<?php echo addslashes($Translation['error:']); ?>", close: function(){ jQuery('#confirmPassword').focus(); } });
				return false;
			}

			/* user exists? */
			if(!$('usernameNotAvailable').hasClassName('invisible')){
				modal_window({ message: '<div class="alert alert-danger"><?php echo addslashes($Translation['username invalid']); ?></div>', title: "<?php echo addslashes($Translation['error:']); ?>", close: function(){ jQuery('#username').focus(); } });
				return false;
			}

			return true;
		}

	</script>

	<style>
		#usernameAvailable,#usernameNotAvailable{ cursor: pointer; }
	</style>

<?php } ?>

<?php include_once("$currDir/footer.php"); ?>
