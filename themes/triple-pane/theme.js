function themeBeforeLayout() {
	if ($("content-insert")) {
		$("headlines-wrap-inner").setAttribute("design", 'sidebar');
		$("content-insert").setAttribute("region", "trailing");
		$("content-insert").setStyle({
			width: '50%',
			height: 'auto'});
	}
}

function themeAfterLayout() {
	$("headlines-toolbar").setStyle({
		'border-width': '1px 1px 0px 0px',
		'border-color': '#88b0f0',
		});
}
