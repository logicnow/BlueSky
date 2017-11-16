<?php
	$currDir=dirname(__FILE__);
	require("$currDir/incCommon.php");
	include("$currDir/incHeader.php");

	// image paths
	$p=array(   
	);

	if(!count($p)) exit;

	// validate input
	$t=$_GET['table'];
	if(!in_array($t, array_keys($p))){
		?>
		<div class="page-header"><h1>Rebuild thumbnails</h1></div>
		<form method="get" action="pageRebuildThumbnails.php" target="_blank">
			Use this utility if you have one or more image fields in a table that don't have thumbnails or
			have thumbnails with incorrect dimensions.<br><br>

			<b>Rebuild thumbnails of table</b> 
			<?php echo htmlSelect('table', array_keys($p), array_keys($p), ''); ?>
			<input type="submit" value="Rebuild">
		</form>


		<?php
		include("$currDir/incFooter.php");
		exit;
	}

	?>
	<div class="page-header"><h1>Rebuilding thumbnails of '<i><?php echo $t; ?></i>' table ...</h1></div>
	Don't close this page until you see a confirmation message that all thumbnails have been built.<br><br>
	<div style="font-weight: bold; color: red; width:700px;" id="status">Status: still rebuilding thumbnails, please wait ...</div>
	<br>

	<div style="text-align:left; padding: 0 5px; width:700px; height:250px;overflow:auto; border: solid 1px green;">
	<?php
		foreach($p[$t] as $f=>$path){
			$res=sql("select `$f` from `$t`", $eo);
			echo "Building thumbnails for '<i>$f</i>' field...<br>";
			unset($tv); unset($dv);
			while($row=db_fetch_row($res)){
				if($row[0]!=''){
					$tv[]=$row[0];
					$dv[]=$row[0];
				}
			}
			for($i=0; $i<count($tv); $i++){
				if($i && !($i%4))  echo '<br style="clear: left;">';
				echo '<img src="../thumbnail.php?t='.$t.'&f='.$f.'&i='.$tv[$i].'&v=tv" align="left" style="margin: 10px 10px;"> ';
			}
			echo '<br style="clear: left;">';

			for($i=0; $i<count($dv); $i++){
				if($i && !($i%4))  echo '<br style="clear: left;">';
				echo '<img src="../thumbnail.php?t='.$t.'&f='.$f.'&i='.$tv[$i].'&v=dv" align="left" style="margin: 10px 10px;"> ';
			}
			echo '<br style="clear: left;">Done.<br><br>';
		}
	?>
	</div>

	<script>
		window.onload=function(){
			document.getElementById('status').innerHTML='Status: finished. You can close this page now.';
			document.getElementById('status').style.color='green';
			document.getElementById('status').style.fontSize='25px';
			document.getElementById('status').style.backgroundColor='#fff4cf';
		}
	</script>

<?php
	include("$currDir/incFooter.php");