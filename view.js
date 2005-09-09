var xmlhttp_rpc = false;

/*@cc_on @*/
/*@if (@_jscript_version >= 5)
// JScript gives us Conditional compilation, we can cope with old IE versions.
// and security blocked creation of the objects.
try {
	xmlhttp_rpc = new ActiveXObject("Msxml2.XMLHTTP");
} catch (e) {
	try {
		xmlhttp_rpc = new ActiveXObject("Microsoft.XMLHTTP");
	} catch (E) {
		xmlhttp_rpc = false;
	}
}
@end @*/

if (!xmlhttp_rpc && typeof XMLHttpRequest!='undefined') {
	xmlhttp_rpc = new XMLHttpRequest();
}

/*
function label_counters_callback() {
	if (xmlhttp_rpc.readyState == 4) {
		var reply = xmlhttp_rpc.responseXML.firstChild;

		var f_document = parent.frames["feeds-frame"].document;

		for (var l = 0; l < reply.childNodes.length; l++) {
			var id = reply.childNodes[l].getAttribute("id");
			var ctr = reply.childNodes[l].getAttribute("counter");

			var feedctr = f_document.getElementById("FEEDCTR-" + id);
			var feedu = f_document.getElementById("FEEDU-" + id);

			feedu.innerHTML = ctr;

			if (ctr > 0) {
				feedctr.className = "odd";
			} else {
				feedctr.className = "invisible";
			}
		}
	}
}

function update_label_counters() {
	if (xmlhttp_ready(xmlhttp_rpc)) {
		var query = "backend.php?op=rpc&subop=getLabelCounters";	
		xmlhttp_rpc.open("GET", query, true);
		xmlhttp_rpc.onreadystatechange=label_counters_callback;
		xmlhttp_rpc.send(null);
	}
}
*/
