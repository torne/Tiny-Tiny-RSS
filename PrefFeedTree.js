dojo.provide("fox.PrefFeedTree");

dojo.require("lib.CheckBoxTree");

dojo.declare("fox.PrefFeedTree", lib.CheckBoxTree, {
	checkItemAcceptance: function(target, source, position) {
		var item = dijit.getEnclosingWidget(target).item;

		// disable copying items
		source.copyState = function() { return false; }

		var source_item = false;

		source.forInSelectedItems(function(node) {
			source_item = node.data.item;
		});

		if (!source_item || !item) return false;

		var id = String(item.id);
		var source_id = String(source_item.id);

		var id = this.tree.model.store.getValue(item, 'id');
		var source_id = source.tree.model.store.getValue(source_item, 'id');

		//console.log(id + " " + position + " " + source_id);

		if (source_id.match("FEED:")) {
			return ((id.match("CAT:") && position == "over") ||
				(id.match("FEED:") && position != "over"));
		} else if (source_id.match("CAT:")) {
			return ((id.match("CAT:") && position != "over") ||
				(id.match("root") && position == "over"));
		}
	},
});

