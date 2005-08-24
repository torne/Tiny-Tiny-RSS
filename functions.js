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

function delay(gap) {
	var then,now; 
	then=new Date().getTime();
	now=then;
	while((now-then)<gap) {
		now=new Date().getTime();
	}
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

function printLockingError() {
	notify("Please wait until operation finishes");
}


