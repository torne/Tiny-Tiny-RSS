dojo.provide("fox.PrefFilterTree");

dojo.require("lib.CheckBoxTree");
dojo.require("dojo.data.ItemFileWriteStore");

dojo.declare("fox.PrefFilterStore", dojo.data.ItemFileWriteStore, {

	_saveEverything: function(saveCompleteCallback, saveFailedCallback,
								newFileContentString) {

		dojo.xhrPost({
			url: "backend.php",
			content: {op: "pref-filters", method: "savefilterorder",
				payload: newFileContentString},
			error: saveFailedCallback,
			load: saveCompleteCallback});
	},

});

dojo.declare("fox.PrefFilterTree", lib.CheckBoxTree, {
	_createTreeNode: function(args) {
		var tnode = this.inherited(arguments);

		var enabled = this.model.store.getValue(args.item, 'enabled');
		var param = this.model.store.getValue(args.item, 'param');

		if (param) {
			param = dojo.doc.createElement('span');
			param.className = (enabled != false) ? 'labelParam' : 'labelParam Disabled';
			param.innerHTML = args.item.param[0];
			dojo.place(param, tnode.rowNode, 'first');
		}

		return tnode;
	},

	getLabel: function(item) {
		var label = item.name;

		var feed = this.model.store.getValue(item, 'feed');
		var inverse = this.model.store.getValue(item, 'inverse');

		if (feed)
			label += " (" + __("in") + " " + feed + ")";

		if (inverse)
			label += " (" + __("Inverse") + ")";

/*		if (item.param)
			label = "<span class=\"labelFixedLength\">" + label +
				"</span>" + item.param[0]; */

		return label;
	},
	getIconClass: function (item, opened) {
		return (!item || this.model.mayHaveChildren(item)) ? (opened ? "dijitFolderOpened" : "dijitFolderClosed") : "invisible";
	},
	getLabelClass: function (item, opened) {
		var enabled = this.model.store.getValue(item, 'enabled');
		return (enabled != false) ? "dijitTreeLabel labelFixedLength" : "dijitTreeLabel labelFixedLength Disabled";
	},
	getRowClass: function (item, opened) {
		return (!item.error || item.error == '') ? "dijitTreeRow" :
			"dijitTreeRow Error";
	},
	checkItemAcceptance: function(target, source, position) {
		var item = dijit.getEnclosingWidget(target).item;

		// disable copying items
		source.copyState = function() { return false; };

		return position != 'over';
	},
	onDndDrop: function() {
		this.inherited(arguments);
		this.tree.model.store.save();
	},
});

