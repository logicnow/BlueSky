<?php
	########################################################################
	/*
	~~~~~~ LIST OF FUNCTIONS ~~~~~~
		getTableList() -- returns an associative array of all tables in this application in the format tableName=>tableCaption
		getThumbnailSpecs($tableName, $fieldName, $view) -- returns an associative array specifying the width, height and identifier of the thumbnail file.
		createThumbnail($img, $specs) -- $specs is an array as returned by getThumbnailSpecs(). Returns true on success, false on failure.
		makeSafe($string)
		checkPermissionVal($pvn)
		sql($statment, $o)
		sqlValue($statment)
		getLoggedAdmin()
		checkUser($username, $password)
		logOutUser()
		getPKFieldName($tn)
		getCSVData($tn, $pkValue, $stripTag=true)
		errorMsg($msg)
		redirect($URL, $absolute=FALSE)
		htmlRadioGroup($name, $arrValue, $arrCaption, $selectedValue, $selClass="", $class="", $separator="<br>")
		htmlSelect($name, $arrValue, $arrCaption, $selectedValue, $class="", $selectedClass="")
		htmlSQLSelect($name, $sql, $selectedValue, $class="", $selectedClass="")
		isEmail($email) -- returns $email if valid or false otherwise.
		notifyMemberApproval($memberID) -- send an email to member acknowledging his approval by admin, returns false if no mail is sent
		setupMembership() -- check if membership tables exist or not. If not, create them.
		thisOr($this, $or) -- return $this if it has a value, or $or if not.
		getUploadedFile($FieldName, $MaxSize=0, $FileTypes='csv|txt', $NoRename=false, $dir='')
		toBytes($val)
		convertLegacyOptions($CSVList)
		getValueGivenCaption($query, $caption)
		undo_magic_quotes($str)
		time24($t) -- return time in 24h format
		time12($t) -- return time in 12h format
		application_url($page) -- return absolute URL of provided page
		is_ajax() -- return true if this is an ajax request, false otherwise
		array_trim($arr) -- recursively trim provided value/array
		csrf_token($validate) -- csrf-proof a form
	~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	*/
	########################################################################

	#########################################################
	if(!function_exists('getTableList')){
		function getTableList($skip_authentication = false){
			$arrTables = array(
				'computers' => 'BlueSky Admin',
				'global' => 'Global Settings',
				'connections' => 'Connection Log'
			);

			return $arrTables;
		}
	}
	########################################################################
	function getThumbnailSpecs($tableName, $fieldName, $view){
		return FALSE;
	}
	########################################################################
	function createThumbnail($img, $specs){
		$w=$specs['width'];
		$h=$specs['height'];
		$id=$specs['identifier'];
		$path=dirname($img);

		// image doesn't exist or inaccessible?
		if(!$size=@getimagesize($img))   return FALSE;

		// calculate thumbnail size to maintain aspect ratio
		$ow=$size[0]; // original image width
		$oh=$size[1]; // original image height
		$twbh=$h/$oh*$ow; // calculated thumbnail width based on given height
		$thbw=$w/$ow*$oh; // calculated thumbnail height based on given width
		if($w && $h){
			if($twbh>$w) $h=$thbw;
			if($thbw>$h) $w=$twbh;
		}elseif($w){
			$h=$thbw;
		}elseif($h){
			$w=$twbh;
		}else{
			return FALSE;
		}

		// dir not writeable?
		if(!is_writable($path))  return FALSE;

		// GD lib not loaded?
		if(!function_exists('gd_info'))  return FALSE;
		$gd=gd_info();

		// GD lib older than 2.0?
		preg_match('/\d/', $gd['GD Version'], $gdm);
		if($gdm[0]<2)    return FALSE;

		// get file extension
		preg_match('/\.[a-zA-Z]{3,4}$/U', $img, $matches);
		$ext=strtolower($matches[0]);

		// check if supplied image is supported and specify actions based on file type
		if($ext=='.gif'){
			if(!$gd['GIF Create Support'])   return FALSE;
			$thumbFunc='imagegif';
		}elseif($ext=='.png'){
			if(!$gd['PNG Support'])  return FALSE;
			$thumbFunc='imagepng';
		}elseif($ext=='.jpg' || $ext=='.jpe' || $ext=='.jpeg'){
			if(!$gd['JPG Support'] && !$gd['JPEG Support'])  return FALSE;
			$thumbFunc='imagejpeg';
		}else{
			return FALSE;
		}

		// determine thumbnail file name
		$ext=$matches[0];
		$thumb=substr($img, 0, -5).str_replace($ext, $id.$ext, substr($img, -5));

		// if the original image smaller than thumb, then just copy it to thumb
		if($h>$oh && $w>$ow){
			return (@copy($img, $thumb) ? TRUE : FALSE);
		}

		// get image data
		if(!$imgData=imagecreatefromstring(implode('', file($img)))) return FALSE;

		// finally, create thumbnail
		$thumbData=imagecreatetruecolor($w, $h);

		//preserve transparency of png and gif images
		if($thumbFunc=='imagepng'){
			if(($clr=@imagecolorallocate($thumbData, 0, 0, 0))!=-1){
				@imagecolortransparent($thumbData, $clr);
				@imagealphablending($thumbData, false);
				@imagesavealpha($thumbData, true);
			}
		}elseif($thumbFunc=='imagegif'){
			@imagealphablending($thumbData, false);
			$transIndex=imagecolortransparent($imgData);
			if($transIndex>=0){
				$transClr=imagecolorsforindex($imgData, $transIndex);
				$transIndex=imagecolorallocatealpha($thumbData, $transClr['red'], $transClr['green'], $transClr['blue'], 127);
				imagefill($thumbData, 0, 0, $transIndex);
			}
		}

		// resize original image into thumbnail
		if(!imagecopyresampled($thumbData, $imgData, 0, 0 , 0, 0, $w, $h, $ow, $oh)) return FALSE;
		unset($imgData);

		// gif transparency
		if($thumbFunc=='imagegif' && $transIndex>=0){
			imagecolortransparent($thumbData, $transIndex);
			for($y=0; $y<$h; ++$y)
				for($x=0; $x<$w; ++$x)
					if(((imagecolorat($thumbData, $x, $y)>>24) & 0x7F) >= 100)   imagesetpixel($thumbData, $x, $y, $transIndex);
			imagetruecolortopalette($thumbData, true, 255);
			imagesavealpha($thumbData, false);
		}

		if(!$thumbFunc($thumbData, $thumb))  return FALSE;
		unset($thumbData);

		return TRUE;
	}
	########################################################################
	function makeSafe($string, $is_gpc = true){
		if($is_gpc) $string = (get_magic_quotes_gpc() ? stripslashes($string) : $string);
		if(!db_link()){ sql("select 1+1", $eo); }
		return db_escape($string);
	}
	########################################################################
	function checkPermissionVal($pvn){
		// fn to make sure the value in the given POST variable is 0, 1, 2 or 3
		// if the value is invalid, it default to 0
		$pvn=intval($_POST[$pvn]);
		if($pvn!=1 && $pvn!=2 && $pvn!=3){
			return 0;
		}else{
			return $pvn;
		}
	}
	########################################################################
	if(!function_exists('sql')){
		function sql($statment, &$o){
			static $connected = false, $db_link; // $connect would be set to true on successful connection

			if(!$connected){
				/****** Connect to MySQL ******/
				if(!($db_link = @db_connect(config('dbServer'), config('dbUsername'), config('dbPassword')))){
					echo "<div class=\"alert alert-danger\">Couldn't connect to MySQL at '" . config('dbServer') . "'. You might need to re-configure this application. You can do so by manually editing the config.php file, or by deleting it to run the setup wizard.</div>";
					exit;
				}

				/****** Select DB ********/
				if(!db_select_db(config('dbDatabase'), $db_link)){
					echo "<div class=\"alert alert-danger\">Couldn't connect to the database '" . config('dbDatabase') . "'.</div>";
					exit;
				}

				$connected = true;
			}

			if(!$result = @db_query($statment)){
				echo "An error occured while attempting to execute:<br><pre>".htmlspecialchars($statment)."</pre><br>MySQL said:<br><pre>".db_error(db_link())."</pre>";
				exit;
			}

			return $result;
		}
	}
	########################################################################
	function sqlValue($statment){
		// executes a statment that retreives a single data value and returns the value retrieved
		if(!$res=sql($statment, $eo)){
			return FALSE;
		}
		if(!$row=db_fetch_row($res)){
			return FALSE;
		}
		return $row[0];
	}
	########################################################################
	function getLoggedAdmin(){
		// checks session variables to see whether the admin is logged or not
		// if not, it returns FALSE
		// if logged, it returns the user id

		$adminConfig = config('adminConfig');

		if($_SESSION['adminUsername']!=''){
			return $_SESSION['adminUsername'];
		}elseif($_SESSION['memberID']==$adminConfig['adminUsername']){
			$_SESSION['adminUsername']=$_SESSION['memberID'];
			return $_SESSION['adminUsername'];
		}else{
			return FALSE;
		}
	}
	########################################################################
	function checkUser($username, $password){
		// checks given username and password for validity
		// if valid, registers the username in a session and returns true
		// else, return FALSE and destroys session

		$adminConfig = config('adminConfig');
		if($username != $adminConfig['adminUsername'] || md5($password) != $adminConfig['adminPassword']){
			return FALSE;
		}

		$_SESSION['adminUsername'] = $username;
		$_SESSION['memberGroupID'] = sqlValue("select groupID from membership_users where memberID='" . makeSafe($username) ."'");
		$_SESSION['memberID'] = $username;
		return TRUE;
	}
	########################################################################
	function logOutUser(){
		// destroys current session
		$_SESSION = array();
		if(isset($_COOKIE[session_name()])){
			setcookie(session_name(), '', time()-42000, '/');
		}
		if(isset($_COOKIE['Bluesky_rememberMe'])){
			setcookie('Bluesky_rememberMe', '', time()-42000);
		}
		session_destroy();
	}
	########################################################################
	function getPKFieldName($tn){
		// get pk field name of given table

		if(!$res=sql("show fields from `$tn`", $eo)){
			return FALSE;
		}

		while($row=db_fetch_assoc($res)){
			if($row['Key']=='PRI'){
				return $row['Field'];
			}
		}

		return FALSE;
	}
	########################################################################
	function getCSVData($tn, $pkValue, $stripTags=true){
		// get pk field name for given table
		if(!$pkField=getPKFieldName($tn)){
			return "";
		}

		// get a concat string to produce a csv list of field values for given table record
		if(!$res=sql("show fields from `$tn`", $eo)){
			return "";
		}
		while($row=db_fetch_assoc($res)){
			$csvFieldList.="`{$row['Field']}`,";
		}
		$csvFieldList=substr($csvFieldList, 0, -1);

		$csvData=sqlValue("select CONCAT_WS(', ', $csvFieldList) from `$tn` where `$pkField`='" . makeSafe($pkValue, false) . "'");

		return ($stripTags ? strip_tags($csvData) : $csvData);
	}
	########################################################################
	function errorMsg($msg){
		echo "<div class=\"status\" style=\"font-weight: bold; color: red;\">$msg</div>";
	}
	########################################################################
	function redirect($URL, $absolute=FALSE){
		$fullURL = ($absolute ? $URL : application_url($URL));
		if(!headers_sent()) header("Location: $fullURL");

		echo "<META HTTP-EQUIV=\"Refresh\" CONTENT=\"0;url=$fullURL\">";
		echo "<br><br><a href=\"$fullURL\">Click here</a> if you aren't automatically redirected.";
		exit;
	}
	########################################################################
	function htmlRadioGroup($name, $arrValue, $arrCaption, $selectedValue, $selClass="", $class="", $separator="<br>"){
		if(is_array($arrValue)){
			for($i=0; $i<count($arrValue); $i++){
				$out.="<span onMouseOver=\"stm(".$name.$arrValue[$i]."Tip, toolTipStyle);\"  onMouseOut=\"htm();\" class=\"".($arrValue[$i]==$selectedValue ? $selClass :$class)."\"><input type=\"radio\" id=\"$name$i\" name=\"$name\" value=\"".$arrValue[$i]."\"".($arrValue[$i]==$selectedValue ? " checked" : "")."> <label for=\"$name$i\">".$arrCaption[$i]."</label></span>".$separator;
			}
		}
		return $out;
	}
	########################################################################
	function htmlSelect($name, $arrValue, $arrCaption, $selectedValue, $class="", $selectedClass=""){
		if($selectedClass==""){
			$selectedClass=$class;
		}
		if(is_array($arrValue)){
			$out="<select name=\"$name\" id=\"$name\">";
			for($i=0; $i<count($arrValue); $i++){
				$out.="<option value=\"".$arrValue[$i]."\"".($arrValue[$i]==$selectedValue ? " selected class=\"$class\"" : " class=\"$selectedClass\"").">".$arrCaption[$i]."</option>";
			}
			$out.="</select>";
		}
		return $out;
	}
	########################################################################
	function htmlSQLSelect($name, $sql, $selectedValue, $class="", $selectedClass=""){
		$arrVal[]='';
		$arrCap[]='';
		if($res=sql($sql, $eo)){
			while($row=db_fetch_row($res)){
				$arrVal[]=$row[0];
				$arrCap[]=$row[1];
			}
			return htmlSelect($name, $arrVal, $arrCap, $selectedValue, $class, $selectedClass);
		}else{
			return "";
		}
	}
	########################################################################
	function isEmail($email){
		if(filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return $email;
		}else{
			return FALSE;
		}
	}
	########################################################################
	function notifyMemberApproval($memberID){
		$adminConfig = config('adminConfig');
		$memberID=strtolower($memberID);

		$email=sqlValue("select email from membership_users where lcase(memberID)='$memberID'");
		if(!isEmail($email)){
			return FALSE;
		}
		if(!@mail($email, $adminConfig['approvalSubject'], $adminConfig['approvalMessage'], "From: ".$adminConfig['senderName']." <".$adminConfig['senderEmail'].">")){
			return FALSE;
		}

		return TRUE;
	}
	########################################################################
	function setupMembership(){
		// run once per request
		static $executed = false;
		if($executed) return;
		$executed = true;

		$adminConfig = config('adminConfig');
		$today = @date('Y-m-d');

		$membership_tables = array(
			'membership_groups' => "CREATE TABLE IF NOT EXISTS membership_groups (groupID int unsigned NOT NULL auto_increment, name varchar(20), description text, allowSignup tinyint, needsApproval tinyint, PRIMARY KEY (groupID))",
			'membership_users' => "CREATE TABLE IF NOT EXISTS membership_users (memberID varchar(20) NOT NULL, passMD5 varchar(40), email varchar(100), signupDate date, groupID int unsigned, isBanned tinyint, isApproved tinyint, custom1 text, custom2 text, custom3 text, custom4 text, comments text, PRIMARY KEY (memberID))",
			'membership_grouppermissions' => "CREATE TABLE IF NOT EXISTS membership_grouppermissions (permissionID int unsigned NOT NULL auto_increment,  groupID int, tableName varchar(100), allowInsert tinyint, allowView tinyint NOT NULL DEFAULT '0', allowEdit tinyint NOT NULL DEFAULT '0', allowDelete tinyint NOT NULL DEFAULT '0', PRIMARY KEY (permissionID))",
			'membership_userrecords' => "CREATE TABLE IF NOT EXISTS membership_userrecords (recID bigint unsigned NOT NULL auto_increment, tableName varchar(100), pkValue varchar(255), memberID varchar(20), dateAdded bigint unsigned, dateUpdated bigint unsigned, groupID int, PRIMARY KEY (recID))",
			'membership_userpermissions' => "CREATE TABLE IF NOT EXISTS membership_userpermissions (permissionID int unsigned NOT NULL auto_increment,  memberID varchar(20) NOT NULL, tableName varchar(100), allowInsert tinyint, allowView tinyint NOT NULL DEFAULT '0', allowEdit tinyint NOT NULL DEFAULT '0', allowDelete tinyint NOT NULL DEFAULT '0', PRIMARY KEY (permissionID))"
		);

		// get db tables
		$tables = array();
		$res = sql("show tables", $eo);
		while($row = db_fetch_array($res)) $tables[] = $row[0];

		// check if membership tables exist or not
		foreach($membership_tables as $tn => $tdef){
			if(!in_array($tn, $tables)){
				sql($tdef, $eo);
			}
		}

		// check membership_users definition
		$membership_users = array();
		$res = sql("show columns from membership_users", $eo);
		while($row = db_fetch_assoc($res)) $membership_users[$row['Field']] = $row;

		if(!in_array('pass_reset_key', array_keys($membership_users))) @db_query("ALTER TABLE membership_users ADD COLUMN pass_reset_key VARCHAR(100)");
		if(!in_array('pass_reset_expiry', array_keys($membership_users))) @db_query("ALTER TABLE membership_users ADD COLUMN pass_reset_expiry INT UNSIGNED");
		if(!$membership_users['groupID']['Key']) @db_query("ALTER TABLE membership_users ADD INDEX groupID (groupID)");

		// create membership indices if not existing
		$membership_userrecords = array();
		$res = sql("show keys from membership_userrecords", $eo);
		while($row = db_fetch_assoc($res)) $membership_userrecords[$row['Key_name']][$row['Seq_in_index']] = $row;

		if(!$membership_userrecords['pkValue'][1]) @db_query("ALTER TABLE membership_userrecords ADD INDEX pkValue (pkValue)");
		if(!$membership_userrecords['tableName'][1]) @db_query("ALTER TABLE membership_userrecords ADD INDEX tableName (tableName)");
		if(!$membership_userrecords['memberID'][1]) @db_query("ALTER TABLE membership_userrecords ADD INDEX memberID (memberID)");
		if(!$membership_userrecords['groupID'][1]) @db_query("ALTER TABLE membership_userrecords ADD INDEX groupID (groupID)");
		if(!$membership_userrecords['tableName_pkValue'][1] || !$membership_userrecords['tableName_pkValue'][2]) @db_query("ALTER IGNORE TABLE membership_userrecords ADD UNIQUE INDEX tableName_pkValue (tableName, pkValue)");

		// retreive anonymous and admin groups and their permissions
		$anon_group = $adminConfig['anonymousGroup'];
		$anon_user = strtolower($adminConfig['anonymousMember']);
		$admin_group = 'Admins';
		$admin_user = strtolower($adminConfig['adminUsername']);
		$groups_permissions = array();
		$res = sql(
			"select g.groupID, g.name, gp.tableName, gp.allowInsert, gp.allowView, gp.allowEdit, gp.allowDelete " .
			"from membership_groups g left join membership_grouppermissions gp on g.groupID=gp.groupID " .
			"where g.name='" . makeSafe($admin_group) . "' or g.name='" . makeSafe($anon_group) . "' " .
			"order by g.groupID, gp.tableName", $eo
		);
		while($row = db_fetch_assoc($res)) $groups_permissions[] = $row;

		// check anonymous group and user and create if necessary
		$anon_group_id = false;
		foreach($groups_permissions as $group){
			if($group['name'] == $anon_group){
				$anon_group_id = $group['groupID'];
				break;
			}
		}

		if(!$anon_group_id){
			sql("insert into membership_groups set name='" . makeSafe($anon_group) . "', allowSignup=0, needsApproval=0, description='Anonymous group created automatically on " . @date("Y-m-d") . "'", $eo);
			$anon_group_id = db_insert_id();
		}

		if($anon_group_id){
			$anon_user_db = sqlValue("select lcase(memberID) from membership_users where lcase(memberID)='" . makeSafe($anon_user) . "' and groupID='{$anon_group_id}'");
			if(!$anon_user_db || $anon_user_db != $anon_user){
				sql("delete from membership_users where groupID='{$anon_group_id}'", $eo);
				sql("insert into membership_users set memberID='" . makeSafe($anon_user) . "', signUpDate='{$today}', groupID='{$anon_group_id}', isBanned=0, isApproved=1, comments='Anonymous member created automatically on {$today}'", $eo);
			}
		}

		// check admin group and user and create if necessary
		$admin_group_id = false;
		foreach($groups_permissions as $group){
			if($group['name'] == $admin_group){
				$admin_group_id = $group['groupID'];
				break;
			}
		}

		if(!$admin_group_id){
			sql("insert into membership_groups set name='" . makeSafe($admin_group) . "', allowSignup=0, needsApproval=1, description='Admin group created automatically on {$today}'", $eo);
			$admin_group_id = db_insert_id();
		}

		if($admin_group_id){
			// check that admins can access all tables
			$all_tables = getTableList(true);
			$tables_ok = $perms_ok = array();
			foreach($all_tables as $tn => $tc) $tables_ok[$tn] = $perms_ok[$tn] = false;

			foreach($groups_permissions as $group){
				if($group['name'] == $admin_group){
					if(isset($tables_ok[$group['tableName']])){
						$tables_ok[$group['tableName']] = true;
						if($group['allowInsert'] == 1 && $group['allowDelete'] == 3 && $group['allowEdit'] == 3 && $group['allowView'] == 3){
							$perms_ok[$group['tableName']] = true;
						}
					}
				}
			}

			// if any table has no record in Admins permissions, create one for it
			$grant_sql = array();
			foreach($tables_ok as $tn => $status){
				if(!$status) $grant_sql[] = "({$admin_group_id}, '{$tn}')";
			}

			if(count($grant_sql)){
				sql("insert into membership_grouppermissions (groupID, tableName) values " . implode(',', $grant_sql), $eo);
			}

			// check admin permissions and update if necessary
			$perms_sql = array();
			foreach($perms_ok as $tn => $status){
				if(!$status) $perms_sql[] = "'{$tn}'";
			}

			if(count($perms_sql)){
				sql("update membership_grouppermissions set allowInsert=1, allowView=3, allowEdit=3, allowDelete=3 where groupID={$admin_group_id} and tableName in (" . implode(',', $perms_sql) . ")", $eo);
			}

			// check if super admin is stored in the users table and add him if not
			$admin_user_exists = sqlValue("select count(1) from membership_users where lcase(memberID)='" . makeSafe($admin_user)."' and groupID='{$admin_group_id}'");
			if(!$admin_user_exists){
				sql("insert into membership_users set memberID='" . makeSafe($admin_user) . "', passMD5='{$adminConfig['adminPassword']}', email='{$adminConfig['senderEmail']}', signUpDate='{$today}', groupID='{$admin_group_id}', isBanned=0, isApproved=1, comments='Admin member created automatically on {$today}'", $eo);
			}
		}
	}

	########################################################################
	function thisOr($this, $or='&nbsp;'){
		return ($this!='' ? $this : $or);
	}
	########################################################################
	function getUploadedFile($FieldName, $MaxSize=0, $FileTypes='csv|txt', $NoRename=false, $dir=''){
		$currDir=dirname(__FILE__);
		if(is_array($_FILES)){
			$f = $_FILES[$FieldName];
		}else{
			return 'Your php settings don\'t allow file uploads.';
		}

		if(!$MaxSize){
			$MaxSize=toBytes(ini_get('upload_max_filesize'));
		}

		if(!is_dir("$currDir/csv")){
			@mkdir("$currDir/csv");
		}

		$dir=(is_dir($dir) && is_writable($dir) ? $dir : "$currDir/csv/");

		if($f['error']!=4 && $f['name']!=''){
			if($f['size']>$MaxSize || $f['error']){
				return 'File size exceeds maximum allowed of '.intval($MaxSize / 1024).'KB';
			}
			if(!preg_match('/\.('.$FileTypes.')$/i', $f['name'], $ft)){
				return 'File type not allowed. Only these file types are allowed: '.str_replace('|', ', ', $FileTypes);
			}

			if($NoRename){
				$n  = str_replace(' ', '_', $f['name']);
			}else{
				$n  = microtime();
				$n  = str_replace(' ', '_', $n);
				$n  = str_replace('0.', '', $n);
				$n .= $ft[0];
			}

			if(!@move_uploaded_file($f['tmp_name'], $dir . $n)){
				return 'Couldn\'t save the uploaded file. Try chmoding the upload folder "'.$dir.'" to 777.';
			}else{
				@chmod($dir.$n, 0666);
				return $dir.$n;
			}
		}
		return 'An error occured while uploading the file. Please try again.';
	}
	########################################################################
	function toBytes($val){
		$val = trim($val);
		$last = strtolower($val{strlen($val)-1});
		switch($last){
			 // The 'G' modifier is available since PHP 5.1.0
			 case 'g':
					$val *= 1024;
			 case 'm':
					$val *= 1024;
			 case 'k':
					$val *= 1024;
		}

		return $val;
	}
	########################################################################
	function convertLegacyOptions($CSVList){
		$CSVList=str_replace(';;;', ';||', $CSVList);
		$CSVList=str_replace(';;', '||', $CSVList);
		return $CSVList;
	}
	########################################################################
	function getValueGivenCaption($query, $caption){
		if(!preg_match('/select\s+(.*?)\s*,\s*(.*?)\s+from\s+(.*?)\s+order by.*/i', $query, $m)){
			if(!preg_match('/select\s+(.*?)\s*,\s*(.*?)\s+from\s+(.*)/i', $query, $m)){
				return '';
			}
		}

		// get where clause if present
		if(preg_match('/\s+from\s+(.*?)\s+where\s+(.*?)\s+order by.*/i', $query, $mw)){
			$where="where ($mw[2]) AND";
			$m[3]=$mw[1];
		}else{
			$where='where';
		}

		$caption=makeSafe($caption);
		return sqlValue("SELECT $m[1] FROM $m[3] $where $m[2]='$caption'");
	}
	########################################################################
	function undo_magic_quotes($str){
		return (get_magic_quotes_gpc() ? stripslashes($str) : $str);
	}
	########################################################################
	function time24($t = false){
		if($t === false) $t = time();
		return date('H:i:s', strtotime($t));
	}
	########################################################################
	function time12($t = false){
		if($t === false) $t = time();
		return date('h:i:s A', strtotime($t));
	}
	########################################################################
	function application_url($page = ''){
		$host = $_SERVER['HTTP_HOST'];
		$uri = dirname($_SERVER['PHP_SELF']);

		/* app folder name (without the ending /admin part) */
		$app_folder_is_admin = false;
		$app_folder = substr(dirname(__FILE__), 0, -6);
		if(substr($app_folder, -6, 6) == '/admin' || substr($app_folder, -6, 6) == '\\admin')
			$app_folder_is_admin = true;

		if(substr($uri, -12, 12) == '/admin/admin') $uri = substr($uri, 0, -6);
		elseif(substr($uri, -6, 6) == '/admin' && !$app_folder_is_admin) $uri = substr($uri, 0, -6);
		elseif($uri == '/') $uri = '';

		$http = (strtolower($_SERVER['HTTPS']) == 'on' ? 'https:' : 'http:');

		return "{$http}//{$host}{$uri}/{$page}";
	}
	########################################################################
	function is_ajax(){
		return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
	}
	########################################################################
	function array_trim($arr){
		if(!is_array($arr)) return trim($arr);
		return array_map('array_trim', $arr);
	}
	########################################################################
	function is_allowed_username($username){
		$username = trim(strtolower($username));
		if(!preg_match('/^[a-z0-9][a-z0-9 _.@]{3,19}$/', $username) || preg_match('/(@@|  |\.\.|___)/', $username)) return false;
		if(sqlValue("select count(1) from membership_users where lcase(memberID)='{$username}'")) return false;
		return $username;
	}
	########################################################################
	/*
		if called without parameters, looks for a non-expired token in the user's session (or creates one if
		none found) and returns html code to insert into the form to be protected.

		if set to true, validates token sent in $_REQUEST against that stored in the session
		and returns true if valid or false if invalid, absent or expired.

		usage:
			1. in a new form that needs csrf proofing: echo csrf_token();
			2. when validating a submitted form: if(!csrf_token(true)){ reject_submission_somehow(); }
	*/
	function csrf_token($validate = false){
		$token_age = 30 * 60;
		/* retrieve token from session */
		$csrf_token = (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : false);
		$csrf_token_expiry = (isset($_SESSION['csrf_token_expiry']) ? $_SESSION['csrf_token_expiry'] : false);

		if(!$validate){
			/* create a new token if necessary */
			if($csrf_token_expiry < time() || !$csrf_token){
				$csrf_token = md5(uniqid(rand(), true));
				$csrf_token_expiry = time() + $token_age;
				$_SESSION['csrf_token'] = $csrf_token;
				$_SESSION['csrf_token_expiry'] = $csrf_token_expiry;
			}

			return '<input type="hidden" id="csrf_token" name="csrf_token" value="' . $csrf_token . '">';
		}

		/* validate submitted token */
		$user_token = (isset($_REQUEST['csrf_token']) ? $_REQUEST['csrf_token'] : false);
		if($csrf_token_expiry < time() || !$user_token || $user_token != $csrf_token){
			return false;
		}

		return true;
	}
