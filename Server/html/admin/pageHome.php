<?php
	$currDir=dirname(__FILE__);
	require("$currDir/incCommon.php");
	include("$currDir/incHeader.php");
?>

<?php
	if(!sqlValue("select count(1) from membership_groups where allowSignup=1")){
		$noSignup=TRUE;
		?>
		<div class="alert alert-info">
			<i>Attention!</i>
			<br><a href="../membership_signup.php" target="_blank">Visitor sign up</a> 
			is disabled because there are no groups where visitors can sign up currently.
			To enable visitor sign-up, set at least one group to allow visitor sign-up.
			</div>
		<?php
	}
?>

<?php
	// get the count of records having no owners in each table
	$arrTables=getTableList();

	foreach($arrTables as $tn=>$tc){
		$countOwned=sqlValue("select count(1) from membership_userrecords where tableName='$tn' and not isnull(groupID)");
		$countAll=sqlValue("select count(1) from `$tn`");

		if($countAll>$countOwned){
			?>
			<div class="alert alert-info">
				You have data in one or more tables that doesn't have an owner.
				To assign an owner group for this data, <a href="pageAssignOwners.php">click here</a>.
				</div>
			<?php
			break;
		}
	}
?>

<div class="page-header"><h1>Membership Management Homepage</h1></div>

<?php if(!$adminConfig['hide_twitter_feed']){ ?>
	<div class="row" id="outer-row"><div class="col-md-8">
<?php } ?>

<div class="row" id="inner-row">

<!-- ################# Newest Updates ######################## -->
<div class="col-md-6">
<div class="panel panel-info">
	<div class="panel-heading">
		<h3 class="panel-title">Newest Updates <a class="btn btn-default btn-sm" href="pageViewRecords.php?sort=dateUpdated&sortDir=desc"><i class="glyphicon glyphicon-chevron-right"></i></a></h3>
	</div>
	<div class="panel-body">
	<table class="table table-striped">
	<?php
		$res=sql("select tableName, pkValue, dateUpdated, recID from membership_userrecords order by dateUpdated desc limit 5", $eo);
		while($row=db_fetch_row($res)){
			?>
			<tr>
				<td class="tdCaptionCell"><?php echo @date($adminConfig['PHPDateTimeFormat'], $row[2]); ?></td>
				<td class="tdCell" align="left"><a href="pageEditOwnership.php?recID=<?php echo $row[3]; ?>"><img src="images/data_icon.gif" border="0" alt="View record details" title="View record details"></a> <?php echo substr(getCSVData($row[0], $row[1]), 0, 15); ?> ...</td>
				</tr>
			<?php
		}
	?>
	</table>
	</div>
</div>
</div>
<!-- ####################################################### -->


<!-- ################# Newest Entries ######################## -->
<div class="col-md-6">
<div class="panel panel-info">
	<div class="panel-heading">
		<h3 class="panel-title">Newest Entries <a class="btn btn-default btn-sm" href="pageViewRecords.php?sort=dateAdded&sortDir=desc"><i class="glyphicon glyphicon-chevron-right"></i></a></h3>
	</div>
	<div class="panel-body">
	<table class="table table-striped">
	<?php
		$res=sql("select tableName, pkValue, dateAdded, recID from membership_userrecords order by dateAdded desc limit 5", $eo);
		while($row=db_fetch_row($res)){
			?>
			<tr>
				<td class="tdCaptionCell"><?php echo @date($adminConfig['PHPDateTimeFormat'], $row[2]); ?></td>
				<td class="tdCell" align="left"><a href="pageEditOwnership.php?recID=<?php echo $row[3]; ?>"><img src="images/data_icon.gif" border="0" alt="View record details" title="View record details"></a> <?php echo substr(getCSVData($row[0], $row[1]), 0, 15); ?> ...</td>
				</tr>
			<?php
		}
	?>
	</table>
	</div>
</div>
</div>
<!-- ####################################################### -->


<!-- ################# Add-ons available ######################## -->
	<?php
		// do we have a cache file that was recently updated?
		$addOnsCache = "$currDir/add-ons.cache";
		$addOnXML = '';
		if(is_file($addOnsCache) && filemtime($addOnsCache) >= (time() - 86400 * 2)){
			// read feed from cache
			$addOnXML = @file_get_contents($addOnsCache);
		}else{
			// read live feed and store to cache
			$addOnXML = @file_get_contents('http://bigprof.com/appgini/taxonomy/term/6/0/feed');
			@file_put_contents($addOnsCache, $addOnXML);
			clearstatcache();
		}

		$xml = @simplexml_load_string($addOnXML);
		if(count($xml->channel->item)){
			?>
		<div class="col-md-6">
		<div class="panel panel-info">
			<div class="panel-heading">
				<h3 class="panel-title">Available add-ons</h3>
			</div>
			<div class="panel-body">
			<table class="table table-striped">
			<?php
				$addOnId = 0;
				foreach($xml->channel->item as $indx => $data){
					$addOnId++; if($addOnId > 10) break;
					?>
					<tr>
						<td>
							<?php echo (strtotime($data->pubDate) > (@time() - 60 * 24 * 60 * 60) ? '<img src="../new.png" align="top" /> ' : ''); ?><a href="#" onclick="return showDialog('add-on-<?php echo $addOnId; ?>');"><?php echo $data->title; ?></a><br/>
							<div class="dialog-box hidden-block" id="add-on-<?php echo $addOnId; ?>">
								<h3><a href="<?php echo $data->link; ?>" target="_blank"><?php echo $data->title; ?></a></h3>
								<p><?php echo $data->description; ?></p>
								<div align="right">
									[<a href="<?php echo $data->link; ?>" target="_blank">More info</a>]
									[<a onclick="return hideDialogs();" href="#" target="_blank">Close</a>]
								</div>
							</div>
						</td>
					</tr>
					<?php
				}
			?>
				<tr><td class="text-center"><a href="http://bigprof.com/appgini/add-ons" target="_blank">View all add-ons</a></td></tr>
			</table>
			</div>
		</div>
		</div>
			<?php
		}
	?>
<!-- ####################################################### -->


<!-- ################# Top Members ######################## -->
<div class="col-md-6">
<div class="panel panel-info">
	<div class="panel-heading">
		<h3 class="panel-title">Top Members</h3>
	</div>
	<div class="panel-body">
	<table class="table table-striped">
	<?php
		$res=sql("select lcase(memberID), count(1) from membership_userrecords group by memberID order by 2 desc limit 5", $eo);
		while($row=db_fetch_row($res)){
			?>
			<tr>
				<td class="tdCaptionCell" align="left"><a href="pageEditMember.php?memberID=<?php echo urlencode($row[0]); ?>"><img src="images/edit_icon.gif" border="0" alt="Edit member details" title="Edit member details"></a> <?php echo $row[0]; ?></td>
				<td class="tdCell"><a href="pageViewRecords.php?memberID=<?php echo urlencode($row[0]); ?>"><img src="images/data_icon.gif" border="0" alt="View member's data records" title="View member's data records"></a> <?php echo $row[1]; ?> records</td>
				</tr>
			<?php
		}
	?>
	</table>
	</div>
</div>
</div>
<!-- ####################################################### -->


<!-- ################# Members Stats ######################## -->
<div class="col-md-6">
<div class="panel panel-info">
	<div class="panel-heading">
		<h3 class="panel-title">Members Stats</h3>
	</div>
	<div class="panel-body">
	<table class="table table-striped">
		<tr>
			<td class="tdCaptionCell">Total groups</td>
			<td class="tdCell"><a href="pageViewGroups.php"><img src="images/view_icon.gif" border="0" alt="View groups" title="View groups"></a> <?php echo sqlValue("select count(1) from membership_groups"); ?></td>
			</tr>
		<tr>
			<td class="tdCaptionCell">Active members</td>
			<td class="tdCell"><a href="pageViewMembers.php?status=2"><img src="images/view_icon.gif" border="0" alt="View active members" title="View active members"></a> <?php echo sqlValue("select count(1) from membership_users where isApproved=1 and isBanned=0"); ?></td>
			</tr>
		<tr>
			<?php
				$awaiting = intval(sqlValue("select count(1) from membership_users where isApproved=0"));
			?>
			<td class="tdCaptionCell" <?php echo ($awaiting ? "style=\"color: red;\"" : ""); ?>>Members awaiting approval</td>
			<td class="tdCell"><a href="pageViewMembers.php?status=1"><img src="images/view_icon.gif" border="0" alt="View members awaiting approval" title="View members awaiting approval"></a> <?php echo $awaiting; ?></td>
			</tr>
		<tr>
			<td class="tdCaptionCell">Banned members</td>
			<td class="tdCell"><a href="pageViewMembers.php?status=3"><img src="images/view_icon.gif" border="0" alt="View banned members" title="View banned members"></a> <?php echo sqlValue("select count(1) from membership_users where isApproved=1 and isBanned=1"); ?></td>
			</tr>
		<tr>
			<td class="tdCaptionCell">Total members</td>
			<td class="tdCell"><a href="pageViewMembers.php"><img src="images/view_icon.gif" border="0" alt="View all members" title="View all members"></a> <?php echo sqlValue("select count(1) from membership_users"); ?></td>
			</tr>
		</table>
	</div>
</div>
</div>
<!-- ####################################################### -->

</div> <!-- /div.row#inner-row -->

<?php if(!$adminConfig['hide_twitter_feed']){ ?>
		</div> <!-- /div.col-md-8 -->

		<div class="col-md-4" id="twitter-feed">
			<h3>
				Tweets By BigProf Software
				<span class="pull-right">
					<a class="twitter-follow-button" href="https://twitter.com/bigprof" data-show-count="false" data-lang="en">Follow @bigprof</a>
					<script type="text/javascript">
						window.twttr = (function (d, s, id) {
							var t, js, fjs = d.getElementsByTagName(s)[0];
							if (d.getElementById(id)) return;
							js = d.createElement(s); js.id = id;
							js.src= "https://platform.twitter.com/widgets.js";
							fjs.parentNode.insertBefore(js, fjs);
							return window.twttr || (t = { _e: [], ready: function (f) { t._e.push(f) } });
						}(document, "script", "twitter-wjs"));
					</script>
				</span>
			</h3><hr>
			<div class="text-center">
				<a class="twitter-timeline" height="400" href="https://twitter.com/bigprof" data-widget-id="552758720300843008" data-chrome="nofooter noheader">Loading @bigprof feed ...</a>
				<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+"://platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
			</div>
			<div class="text-right hidden" id="remove-feed-link"><a href="pageSettings.php#hide_twitter_feed"><i class="glyphicon glyphicon-remove"></i> Remove this feed</a></div>
			<script>
				$j(function(){
					show_remove_feed_link = function(){
						if(!$j('.twitter-timeline-rendered').length){
							setTimeout(function(){ show_remove_feed_link(); }, 1000);
						}else{
							$j('#remove-feed-link').removeClass('hidden');
						}
					};
					show_remove_feed_link();
				});
			</script>
		</div>
	</div> <!-- /div.row#outer-row -->
<?php } ?>


<?php
	include("$currDir/incFooter.php");
?>
