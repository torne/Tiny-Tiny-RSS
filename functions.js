function param_escape(arg) {
	if (typeof encodeURIComponent != 'undefined')
		return encodeURIComponent(arg);	
	else
		return escape(arg);
}

function param_unescape(arg) {
	if (typeof decodeURIComponent != 'undefined')
		return decodeURIComponent(arg);	
	else
		return unescape(arg);
}


function notify(msg) {

	var n = document.getElementById("notify");

	n.innerHTML = msg;

	if (msg.length == 0) {
		n.style.display = "none";
	} else {
		n.style.display = "block";
	}

}


