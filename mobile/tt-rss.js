function toggleSelectRow(cb, id) {	
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
}
