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
	if ($("content-insert")) {
		$("headlines-toolbar").setStyle({
			'border-right-width': '1px',
			'border-color': '#88b0f0',
			});
	}
}
