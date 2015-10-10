dojo.provide("fox.PrefLabelTree");

dojo.require("lib.CheckBoxTree");
dojo.require("dijit.form.DropDownButton");

dojo.declare("fox.PrefLabelTree", lib.CheckBoxTree, {
	setNameById: function (id, name) {
		var item = this.model.store._itemsByIdentity['LABEL:' + id];

		if (item)
			this.model.store.setValue(item, 'name', name);

	},
	_createTreeNode: function(args) {
		var tnode = this.inherited(arguments);

		var fg_color = this.model.store.getValue(args.item, 'fg_color');
		var bg_color = this.model.store.getValue(args.item, 'bg_color');
		var type = this.model.store.getValue(args.item, 'type');
		var bare_id = this.model.store.getValue(args.item, 'bare_id');

		if (type == 'label') {
			var span = dojo.doc.createElement('span');
			span.innerHTML = '&alpha;';
			span.className = 'labelColorIndicator';
			span.id = 'LICID-' + bare_id;

			span.setStyle({
				color: fg_color,
				backgroundColor: bg_color});

			tnode._labelIconNode = span;

			dojo.place(tnode._labelIconNode, tnode.labelNode, 'before');
		}

		return tnode;
	},
	getIconClass: function (item, opened) {
		return (!item || this.model.mayHaveChildren(item)) ? (opened ? "dijitFolderOpened" : "dijitFolderClosed") : "invisible";
	},
});

