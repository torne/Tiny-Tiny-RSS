dojo.provide("fox.FeedTree");
dojo.provide("fox.FeedStoreModel");

dojo.require("dijit.Tree");
dojo.require("dijit.Menu");

dojo.declare("fox.FeedStoreModel", dijit.tree.ForestStoreModel, {
	getItemsInCategory: function (id) {
		if (!this.store._itemsByIdentity) return undefined;

		cat = this.store._itemsByIdentity['CAT:' + id];

		if (cat && cat.items)
			return cat.items;
		else
			return undefined;

	},
	getItemById: function(id) {
		return this.store._itemsByIdentity[id];
	},
	getFeedValue: function(feed, is_cat, key) {	
		if (!this.store._itemsByIdentity) return undefined;

		if (is_cat) 
			treeItem = this.store._itemsByIdentity['CAT:' + feed];
		else
			treeItem = this.store._itemsByIdentity['FEED:' + feed];

		if (treeItem)
			return this.store.getValue(treeItem, key);
	},
	getFeedName: function(feed, is_cat) {	
		return this.getFeedValue(feed, is_cat, 'name');
	},
	getFeedUnread: function(feed, is_cat) {
		var unread = parseInt(this.getFeedValue(feed, is_cat, 'unread'));
		return (isNaN(unread)) ? 0 : unread;
	},
	setFeedUnread: function(feed, is_cat, unread) {
		return this.setFeedValue(feed, is_cat, 'unread', parseInt(unread));
	},
	setFeedValue: function(feed, is_cat, key, value) {
		if (!value) value = '';
		if (!this.store._itemsByIdentity) return undefined;

		if (is_cat) 
			treeItem = this.store._itemsByIdentity['CAT:' + feed];
		else
			treeItem = this.store._itemsByIdentity['FEED:' + feed];

		if (treeItem)
			return this.store.setValue(treeItem, key, value);
	},
	getNextUnreadFeed: function (feed, is_cat) {
		if (is_cat) {
			treeItem = this.store._itemsByIdentity['CAT:' + feed];
			items = this.store._arrayOfTopLevelItems;
		} else {
			treeItem = this.store._itemsByIdentity['FEED:' + feed];
			items = this.store._arrayOfAllItems;
		}

		for (var i = 0; i < items.length; i++) {
			if (items[i] == treeItem) {

				for (j = i+1; j < items.length; j++) {
					var unread = this.store.getValue(items[j], 'unread');
					var id = this.store.getValue(items[j], 'id');

					if (unread > 0 && (is_cat || id.match("FEED:"))) return items[j];
				}

				for (j = 0; j < i; j++) {
					var unread = this.store.getValue(items[j], 'unread');
					var id = this.store.getValue(items[j], 'id');

					if (unread > 0 && (is_cat || id.match("FEED:"))) return items[j];
				}
			}
		}
		
		return null;
	},
	hasCats: function() {
		if (this.store && this.store._itemsByIdentity)
			return this.store._itemsByIdentity['CAT:-1'] != undefined;
		else
			return false;
	},
});

dojo.declare("fox.FeedTree", dijit.Tree, {
	_createTreeNode: function(args) {
		var tnode = new dijit._TreeNode(args);
		
		if (args.item.icon)
			tnode.iconNode.src = args.item.icon[0];

		var id = args.item.id[0];
		var bare_id = parseInt(id.substr(id.indexOf(':')+1));

		if (id.match("FEED:") && bare_id > 0) {
			var menu = new dijit.Menu();
			menu.row_id = bare_id;

			menu.addChild(new dijit.MenuItem({
				label: __("Edit feed"),
				onClick: function() {
					editFeed(this.getParent().row_id);
				}}));

			menu.addChild(new dijit.MenuItem({
				label: __("Update feed"),
				onClick: function() {
					scheduleFeedUpdate(this.getParent().row_id, false);
				}}));

			menu.bindDomNode(tnode.domNode);
			tnode._menu = menu;
		}

		//tnode.labelNode.innerHTML = args.label;
		return tnode;
	},
	getIconClass: function (item, opened) {
		return (!item || this.model.mayHaveChildren(item)) ? (opened ? "dijitFolderOpened" : "dijitFolderClosed") : "feedIcon";
	},
	getLabelClass: function (item, opened) {
		return (item.unread == 0) ? "dijitTreeLabel" : "dijitTreeLabel Unread";
	},
	getRowClass: function (item, opened) {
		return (!item.error || item.error == '') ? "dijitTreeRow" : 
			"dijitTreeRow Error";
	},
	getLabel: function(item) {
		if (item.unread > 0) {
			return item.name + " (" + item.unread + ")";
		} else {
			return item.name;
		}
	},
	selectFeed: function(feed, is_cat) {
		if (is_cat) 
			treeNode = this._itemNodesMap['CAT:' + feed];
		else
			treeNode = this._itemNodesMap['FEED:' + feed];

		if (treeNode) {
			treeNode = treeNode[0];
			if (!is_cat) this._expandNode(treeNode);
			this._selectNode(treeNode);
		}
	},
	setFeedIcon: function(feed, is_cat, src) {
		if (is_cat) 
			treeNode = this._itemNodesMap['CAT:' + feed];
		else
			treeNode = this._itemNodesMap['FEED:' + feed];

		if (treeNode) {
			treeNode = treeNode[0];
			treeNode.iconNode.src = src;
			return true;
		}
		return false;
	},
	setFeedExpandoIcon: function(feed, is_cat, src) {
		if (is_cat) 
			treeNode = this._itemNodesMap['CAT:' + feed];
		else
			treeNode = this._itemNodesMap['FEED:' + feed];

		if (treeNode) {
			treeNode = treeNode[0];
			treeNode.expandoNode.src = src;
			return true;
		}

		return false;
	},
	hasCats: function() {
		return this.model.hasCats();
	},
	hideRead: function (hide, show_special) {
		if (this.hasCats()) {

			var tree = this;
			var cats = this.model.store._arrayOfTopLevelItems;
	
			cats.each(function(cat) {
				var cat_unread = tree.hideReadFeeds(cat.items, hide, show_special);
		
				var id = String(cat.id);
				var node = tree._itemNodesMap[id];
				var bare_id = parseInt(id.substr(id.indexOf(":")+1));
		
				if (node) {
					var check_unread = tree.model.getFeedUnread(bare_id, true);
	
					if (hide && cat_unread == 0 && check_unread == 0) {
						Effect.Fade(node[0].rowNode, {duration : 0.3, 
							queue: { position: 'end', scope: 'FFADE-' + id, limit: 1 }});
					} else {
						Element.show(node[0].rowNode);
						++cat_unread;
					}
				}	
			});

		} else {
			this.hideReadFeeds(this.model.store._arrayOfTopLevelItems, hide, 
				show_special);
		}
	},
	hideReadFeeds: function (items, hide, show_special) {
		var tree = this;
		var cat_unread = 0;

		items.each(function(feed) {
			var id = String(feed.id);
			var bare_id = parseInt(feed.bare_id);;
	
			var unread = feed.unread[0];
			var node = tree._itemNodesMap[id];
	
			if (node) {
				if (hide && unread == 0 && (bare_id > 0 || !show_special)) {
					Effect.Fade(node[0].rowNode, {duration : 0.3, 
						queue: { position: 'end', scope: 'FFADE-' + id, limit: 1 }});
				} else {
					Element.show(node[0].rowNode);
					++cat_unread;
				}
			}
		});
	
		return cat_unread;
	},
	collapseHiddenCats: function() {
		if (!this.model.hasCats()) return;

		var cats = this.model.store._arrayOfTopLevelItems;
		var tree = this;

		dojo.forEach(cats, function(cat) {
			var hidden = tree.model.store.getValue(cat, 'hidden');
			var id = tree.model.store.getValue(cat, 'id');
			var node = tree._itemNodesMap[id][0];

			if (hidden) 
				tree._collapseNode(node);
			else
				tree._expandNode(node);

		});
	},
});
