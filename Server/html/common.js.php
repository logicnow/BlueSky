<?php
	@header("Content-Type: text/javascript; charset=UTF-8");
	$currDir=dirname(__FILE__);
	include("$currDir/defaultLang.php");
	include("$currDir/language.php");
?>
/* initials and fixes */
jQuery(function(){
	$j(window).resize(function(){
		var window_width = $j(window).width();
		var max_width = $j('body').width() * 0.5;

		if($j('fieldset .col-xs-11').length) max_width = $j('fieldset .col-xs-11').width() - 99;
		$j('.select2-container:not(.option_list)').css({ 'max-width' : max_width + 'px', 'width': '100%' });
		fix_table_responsive_width();

		var full_img_factor = 0.9; /* xs */
		if(window_width >= 992) full_img_factor = 0.6; /* md, lg */
		else if(window_width >= 768) full_img_factor = 0.9; /* sm */

		$j('.detail_view .img-responsive').css({'max-width' : parseInt($j('.detail_view').width() * full_img_factor) + 'px'});
	});

	setTimeout(function(){ $j(window).resize(); }, 1000);
	setTimeout(function(){ $j(window).resize(); }, 3000);

	/* don't allow saving detail view while an ajax request is in progress */
	jQuery(document).ajaxStart(function(){ jQuery('#update, #insert').prop('disabled', true); });
	jQuery(document).ajaxStop(function(){ jQuery('#update, #insert').prop('disabled', false); });

	/* don't allow responsive images to initially exceed the smaller of their actual dimensions, or .6 container width */
	jQuery('.detail_view .img-responsive').each(function(){
		 var pic_real_width, pic_real_height;
		 var img = jQuery(this);
		 jQuery('<img/>') // Make in memory copy of image to avoid css issues
				.attr('src', img.attr('src'))
				.load(function() {
					pic_real_width = this.width;
					pic_real_height = this.height;

					if(pic_real_width > $j('.detail_view').width() * .6) pic_real_width = $j('.detail_view').width() * .6;
					img.css({ "max-width": pic_real_width });
				});
	});

	jQuery('.table-responsive .img-responsive').each(function(){
		 var pic_real_width, pic_real_height;
		 var img = jQuery(this);
		 jQuery('<img/>') // Make in memory copy of image to avoid css issues
				.attr('src', img.attr('src'))
				.load(function() {
					pic_real_width = this.width;
					pic_real_height = this.height;

					if(pic_real_width > $j('.table-responsive').width() * .6) pic_real_width = $j('.table-responsive').width() * .6;
					img.css({ "max-width": pic_real_width });
				});
	});

	/* toggle TV action buttons based on selected records */
	jQuery('.record_selector').click(function(){
		var id = jQuery(this).val();
		var checked = jQuery(this).prop('checked');
		update_action_buttons();
	});

	/* select/deselect all records in TV */
	jQuery('#select_all_records').click(function(){
		jQuery('.record_selector').prop('checked', jQuery(this).prop('checked'));
		update_action_buttons();
	});

	/* fix behavior of select2 in bootstrap modal. See: https://github.com/ivaynberg/select2/issues/1436 */
	jQuery.fn.modal.Constructor.prototype.enforceFocus = function(){};

	update_action_buttons();
});

/* show/hide TV action buttons based on whether records are selected or not */
function update_action_buttons(){
	if(jQuery('.record_selector:checked').length){
		jQuery('.selected_records').removeClass('hidden');
		jQuery('#select_all_records')
			.prop('checked', (jQuery('.record_selector:checked').length == jQuery('.record_selector').length));
	}else{
		jQuery('.selected_records').addClass('hidden');
	}
}

/* fix table-responsive behavior on Chrome */
function fix_table_responsive_width(){
	var resp_width = jQuery('div.table-responsive').width();
	var table_width;

	if(resp_width){
		jQuery('div.table-responsive table').width('100%');
		table_width = jQuery('div.table-responsive table').width();
		resp_width = jQuery('div.table-responsive').width();
		if(resp_width == table_width){
			jQuery('div.table-responsive table').width(resp_width - 1);
		}
	}
}

function computers_validateData(){
	$j('.has-error').removeClass('has-error');
	if($j('#blueskyid').val() == ''){ modal_window({ message: '<div class="alert alert-danger"><?php echo addslashes($Translation['field not null']); ?></div>', title: "<?php echo addslashes($Translation['error:']); ?> BlueSky ID", close: function(){ $j('[name=blueskyid]').focus(); $j('[name=blueskyid]').parents('.form-group').addClass('has-error'); } }); return false; };
	return true;
}
function global_validateData(){
	$j('.has-error').removeClass('has-error');
	return true;
}
function connections_validateData(){
	$j('.has-error').removeClass('has-error');
	return true;
}
function post(url, params, update, disable, loading){
	new Ajax.Request(
		url, {
			method: 'post',
			parameters: params,
			onCreate: function() {
				if($(disable) != undefined) $(disable).disabled=true;
				if($(loading) != undefined && update != loading) $(loading).update('<div style="direction: ltr;"><img src="loading.gif"> <?php echo $Translation['Loading ...']; ?></div>');
			},
			onSuccess: function(resp) {
				if($(update) != undefined) $(update).update(resp.responseText);
			},
			onComplete: function() {
				if($(disable) != undefined) $(disable).disabled=false;
				if($(loading) != undefined && loading != update) $(loading).update('');
			}
		}
	);
}
function post2(url, params, notify, disable, loading, redirectOnSuccess){
	new Ajax.Request(
		url, {
			method: 'post',
			parameters: params,
			onCreate: function() {
				if($(disable) != undefined) $(disable).disabled=true;
				if($(loading) != undefined) $(loading).show();
			},
			onSuccess: function(resp) {
				/* show notification containing returned text */
				if($(notify) != undefined) $(notify).removeClassName('Error').appear().update(resp.responseText);

				/* in case no errors returned, */
				if(!resp.responseText.match(/<?php echo $Translation['error:']; ?>/)){
					/* redirect to provided url */
					if(redirectOnSuccess != undefined){
						window.location=redirectOnSuccess;

					/* or hide notification after a few seconds if no url is provided */
					}else{
						if($(notify) != undefined) window.setTimeout(function(){ $(notify).fade(); }, 15000);
					}

				/* in case of error, apply error class */
				}else{
					$(notify).addClassName('Error');
				}
			},
			onComplete: function() {
				if($(disable) != undefined) $(disable).disabled=false;
				if($(loading) != undefined) $(loading).hide();
			}
		}
	);
}
function passwordStrength(password, username){
	// score calculation (out of 10)
	var score = 0;
	re = new RegExp(username, 'i');
	if(username.length && password.match(re)) score -= 5;
	if(password.length < 6) score -= 3;
	else if(password.length > 8) score += 5;
	else score += 3;
	if(password.match(/(.*[0-9].*[0-9].*[0-9])/)) score += 3;
	if(password.match(/(.*[!,@,#,$,%,^,&,*,?,_,~].*[!,@,#,$,%,^,&,*,?,_,~])/)) score += 5;
	if(password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) score += 2;

	if(score >= 9)
		return 'strong';
	else if(score >= 5)
		return 'good';
	else
		return 'weak';
}
function validateEmail(email) { 
	var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
	return re.test(email);
}
function loadScript(jsUrl, cssUrl, callback){
	// adding the script tag to the head
	var head = document.getElementsByTagName('head')[0];
	var script = document.createElement('script');
	script.type = 'text/javascript';
	script.src = jsUrl;

	if(cssUrl != ''){
		var css = document.createElement('link');
		css.href = cssUrl;
		css.rel = "stylesheet";
		css.type = "text/css";
		head.appendChild(css);
	}

	// then bind the event to the callback function 
	// there are several events for cross browser compatibility
	if(script.onreadystatechange != undefined){ script.onreadystatechange = callback; }
	if(script.onload != undefined){ script.onload = callback; }

	// fire the loading
	head.appendChild(script);
}
/**
 * options object. The following members can be provided:
 *    url: iframe url to load
 *    message: instead of a url to open, you could pass a message. HTML tags allowed.
 *    id: id attribute of modal window
 *    title: optional modal window title
 *    size: 'default', 'full'
 *    close: optional function to execute on closing the modal
 *    footer: optional array of objects describing the buttons to display in the footer.
 *       Each button object can have the following members:
 *          label: string, label of button
 *          bs_class: string, button bootstrap class. Can be 'primary', 'default', 'success', 'warning' or 'danger'
 *          click: function to execute on clicking the button. If the button closes the modal, this
 *                 function is executed before the close handler
 *          causes_closing: boolean, default is true.
 */
function modal_window(options){
	var id = options.id;
	var url = options.url;
	var title = options.title;
	var footer = options.footer;
	var message = options.message;

	if(typeof(id) == 'undefined') id = random_string(20);
	if(typeof(footer) == 'undefined') footer = [];

	if(jQuery('#' + id).length){
		/* modal exists -- remove it first */
		jQuery('#' + id).remove();
	}

	/* prepare footer buttons, if any */
	var footer_buttons = '';
	for(i = 0; i < footer.length; i++){
		if(typeof(footer[i].causes_closing) == 'undefined'){ footer[i].causes_closing = true; }
		if(typeof(footer[i].bs_class) == 'undefined'){ footer[i].bs_class = 'default'; }
		footer[i].id = id + '_footer_button_' + random_string(10);

		footer_buttons += '<button type="button" class="btn btn-' + footer[i].bs_class + '" ' +
				(footer[i].causes_closing ? 'data-dismiss="modal" ' : '') +
				'id="' + footer[i].id + '" ' +
				'>' + footer[i].label + '</button>';
	}

	jQuery('body').append(
		'<div class="modal fade" id="' + id + '" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">' +
			'<div class="modal-dialog">' +
				'<div class="modal-content">' +
					( title != undefined ?
						'<div class="modal-header">' +
							'<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>' +
							'<h3 class="modal-title" id="myModalLabel">' + title + '</h3>' +
						'</div>'
						: ''
					) +
					'<div class="modal-body" style="-webkit-overflow-scrolling:touch !important; overflow-y: auto;">' +
						( url != undefined ?
							'<iframe width="100%" height="100%" sandbox="allow-forms allow-scripts allow-same-origin allow-popups" src="' + url + '"></iframe>'
							: message
						) +
					'</div>' +
					( footer != undefined ?
						'<div class="modal-footer">' + footer_buttons + '</div>'
						: ''
					) +
				'</div>' +
			'</div>' +
		'</div>'
	);

	for(i = 0; i < footer.length; i++){
		if(typeof(footer[i].click) == 'function'){
			jQuery('#' + footer[i].id).click(footer[i].click);
		}
	}

	jQuery('#' + id).modal();

	if(typeof(options.close) == 'function'){
		jQuery('#' + id).on('hidden.bs.modal', options.close);
	}

	if(typeof(options.size) == 'undefined') options.size = 'default';

	if(options.size == 'full'){
		jQuery(window).resize(function(){
			jQuery('#' + id + ' .modal-dialog').width(jQuery(window).width() * 0.95);
			jQuery('#' + id + ' .modal-body').height(jQuery(window).height() * 0.7);
		}).trigger('resize');
	}

	return id;
}

function random_string(string_length){
	var text = "";
	var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

	for(var i = 0; i < string_length; i++)
		text += possible.charAt(Math.floor(Math.random() * possible.length));

	return text;
}

function get_selected_records_ids(){
	return jQuery('.record_selector:checked').map(function(){ return jQuery(this).val() }).get();
}

function print_multiple_dv_tvdv(t, ids){
	document.myform.NoDV.value=1;
	document.myform.PrintDV.value=1;
	document.myform.SelectedID.value = '';
	document.myform.submit();
	return true;
}

function print_multiple_dv_sdv(t, ids){
	document.myform.NoDV.value=1;
	document.myform.PrintDV.value=1;
	document.myform.writeAttribute('novalidate', 'novalidate');
	document.myform.submit();
	return true;
}

function mass_delete(t, ids){
	if(ids == undefined) return;
	if(!ids.length) return;

	var confirm_message = '<div class="alert alert-danger">' +
			'<i class="glyphicon glyphicon-warning-sign"></i> ' + 
			'<?php echo addslashes($Translation['<n> records will be deleted. Are you sure you want to do this?']); ?>' +
		'</div>';
	var confirm_title = '<?php echo addslashes($Translation['Confirm deleting multiple records']); ?>';
	var label_yes = '<?php echo addslashes($Translation['Yes, delete them!']); ?>';
	var label_no = '<?php echo addslashes($Translation['No, keep them.']); ?>';
	var progress = '<?php echo addslashes($Translation['Deleting record <i> of <n>']); ?>';
	var continue_delete = true;

	// request confirmation of mass delete operation
	modal_window({
		message: confirm_message.replace(/\<n\>/, ids.length),
		title: confirm_title,
		footer: [ /* shows a 'yes' and a 'no' buttons .. handler for each follows ... */
			{
				label: '<i class="glyphicon glyphicon-trash"></i> ' + label_yes,
				bs_class: 'danger',
				// on confirming, start delete operations
				click: function(){

					// show delete progress, allowing user to abort operations by closing the window or clicking cancel
					var progress_window = modal_window({
						title: '<?php echo addslashes($Translation['Delete progress']); ?>',
						message: '' +
							'<div class="progress">' +
								'<div class="progress-bar progress-bar-warning" role="progressbar" style="width: 0;"></div>' +
							'</div>' + 
							'<button type="button" class="btn btn-default details_toggle" onclick="' +
								'jQuery(this).children(\'.glyphicon\').toggleClass(\'glyphicon-chevron-right glyphicon-chevron-down\'); ' +
								'jQuery(\'.well.details_list\').toggleClass(\'hidden\');'
								+ '">' +
								'<i class="glyphicon glyphicon-chevron-right"></i> ' +
								'<?php echo addslashes($Translation['Show/hide details']); ?>' +
							'</button>' +
							'<div class="well well-sm details_list hidden"><ol></ol></div>',
						close: function(){
							// stop deleting further records ...
							continue_delete = false;
						},
						footer: [
							{
								label: '<i class="glyphicon glyphicon-remove"></i> <?php echo addslashes($Translation['Cancel']); ?>',
								bs_class: 'warning'
							}
						]
					});

					// begin deleting records, one by one
					progress = progress.replace(/\<n\>/, ids.length);
					var delete_record = function(itrn){
						if(!continue_delete) return;
						jQuery.ajax(t + '_view.php', {
							type: 'POST',
							data: { delete_x: 1, SelectedID: ids[itrn] },
							success: function(resp){
								if(resp == 'OK'){
									jQuery(".well.details_list ol").append('<li class="text-success"><?php echo addslashes($Translation['The record has been deleted successfully']); ?></li>');
									jQuery('#record_selector_' + ids[itrn]).prop('checked', false).parent().parent().fadeOut(1500);
									jQuery('#select_all_records').prop('checked', false);
								}else{
									jQuery(".well.details_list ol").append('<li class="text-danger">' + resp + '</li>');
								}
							},
							error: function(){
								jQuery(".well.details_list ol").append('<li class="text-warning"><?php echo addslashes($Translation['Connection error']); ?></li>');
							},
							complete: function(){
								jQuery('#' + progress_window + ' .progress-bar').attr('style', 'width: ' + (Math.round((itrn + 1) / ids.length * 100)) + '%;').html(progress.replace(/\<i\>/, (itrn + 1)));
								if(itrn < (ids.length - 1)){
									delete_record(itrn + 1);
								}else{
									if(jQuery('.well.details_list li.text-danger, .well.details_list li.text-warning').length){
										jQuery('button.details_toggle').removeClass('btn-default').addClass('btn-warning').click();
										jQuery('.btn-warning[id^=' + progress_window + '_footer_button_]')
											.toggleClass('btn-warning btn-default')
											.html('<?php echo addslashes($Translation['ok']); ?>');
									}else{
										setTimeout(function(){ jQuery('#' + progress_window).modal('hide'); }, 500);
									}
								}
							}
						});
					}

					delete_record(0);
				}
			},
			{
				label: '<i class="glyphicon glyphicon-ok"></i> ' + label_no,
				bs_class: 'success' 
			}
		]
	});
}

function mass_change_owner(t, ids){
	if(ids == undefined) return;
	if(!ids.length) return;

	var update_form = '<?php echo addslashes($Translation['Change owner of <n> selected records to']); ?> ' + 
		'<span id="new_owner_for_selected_records"></span><input type="hidden" name="new_owner_for_selected_records" value="">';
	var confirm_title = '<?php echo addslashes($Translation['Change owner']); ?>';
	var label_yes = '<?php echo addslashes($Translation['Continue']); ?>';
	var label_no = '<?php echo addslashes($Translation['Cancel']); ?>';
	var progress = '<?php echo addslashes($Translation['Updating record <i> of <n>']); ?>';
	var continue_updating = true;

	// request confirmation of mass update operation
	modal_window({
		message: update_form.replace(/\<n\>/, ids.length),
		title: confirm_title,
		footer: [ /* shows a 'continue' and a 'cancel' buttons .. handler for each follows ... */
			{
				label: '<i class="glyphicon glyphicon-ok"></i> ' + label_yes,
				bs_class: 'success',
				// on confirming, start update operations
				click: function(){
					var memberID = jQuery('input[name=new_owner_for_selected_records]').eq(0).val();
					if(!memberID.length) return;

					// show update progress, allowing user to abort operations by closing the window or clicking cancel
					var progress_window = modal_window({
						title: '<?php echo addslashes($Translation['Update progress']); ?>',
						message: '' +
							'<div class="progress">' +
								'<div class="progress-bar progress-bar-success" role="progressbar" style="width: 0;"></div>' +
							'</div>' + 
							'<button type="button" class="btn btn-default details_toggle" onclick="' +
								'jQuery(this).children(\'.glyphicon\').toggleClass(\'glyphicon-chevron-right glyphicon-chevron-down\'); ' +
								'jQuery(\'.well.details_list\').toggleClass(\'hidden\');'
								+ '">' +
								'<i class="glyphicon glyphicon-chevron-right"></i> ' +
								'<?php echo addslashes($Translation['Show/hide details']); ?>' +
							'</button>' +
							'<div class="well well-sm details_list hidden"><ol></ol></div>',
						close: function(){
							// stop updating further records ...
							continue_updating = false;
						},
						footer: [
							{
								label: '<i class="glyphicon glyphicon-remove"></i> <?php echo addslashes($Translation['Cancel']); ?>',
								bs_class: 'warning'
							}
						]
					});

					// begin updating records, one by one
					progress = progress.replace(/\<n\>/, ids.length);
					var update_record = function(itrn){
						if(!continue_updating) return;
						jQuery.ajax('admin/pageEditOwnership.php', {
							type: 'POST',
							data: {
								pkValue: ids[itrn],
								t: t,
								memberID: memberID,
								saveChanges: 'Save changes'
							},
							success: function(resp){
								if(resp == 'OK'){
									jQuery(".well.details_list ol").append('<li class="text-success"><?php echo addslashes($Translation['record updated']); ?></li>');
									jQuery('#record_selector_' + ids[itrn]).prop('checked', false);
									jQuery('#select_all_records').prop('checked', false);
								}else{
									jQuery(".well.details_list ol").append('<li class="text-danger">' + resp + '</li>');
								}
							},
							error: function(){
								jQuery(".well.details_list ol").append('<li class="text-warning"><?php echo addslashes($Translation['Connection error']); ?></li>');
							},
							complete: function(){
								jQuery('#' + progress_window + ' .progress-bar').attr('style', 'width: ' + (Math.round((itrn + 1) / ids.length * 100)) + '%;').html(progress.replace(/\<i\>/, (itrn + 1)));
								if(itrn < (ids.length - 1)){
									update_record(itrn + 1);
								}else{
									if(jQuery('.well.details_list li.text-danger, .well.details_list li.text-warning').length){
										jQuery('button.details_toggle').removeClass('btn-default').addClass('btn-warning').click();
										jQuery('.btn-warning[id^=' + progress_window + '_footer_button_]')
											.toggleClass('btn-warning btn-default')
											.html('<?php echo addslashes($Translation['ok']); ?>');
									}else{
										jQuery('button.btn-warning[id^=' + progress_window + '_footer_button_]')
											.toggleClass('btn-warning btn-success')
											.html('<i class="glyphicon glyphicon-ok"></i> <?php echo addslashes($Translation['ok']); ?>');
									}
								}
							}
						});
					}

					update_record(0);
				}
			},
			{
				label: '<i class="glyphicon glyphicon-remove"></i> ' + label_no,
				bs_class: 'warning' 
			}
		]
	});

	/* show drop down of users */
	var populate_new_owner_dropdown = function(){

		jQuery('[id=new_owner_for_selected_records]').select2({
			width: '100%',
			formatNoMatches: function(term){ return '<?php echo addslashes($Translation['No matches found!']); ?>'; },
			minimumResultsForSearch: 10,
			loadMorePadding: 200,
			escapeMarkup: function(m){ return m; },
			ajax: {
				url: 'admin/getUsers.php',
				dataType: 'json',
				cache: true,
				data: function(term, page){ return { s: term, p: page, t: t }; },
				results: function(resp, page){ return resp; }
			}
		}).on('change', function(e){
			jQuery('[name="new_owner_for_selected_records"]').val(e.added.id);
		});

	}

	populate_new_owner_dropdown();
}

function add_more_actions_link(){
	window.open('http://bigprof.com/appgini/help/advanced-topics/hooks/multiple-record-batch-actions?r=appgini-action-menu');
}
