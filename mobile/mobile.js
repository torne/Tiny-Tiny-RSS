var backend = "backend.php";

function toggleMarked(id, elem) {

	var toggled = false;

	if (elem.getAttribute("toggled") == "true") {
		toggled = 1;
	} else {
		toggled = 0;
	}

	var query = "?op=toggleMarked&id=" + id + "&mark=" + toggled;

	new Ajax.Request(backend, {
		parameters: query,
		onComplete: function (transport) {
			//
		} });
}

function togglePublished(id, elem) {

	var toggled = false;

	if (elem.getAttribute("toggled") == "true") {
		toggled = 1;
	} else {
		toggled = 0;
	}

	var query = "?op=togglePublished&id=" + id + "&mark=" + toggled;

	new Ajax.Request(backend, {
		parameters: query,
		onComplete: function (transport) {
			//
		} });

}

