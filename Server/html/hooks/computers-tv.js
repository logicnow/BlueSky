function mark_removal(table_name, ids){

	editPageURL="u_mark_removal.php?selected_ids="+ids;
	var options={id:"edit_modal",url:editPageURL,title:"Applying these changes will overwrite any existing settings in the selected Computers.",message:"test Message",size:"full"};
	modal_window(options);
	jQuery('#edit_modal').on('hidden.bs.modal', function (){ window.location.reload();});
}