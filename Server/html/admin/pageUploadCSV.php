<?php
	@ini_set('auto_detect_line_endings', 1);
	define('MAXROWS', 500000); /* max records to import from the csv file per run */
	define('BATCHSIZE', 200); /* number of records to insert per query */

	ignore_user_abort(true);
	set_time_limit(0);
	@ini_set('auto_detect_line_endings', '1');
	$currDir=dirname(__FILE__);
	require("$currDir/incCommon.php");
	include("$currDir/incHeader.php");

	$arrTables=getTableList();

	if($_POST['csvPreview']!=''){
		$fn=(strpos($_POST['csvPreview'], 'Apply')===false ? getUploadedFile('csvFile') : $_SESSION['csvUploadFile']);

		$headCellStyle='border: solid 1px white; border-bottom: solid 1px #C0C0C0; border-right: solid 1px #C0C0C0; background-color: #ECECFB; font-weight: bold; font-size: 12px; padding: 0 2px;';
		$dataCellStyle='border: solid 1px white; border-bottom: solid 1px #C0C0C0; border-right: solid 1px #C0C0C0; font-size: 10px; padding: 0 2px;';

		if(!is_file($fn)){
			?>
			<div class="alert alert-danger">
				Error: File '<?php echo $fn; ?>' not found.
				</div>
			<?php
			include("$currDir/incFooter.php");
			exit;
		}

		?>
		<!--<div align=left>
		<pre><?php print_r($_POST); ?></pre>
		<pre><?php print_r($_SESSION); ?></pre>
		</div>-->
		<?php

		$_SESSION['csvUploadFile']=$fn;

		$arrPreviewData=getCSVArray(0, 10, false);
		if(!is_array($arrPreviewData)){
			die('<div class="alert alert-danger">Error: '. $arrPreviewData.'</div>');
		}
		?>
		<div class="page-header"><h1>Preview the CSV data then confirm to import it ...</h1></div>

		<form method="post" action="pageUploadCSV.php">
		<div class="table-responsive"><table class="table table-striped">
			<tr><td colspan="<?php echo (count($arrPreviewData[0])+1); ?>"><i>Displaying the first 10 rows of the CSV file ...</i></td></tr>
			<tr><td width="60" style="<?php echo $headCellStyle; ?>">&nbsp;</td><?php
			foreach($arrPreviewData[0] as $fc){
				echo '<td style="'.$headCellStyle.'">'.$fc.'</td>';
			}
			?></tr><?php

		for($i=1; $i<count($arrPreviewData); $i++){
			?><tr><td style="<?php echo $headCellStyle; ?>" align="right"><?php echo $i; ?></td><?php
			foreach($arrPreviewData[$i] as $fv){
				?><td style="<?php echo $dataCellStyle; ?>"><?php echo nl2br($fv != '' ? (strlen($fv) > 20 ? substr($fv, 0, 18) . '...' : $fv) : '&nbsp;'); ?></td><?php
			}
			?></tr><?php
		}

		?>

		<tr><td align="left" colspan="<?php echo (count($arrPreviewData[0])+1); ?>" style="<?php echo $headCellStyle; ?>">
			<input type="button" value="Change CSV settings" style="font-weight: bold;" onclick="
				document.getElementById('advancedOptions').style.display='inline'; 
				document.getElementById('applyCSVSettings').style.display='inline';
				this.style.display='none';
				">
			<input type="submit" name="csvImport" value="Confirm and import CSV data &gt;" style="font-weight: bold;" onclick="this.visibility='hidden';">
			</td></tr>
		</table></div>

		<?php echo advancedCSVSettingsForm(); ?>
		<div id="applyCSVSettings" style="width: 850px; text-align: right; visibility: hidden;">
			<input type="submit" name="csvPreview" value="Apply CSV Settings" style="font-weight: bold;">
			</div>
		<input type="hidden" name="tableName" value="<?php echo htmlspecialchars($_POST['tableName'])?>">
		</form>
		<?php
	}elseif($_POST['csvImport']!='' || $_GET['csvImport']!=''){
		if($_GET['csvImport']!=''){
			$_POST=$_GET;
			$csvStart=intval($_GET['csvStart']);
		}else{
			$csvStart=0;
		}

		// get settings
		getCSVSettings($csvIgnoreNRows, $csvCharsPerLine, $csvFieldSeparator, $csvFieldDelimiter, $csvFieldNamesOnTop, $csvUpdateIfPKExists, $csvBackupBeforeImport);

		// measure time
		$t1=array_sum(explode(' ', microtime()));

		// validate filename
		$fn=$_SESSION['csvUploadFile'];
		if($fn==''){
			die('<META HTTP-EQUIV="Refresh" CONTENT="0;url=pageUploadCSV.php?entropy='.rand().'">');
		}
		if(!is_file($fn)){
			?>
			<div class="alert alert-danger">
				Error: File '<?php echo $fn; ?>' not found.
				</div>
			<?php
			include("$currDir/incFooter.php");
			exit;
		}

		// estimate number of records
		if(!$_SESSION['csvEstimatedRecords']){
			if($handle=@fopen($fn, "r")){
				$i=0;
				while(!feof($handle)){
					$tempLine=fgets($handle, 4096);
					if(trim($tempLine)!='') $i++;
				}
				fclose($handle);
			}
			$_SESSION['csvEstimatedRecords']=($i-$csvIgnoreNRows-($csvFieldNamesOnTop ? 1 : 0));
		}

		// header
		?>
		<div class="page-header"><h1>Importing CSV data ...</h1></div>
		<div style="width: 700px; text-align: left;">
		<?php

		// get tablename and csv data
		$tn = $_POST['tableName'];
		$arrCSVData = getCSVArray($csvStart, 0, false);
		echo 'Starting at record '.number_format($csvStart).' of '.number_format($_SESSION['csvEstimatedRecords']).' total estimated records ...<br>';

		if(@count($arrCSVData)>1){
			// backup table
			if($_POST['csvBackupBeforeImport']){
				if(sqlValue("select count(1) from `$tn`")){
					$btn=$tn.'_backup_'.@date('YmdHis');
					sql("drop table if exists `$btn`", $eo);
					sql("create table if not exists `$btn` select * from `$tn`", $eo);

					echo "Table '$tn' backed up as '$btn'.<br><br>";
				}else{
					echo "Table '$tn' is empty, so no backup was done.<br><br>";
				}
			}

			// field list
			$fieldList='`'.implode('`,`', noSpaces($arrCSVData[0])).'`';

			// insert records
			$batch=BATCHSIZE; /* batch size (records per batch) */
			$numRows=count($arrCSVData)-1;
			$numBatches=ceil($numRows/$batch);

			echo '<textarea cols="70" rows="15" class="formTextBox">';
			for($i=1; $i<=$numRows; $i+=$batch){
				$insert='';
				for($j=$i; $j<($i+$batch) && $j<=$numRows; $j++){
					// add slashes to field values if necessary
					foreach($arrCSVData[$j] as $fi=>$fv){
						$arrCSVData[$j][$fi] = makeSafe($fv);
					}
					$valList=implode("','", $arrCSVData[$j]);
					if($valList!='' && strlen($valList)>count($arrCSVData[$j])*3)
						$insert.="('".$valList."'),";
				}

				// update record if pk matches
				if($_POST['csvUpdateIfPKExists']){
					$insert="replace `$tn` ($fieldList) values ".substr($insert, 0, -1);
				}else{
					$insert="insert ignore into `$tn` ($fieldList) values ".substr($insert, 0, -1);
				}

				// execute batch
				echo 'Importing batch '.(($i-1)/$batch + 1).' of '.$numBatches.': ';
				if(!@db_query($insert)){
					echo 'ERROR: ' . db_error(db_link()) . "\n";
				}else{
					echo "Ok\n";
				}

				if(!($i%($batch*5)))   flush();
			}
			echo "</textarea>";
		}else{ /* no more records in csv file */
			$numRows=0;
		}

		if($numRows<MAXROWS){ /* reached end of data */
			// remove uploaded csv file
			@unlink($fn);
			$_SESSION['csvUploadFile']='';
			$_SESSION['csvEstimatedRecords']='';
			?>
			<br><b><?php echo $numRows; ?> records inserted/updated in <?php echo round(array_sum(explode(' ', microtime())) - $t1, 3); ?> seconds. <i style="color: green;">Mission accomplished!</i></b>
			<br><br><input type="button" name="assignOwner" value="Assign an owner to the imported records &gt;" style="font-weight: bold;" onclick="window.location='pageAssignOwners.php';">
			<?php
		}else{
			?>
			<META HTTP-EQUIV="Refresh" CONTENT="0;url=pageUploadCSV.php?csvImport=1&tableName=<?php echo urlencode($tn); ?>&csvBackupBeforeImport=0&csvUpdateIfPKExists=<?php echo $csvUpdateIfPKExists; ?>&csvIgnoreNRows=<?php echo $csvIgnoreNRows; ?>&csvCharsPerLine=<?php echo $csvCharsPerLine; ?>&csvFieldSeparator=<?echo urlencode($csvFieldSeparator); ?>&csvStart=<?php echo ($csvStart+$numRows); ?>&csvFieldDelimiter=<?php echo urlencode($csvFieldDelimiter); ?>">
			<br><b><?php echo $numRows; ?> records inserted/updated in <?php echo round(array_sum(explode(' ', microtime())) - $t1, 3); ?> seconds. <i style="color: red; background-color: #FFFF9C;">Please wait and don't close this page ...</i></b>
			<?php
		}
		echo '</div>';

	}else{ // first step
		?>
		<script>
			<!--
			function toggleAdvancedOptions(){
				var t=document.getElementById('advancedOptions');
				var b=document.getElementById('TAO');

				if(b.checked){
					t.style.display='inline';
					b.value='Hide advanced options';
				}else{
					t.style.display='none';
					b.value='Show advanced options';
				}
			}
			//-->
			</script>

		<div class="page-header"><h1>Import a CSV file to the database</h1></div>

		<form enctype="multipart/form-data" method="post" action="pageUploadCSV.php">
			<table class="table table-striped">
				<tr>
					<td colspan="2" class="tdFormCaption">
						<div class="formFieldCaption">
							This page allows you to upload a CSV file
							(for example, one generated from MS Excel) and
							import it to one of the tables of the database.
							This makes it so easy to bulk-populate the database
							with data from other sources rather than manually
							entering every single record.
							</div>
						</td>
					</tr>
				<tr>
					<td align="right" class="tdFormCaption" valign="top" width="250">
						<div class="formFieldCaption">Table</div>
						</td>
					<td align="left" class="tdFormInput">
						<?php 
							echo htmlSelect('tableName', array_keys($arrTables), array_values($arrTables), '');
						?>
						<br><i>This is the table that you want to populate with data from the CSV file.</i>
						</td>
					</tr>
				<tr>
					<td align="right" class="tdFormCaption" valign="top">
						<div class="formFieldCaption">CSV file</div>
						</td>
					<td align="left" class="tdFormInput">
						<input type="file" name="csvFile" class="formTextBox"><br>
						</td>
					</tr>
				<tr>
					<td align="left" class="tdFormCaption" valign="top" colspan="2">
						<div class="formFieldCaption"><input type="checkbox" id="TAO" onclick="toggleAdvancedOptions();"> <label for="TAO">Show advanced options</label></div>
						</td>
					</tr>
				</table>

			<?php echo advancedCSVSettingsForm(); ?>

			<table class="table table-striped">
				<tr>
					<td align="right" class="tdFormCaption" valign="top" colspan="2">
						<input type="submit" name="csvPreview" value="Preview CSV data &gt;" style="font-weight: bold;">
						</td>
					</tr>
				</table>
			</form>
		<?php
	}

	include("$currDir/incFooter.php");

	##########################################################################
	function getCSVArray($start = 0, $numRows = 0, $makeSafe = true){
		if($numRows<1) $numRows=MAXROWS;

		getCSVSettings($csvIgnoreNRows, $csvCharsPerLine, $csvFieldSeparator, $csvFieldDelimiter, $csvFieldNamesOnTop, $csvUpdateIfPKExists, $csvBackupBeforeImport);

		$tn=$_POST['tableName'];
		if($tn=='')    return 'No table name provided.';

		// get field names of table
		$res=sql('select * from `'.$tn.'` limit 1', $eo);
		for($i=0; $i<db_num_fields($res); $i++){
			$arrFieldName[]=db_field_name($res, $i);
		}

		$fn=$_SESSION['csvUploadFile'];
		if(!$fp=fopen($fn, 'r'))   return "Can't open csv file '$fn'.";

		if($_POST['csvFieldNamesOnTop']==1){
			// read first line
			if(!$arr=fgetcsv($fp, $csvCharsPerLine, $csvFieldSeparator, $csvFieldDelimiter)){
				fclose($fp);
				return "The csv file '$fn' is empty.";
			}
			if(lineHasFieldNames($arr, $tn)){
				$arrCSVData[0]=arrayResize($arr, count($arrFieldName));
				// skip n+start rows
				for($i=0; $i<$csvIgnoreNRows+$start; $i++){
					if(!fgets($fp)){
						fclose($fp);
						return $arrCSVData;
					}
				}
				echo '<!-- getCSVArray: line '.__LINE__.' -->';
			}else{
				if($csvIgnoreNRows>0){
					// skip n-1 rows
					for($i=1; $i<$csvIgnoreNRows; $i++){
						if(!fgets($fp)){
							fclose($fp);
							return "The csv file '$fn' has no data to read.";
						}
					}
					echo '<!-- getCSVArray: line '.__LINE__.' -->';
					// read one line
					if(!$arr=fgetcsv($fp, $csvCharsPerLine, $csvFieldSeparator, $csvFieldDelimiter)){
						fclose($fp);
						return "The csv file '$fn' has no data to read.";
					}
					if(lineHasFieldNames($arr, $tn)){
						$arrCSVData[0]=arrayResize($arr, count($arrFieldName));
						// skip $start rows
						for($i=0; $i<$start; $i++){
							if(!fgets($fp)){
								fclose($fp);
								return $arrCSVData;
							}
						}
						echo '<!-- getCSVArray: line '.__LINE__.' -->';
					}else{
						// warning! no field names found
						// assume default field order
						$arrCSVData[0]=$arrFieldName;
						// add previously-read line, or ignore it
						if(!$start){
							$arrCSVData[]=arrayResize($arr, count($arrFieldName));
							$numRows--;
							echo '<!-- getCSVArray: line '.__LINE__.' -->';
						}else{
							// skip $start rows
							for($i=0; $i<$start-1; $i++){
								if(!fgets($fp)){
									fclose($fp);
									return $arrCSVData;
								}
							}
							echo '<!-- getCSVArray: line '.__LINE__.' -->';
						}
					}
				}else{
					// warning! no field names found
					// assume default field order
					$arrCSVData[0]=$arrFieldName;
					$arrCSVData[]=arrayResize($arr, count($arrFieldName));
					$numRows--;
					// skip $start rows
					for($i=0; $i<$start; $i++){
						if(!fgets($fp)){
							fclose($fp);
							return $arrCSVData;
						}
					}
					echo '<!-- getCSVArray: line '.__LINE__.' -->';
				}
			}
		}else{
			// skip n+start rows
			for($i=0; $i<$csvIgnoreNRows+$start; $i++){
				if(!fgets($fp)){
					fclose($fp);
					return $arrCSVData;
				}
			}
			echo '<!-- getCSVArray: line '.__LINE__.' -->';
			// assume default field order
			$arrCSVData[0]=$arrFieldName;
		}

		// fetch data
		$i=0;
		while(($arr=fgetcsv($fp, $csvCharsPerLine, $csvFieldSeparator, $csvFieldDelimiter)) && $i<$numRows){
			$arr=arrayResize($arr, count($arrCSVData[0]));
			$arrCSVData[0]=arrayResize($arrCSVData[0], count($arr));
			foreach($arr as $k => $v){
				$arr[$k] = ($makeSafe ? makeSafe($v) : $v);
			}
			$arrCSVData[]=$arr;
			$i++;
		}

		fclose($fp);
		return $arrCSVData;
	}
	##########################################################################
	function lineHasFieldNames($arr, $table){
		if(!is_array($arr)){
			#echo '<!-- lineHasFieldNames: line '.__LINE__.' -->';
			return false;
		}

		// get field names of table
		$res=sql('select * from `'.$table.'` limit 1', $eo);
		for($i=0; $i<db_num_fields($res); $i++){
			$arrTableFieldName[]=db_field_name($res, $i);
		}

		$arrCommon=array_intersect($arrTableFieldName, noSpaces($arr));
		//echo '<!-- lineHasFieldNames: arrTableFieldName: '.count($arrTableFieldName).' -->';
		//echo '<!-- lineHasFieldNames: arr: '.count($arr).' -->';
		//echo '<!-- lineHasFieldNames: arrCommon: '.count($arrCommon).' -->';
		return (count($arrCommon) < count($arr) ? false : true);
	}
	##########################################################################
	function noSpaces($arr){
		$cArr=count($arr);
		for($i=0; $i<$cArr; $i++){
			$arr[$i]=str_replace(' ', '', $arr[$i]);
		}
		return $arr;
	}
	##########################################################################
	function arrayResize($arr, $size){
		if(count($arr)<$size){
			return $arr;
		}elseif(count($arr)>$size){
			array_splice($arr, $size);
			return $arr;
		}else{
			return $arr;
		}
	}
	##########################################################################
	function getCSVSettings(&$csvIgnoreNRows, &$csvCharsPerLine, &$csvFieldSeparator, &$csvFieldDelimiter, &$csvFieldNamesOnTop, &$csvUpdateIfPKExists, &$csvBackupBeforeImport){
		if(count($_POST)){
			$csvIgnoreNRows=intval($_POST['csvIgnoreNRows']);
			if($csvIgnoreNRows<0)  $csvIgnoreNRows=0;

			$csvCharsPerLine=intval($_POST['csvCharsPerLine']);
			if($csvCharsPerLine<1000)  $csvCharsPerLine=1000;

			$csvFieldSeparator=(get_magic_quotes_gpc() ? stripslashes($_POST['csvFieldSeparator']) : $_POST['csvFieldSeparator']);
			if($csvFieldSeparator=='') $csvFieldSeparator=',';

			$csvFieldDelimiter=(get_magic_quotes_gpc() ? stripslashes($_POST['csvFieldDelimiter']) : $_POST['csvFieldDelimiter']);
			if($csvFieldDelimiter=='') $csvFieldDelimiter='"';

			$csvFieldNamesOnTop=($_POST['csvFieldNamesOnTop'] ? 1 : 0);
			$csvUpdateIfPKExists=($_POST['csvUpdateIfPKExists'] ? 1 : 0);
			$csvBackupBeforeImport=($_POST['csvBackupBeforeImport'] ? 1 : 0);
		}else{
			$csvIgnoreNRows=0;
			$csvCharsPerLine=10000;
			$csvFieldSeparator=',';
			$csvFieldDelimiter='"';
			$csvFieldNamesOnTop=1;
			$csvUpdateIfPKExists=0;
			$csvBackupBeforeImport=1;
		}
	}
	##########################################################################
	function advancedCSVSettingsForm(){
		getCSVSettings($csvIgnoreNRows, $csvCharsPerLine, $csvFieldSeparator, $csvFieldDelimiter, $csvFieldNamesOnTop, $csvUpdateIfPKExists, $csvBackupBeforeImport);
		ob_start();
		?>
		<div style="display: none;" id="advancedOptions">
		<table class="table table-striped">
			<tr>
				<td align="right" class="tdFormCaption" valign="top" width="250">
					<div class="formFieldCaption">Field separator</div>
					</td>
				<td align="left" class="tdFormInput">
					<input type="text" name="csvFieldSeparator" class="formTextBox" value="<?php echo htmlspecialchars($csvFieldSeparator); ?>" size="2"> <i>The default is comma (,)</i>
					</td>
				</tr>
			<tr>
				<td align="right" class="tdFormCaption" valign="top">
					<div class="formFieldCaption">Field delimiter</div>
					</td>
				<td align="left" class="tdFormInput">
					<input type="text" name="csvFieldDelimiter" class="formTextBox" value="<?php echo htmlspecialchars($csvFieldDelimiter); ?>" size="2"> <i>The default is double-quote (")</i>
					</td>
				</tr>
			<tr>
				<td align="right" class="tdFormCaption" valign="top">
					<div class="formFieldCaption">Maximum characters per line</div>
					</td>
				<td align="left" class="tdFormInput">
					<input type="text" name="csvCharsPerLine" class="formTextBox" value="<?php echo intval($csvCharsPerLine); ?>" size="6"> <i>If you have trouble importing the CSV file, try increasing this value.</i>
					</td>
				</tr>
			<tr>
				<td align="right" class="tdFormCaption" valign="top">
					<div class="formFieldCaption">Number of lines to ignore</div>
					</td>
				<td align="left" class="tdFormInput">
					<input type="text" name="csvIgnoreNRows" class="formTextBox" value="<?php echo intval($csvIgnoreNRows); ?>" size="8"> <i>Change this value if you want to skip a specific number of lines in the CSV file.</i>
					</td>
				</tr>
			<tr>
				<td align="right" class="tdFormCaption" valign="top">
					<div class="formFieldCaption"><input type="checkbox" id="csvFieldNamesOnTop" name="csvFieldNamesOnTop" value="1" <?php echo ($csvFieldNamesOnTop ? 'checked' : ''); ?>></div>
					</td>
				<td align="left" class="tdFormInput">
					<label for="csvFieldNamesOnTop">The first line of the file contains field names</label>
					<br><i>Field names must <b>exactly</b> match those in the database.</i>
					</td>
				</tr>
			<tr>
				<td align="right" class="tdFormCaption" valign="top">
					<div class="formFieldCaption"><input type="checkbox" id="csvUpdateIfPKExists" name="csvUpdateIfPKExists" value="1" <?php echo ($csvUpdateIfPKExists ? 'checked' : ''); ?>></div>
					</td>
				<td align="left" class="tdFormInput">
					<label for="csvUpdateIfPKExists">Update table records if their primary key values match those in the CSV file.</label>
					<br><i>If not checked, records in the CSV file having the same primary key values as those in the table <b>will be ignored</b></i>
					</td>
				</tr>
			<tr>
				<td align="right" class="tdFormCaption" valign="top">
					<div class="formFieldCaption"><input type="checkbox" id="csvBackupBeforeImport" name="csvBackupBeforeImport" value="1" <?php echo ($csvBackupBeforeImport ? 'checked' : ''); ?>></div>
					</td>
				<td align="left" class="tdFormInput">
					<label for="csvBackupBeforeImport">Back up the table before importing CSV data into it.</label>
					</td>
				</tr>
			</table>
			</div>
		<?php
		$out=ob_get_contents();
		ob_end_clean();

		return $out;
	}
?>