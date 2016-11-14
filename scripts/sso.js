/**
 * Display/hide the overlay div and center this first child on screen
 */
function showOverlayDialog($overlay, show = true) {
	if (show) {
		$overlay.show();
		var $dialog = $overlay.children().first();
		$dialog.css({
			'margin-left' : function() {return -$dialog.outerWidth()/2},
			'margin-top' : function() {return -$dialog.outerHeight()/2},
		});
		$dialog.find(':input').first().focus();
	} else {
		$overlay.hide();
	}
}

/**
 * Handle overlay
 * @param action show|cancel|save
 * 		show display the dialog and save previous values
 * 		cancel hide the dialog and restore previous values
 * 		save hide the dialog without restore
 */
function handleOverlay($overlay, action) {
	if (action === 'show') {
		showOverlayDialog($overlay, true);

		$overlay.find(':input').not('[type=button]').each(function() {
			$(this).data('old', $(this).val());
		});
	} else if (action === 'save') {
		showOverlayDialog($overlay, false);

	} else if (action === 'cancel') {
		showOverlayDialog($overlay, false);

		$overlay.find(':input').not('[type=button]').each(function() {
			$(this).val($(this).data('old'));
		});
	}
}

/**
 * Handle options for AuthMethod 
 * @param action show|cancel|save
 * 		show display the dialog and load values from hidden input
 * 		cancel hide the dialog and restore previous values
 * 		save hide the dialog and save values in hidden original input
 */
function modifyAuthOptions(e, action) {
	var parent = $(e).closest('.overlay')
	var inputs = parent.find(':input').not('[type=button]')
	var dest = parent.next('input[type=hidden]')

	switch(action) {
		case 'show' :
			showOverlayDialog(parent, true);
			var obj = null;
			if (dest.val() != '') {
				obj = JSON.parse(dest.val());
			}
			inputs.each(function() {
				var name = extractLastName(this.name);
				$(this).val((obj !== null)?obj[name]:null);
			});
		break;
		case 'cancel' :
			showOverlayDialog(parent, false);
			var obj = null;
			if (dest.val() != '') {
				obj = JSON.parse(dest.val());
			}
			inputs.each(function() {
				var name = extractLastName(this.name);
				$(this).val((obj !== null)?obj[name]:null);
			});
		break;
		case 'save' :
			showOverlayDialog(parent, false)

			var obj = {};
			var tooltip = [];
			inputs.each(function() {
				var name = extractLastName(this.name);
				obj[name]=this.value;
				tooltip.push($(this).closest('tr').find('td').first().text()+' : '+this.value);
			});
			dest.val(JSON.stringify(obj));
			var img = parent.prev('img');
			img.prop('title', tooltip.join("\n"));
		break;
	}
}

/**
 * Extract the last name of a complex input name like 'a[b][c][d]' => return 'd'
 */
function extractLastName(name) {
	var names = name.split(/[[\]]{1,2}/);
	name = names.pop(); 
	if (name == '') { // can be empty, last ]
		name = names.pop();
	}
	return name;
}

/**
 * Propagate select option title to main select tag
 * and update link after select
 */
function selectTitle(select) {
	var selected = $(select).find(':selected');
	var a=$(select).next('a')[0];
	if (selected.prop('value').indexOf('__g')==0) {
		$(select).prop('title', selected.prop('title'));
		a.href = a.href.replace(/=\d*$/, '='+select.value.substr('__g'.length));
		$(a).toggle(select.value != '');
	} else {
		$(select).prop('title', '');
		$(a).toggle(false);
	}
}

// Init : update select title
$(function() {
	$('select.selectTitle').change();
});