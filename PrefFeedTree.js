dojo.provide("fox.PrefFeedTree");

dojo.require("lib.CheckBoxTree");

dojo.declare("fox.PrefFeedTree", lib.CheckBoxTree, {
	checkItemAcceptance: function(target, source, position) {
		var item = dijit.getEnclosingWidget(target).item;

		console.log(source.currentWidget);
		
		var id = String(item.id);
		return (id.match("CAT:") || position != "over");
		return true;
	},
});

