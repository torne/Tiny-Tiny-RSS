function selectTableRow(r, do_select) {
	r.className = r.className.replace("Selected", "");
	
	if (do_select) {
		r.className = r.className + "Selected";
	}
}

function selectTableRowById(elem_id, check_id, do_select) {

	try {

		var row = $(elem_id);

		if (row) {
			selectTableRow(row, do_select);
		}		

		var check = $(check_id);

		if (check) {
			check.checked = do_select;
		}
	} catch (e) {
		exception_error("selectTableRowById", e);
	}
}

