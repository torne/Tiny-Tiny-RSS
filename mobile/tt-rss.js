function toggleSelectRow(cb, id) {	
	try {

		var row = document.getElementById("HROW-" + id);
		var checked = cb.checked;
		if (row) {
			var unread = row.className.match("Unread");
			var new_classname = row.className;

			new_classname = new_classname.replace("Selected", "");
			new_classname = new_classname.replace("Unread", "");

			if (unread) new_classname = new_classname + "Unread";
			if (checked) new_classname = new_classname + "Selected";

			row.className = new_classname;
		}
	} catch (e) {
		exception_error("toggleSelectRow", e);
	}
}

function selectHeadlines(mode) {
	try {

	var cboxes = document.getElementsByTagName("INPUT");

	for (var i = 0; i < cboxes.length; i++) {
		if (cboxes[i].id && cboxes[i].id.match("HSCB-")) {
			var row_id = cboxes[i].id.replace("HSCB-", "")
			var row = document.getElementById("HROW-" + row_id);

			if (row) {

				if (mode == 1) {
					cboxes[i].checked = true;
					toggleSelectRow(cboxes[i], row_id);
				}

				if (mode == 2) {

					var unread = row.className.match("Unread");

					if (unread) {
						cboxes[i].checked = true;
					} else {
						cboxes[i].checked = false;
					}
				}

				if (mode == 3) {
					cboxes[i].checked = false;
				}

				if (mode == 4) {
					cboxes[i].checked = !cboxes[i].checked;
				}

				toggleSelectRow(cboxes[i], row_id);

			}

		}

	}

	} catch (e) {
		exception_error("selectHeadlines", e);
	}
}

function exception_error(location, e, silent) {
	var msg;

	if (e.fileName) {
		var base_fname = e.fileName.substring(e.fileName.lastIndexOf("/") + 1);
	
		msg = "Exception: " + e.name + ", " + e.message + 
			"\nFunction: " + location + "()" +
			"\nLocation: " + base_fname + ":" + e.lineNumber;

	} else if (e.description) {
		msg = "Exception: " + e.description + "\nFunction: " + location + "()";
	} else {
		msg = "Exception: " + e + "\nFunction: " + location + "()";
	}

	debug("<b>EXCEPTION: " + msg + "</b>");

	if (!silent) {
		alert(msg);
	}
}

