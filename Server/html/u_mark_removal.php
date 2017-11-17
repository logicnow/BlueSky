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
<body>
<form action="u_save_removal.php" method="POST">
<div class="row">
<div class="col-md-8 col-lg-10" id="computers_dv_form">
			<fieldset class="form-horizontal">
			<input name="selected_ids" type="hidden" value="<?php echo $_REQUEST["selected_ids"];?>">
				
				<div class="form-group">
					<div class="col-lg-offset-3 col-lg-9">
						<div class="checkbox"><label for="selfdestruct"><input tabindex="1" type="checkbox" name="selfdestruct" id="selfdestruct" value="1" > Remove BlueSky (Cannot Undo From Here)</label>
							<button class="btn btn-info btn-xs" type="button" data-toggle="collapse" tabindex="-1" data-target="#selfdestruct-description"><i class="glyphicon glyphicon-info-sign"></i></button>
							<span class="help-block collapse" id="selfdestruct-description"><div class="alert alert-info">If you check this box and save changes, BlueSky will uninstall itself from this host after its next check-in.  Unchecking the box will make no difference as the computer will not contact the server again without a reinstall.</div></span>
						</div>
					</div>
				</div>

			</fieldset>
		</div>

		<div class="col-md-4 col-lg-2" id="computers_dv_action_buttons">
			<div class="btn-toolbar">
				<div class="btn-group-vertical btn-group-lg" style="width: 100%;">
					<button tabindex="2" type="submit" class="btn btn-success btn-lg" id="update" name="update_x" value="1" ><i class="glyphicon glyphicon-ok"></i> Save Changes</button>
				</div><p></p>
				
					
				</div>
			</div>
		</div>
		</div>
		</form>
		<script>
		jQuery(function(){
			jQuery('select, input[type=text], textarea').not(':disabled').eq(0).focus();
			jQuery('form').eq(0).change(function(){
				if(jQuery(this).data('already_changed')) return;
				if(jQuery('#deselect').length) jQuery('#deselect').removeClass('btn-default').addClass('btn-warning').get(0).lastChild.data = " Cancel";
				jQuery(this).data('already_changed', true);
			});

			jQuery('a[href="./images/"]').click(function(){ return false; });
		});

		document.observe("dom:loaded", function() {
			/* when no record is selected ('add new' mode) */
			if($$('input[name=SelectedID]')[0].value==''){
				/* hide links to children tables */
				$$('.detail_view a[id]').findAll(function(cl){ return cl.id.match(/_link$/); }).invoke('hide');
				/* skip loading parent/children view */
				return false;
			}
			post(
				'parent-children.php', {
					ParentTable: 'computers',
					SelectedID: '26',
					Operation: 'show-children'
				},
				'computers-children'
			);
		});
	</script>

<script>jQuery(function(){
	jQuery('#computers_link').removeClass('hidden');
	jQuery('#xs_computers_link').removeClass('hidden');
	jQuery('[id^="computers_plink"]').removeClass('hidden');
	jQuery('form').eq(0).data('already_changed', true);	jQuery('form').eq(0).data('already_changed', false);
});</script>
<script>document.observe('dom:loaded', function() {});</script>
	<script>
		// initial lookup values
		var current_client = { text: "", value: ""};
		
		jQuery(function() {
			client_reload();
		});
		function client_reload(){
		
			jQuery("#client-container").select2({
				/* initial default value */
				initSelection: function(e, c){
					jQuery.ajax({
						url: 'ajax_combo.php',
						dataType: 'json',
						data: { id: current_client.value, t: 'computers', f: 'client' }
					}).done(function(resp){
						c({
							id: resp.results[0].id,
							text: resp.results[0].text
						});
						jQuery('[name="client"]').val(resp.results[0].id);
						jQuery('[id=client-container-readonly]').html('<span id="client-match-text">' + resp.results[0].text + '</span>');


						if(typeof(client_update_autofills) == 'function') client_update_autofills();
					});
				},
				width: '100%',
				formatNoMatches: function(term){ return 'No matches found!'; },
				minimumResultsForSearch: 10,
				loadMorePadding: 200,
				ajax: {
					url: 'ajax_combo.php',
					dataType: 'json',
					cache: true,
					data: function(term, page){ return { s: term, p: page, t: 'computers', f: 'client' }; },
					results: function(resp, page){ return resp; }
				}
			}).on('change', function(e){
				current_client.value = e.added.id;
				current_client.text = e.added.text;
				jQuery('[name="client"]').val(e.added.id);


				if(typeof(client_update_autofills) == 'function') client_update_autofills();
			});
		
		}
	</script>
</body>
</html>