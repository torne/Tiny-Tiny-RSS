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
		if (!this.store._itemsByIdentity)
			return null;

		if (is_cat) {
			treeItem = this.store._itemsByIdentity['CAT:' + feed];
			items = this.store._arrayOfTopLevelItems;
		} else {
			treeItem = this.store._itemsByIdentity['FEED:' + feed];
			items = this.store._arrayOfAllItems;
		}

		for (var i = 0; i < items.length; i++) {
			if (items[i] == treeItem) {

				for (var j = i+1; j < items.length; j++) {
					var unread = this.store.getValue(items[j], 'unread');
					var id = this.store.getValue(items[j], 'id');

					if (unread > 0 && (is_cat || id.match("FEED:"))) return items[j];
				}

				for (var j = 0; j < i; j++) {
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
	_onKeyPress: function(/* Event */ e) {
		return; // Stop dijit.Tree from interpreting keystrokes
	},
	_createTreeNode: function(args) {
		var tnode = new dijit._TreeNode(args);

		if (args.item.icon)
			tnode.iconNode.src = args.item.icon[0];

		var id = args.item.id[0];
		var bare_id = parseInt(id.substr(id.indexOf(':')+1));

		if (bare_id < -10) {
			var span = dojo.doc.createElement('span');
			var fg_color = args.item.fg_color[0];
			var bg_color = args.item.bg_color[0];

			span.innerHTML = "&alpha;";
			span.className = 'labelColorIndicator';
			span.setStyle({
				color: fg_color,
				backgroundColor: bg_color});

			dojo.place(span, tnode.iconNode, 'replace');
		}

		if (id.match("FEED:")) {
			var menu = new dijit.Menu();
			menu.row_id = bare_id;

			menu.addChild(new dijit.MenuItem({
				label: __("Mark as read"),
				onClick: function() {
					catchupFeed(this.getParent().row_id);
				}}));

			if (bare_id > 0) {
				menu.addChild(new dijit.MenuItem({
					label: __("Edit feed"),
					onClick: function() {
						editFeed(this.getParent().row_id, false);
					}}));

				/* menu.addChild(new dijit.MenuItem({
					label: __("Update feed"),
					onClick: function() {
						heduleFeedUpdate(this.getParent().row_id, false);
					}})); */
			}

			menu.bindDomNode(tnode.domNode);
			tnode._menu = menu;
		}

		if (id.match("CAT:") && bare_id >= 0) {
			var menu = new dijit.Menu();
			menu.row_id = bare_id;

			menu.addChild(new dijit.MenuItem({
				label: __("Mark as read"),
				onClick: function() {
					catchupFeed(this.getParent().row_id, true);
				}}));

			menu.bindDomNode(tnode.domNode);
			tnode._menu = menu;
		}

		if (id.match("CAT:") && bare_id == -1) {
			var menu = new dijit.Menu();
			menu.row_id = bare_id;

			menu.addChild(new dijit.MenuItem({
				label: __("Mark all feeds as read"),
				onClick: function() {
					catchupAllFeeds();
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
		var name = String(item.name);

		/* Horrible */
		name = name.replace(/&quot;/g, "\"");
		name = name.replace(/&amp;/g, "&");
		name = name.replace(/&mdash;/g, "-");
		name = name.replace(/&lt;/g, "<");
		name = name.replace(/&gt;/g, ">");

		var label;

		if (item.unread > 0) {
			label = name + " (" + item.unread + ")";
		} else {
			label = name;
		}

		return label;
	},
	selectFeed: function(feed, is_cat) {
		if (is_cat)
			treeNode = this._itemNodesMap['CAT:' + feed];
		else
			treeNode = this._itemNodesMap['FEED:' + feed];

		if (treeNode) {
			treeNode = treeNode[0];
			if (!is_cat) this._expandNode(treeNode);
			this.set("selectedNodes", [treeNode]);
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
	hideReadCat: function (cat, hide, show_special) {
		if (this.hasCats()) {
			var tree = this;

			if (cat && cat.items) {
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
			}
		}
	},
	hideRead: function (hide, show_special) {
		if (this.hasCats()) {

			var tree = this;
			var cats = this.model.store._arrayOfTopLevelItems;

			cats.each(function(cat) {
				tree.hideReadCat(cat, hide, show_special);
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

			// it's a subcategory
			if (feed.items) {
				tree.hideReadCat(feed, hide, show_special);
			} else {	// it's a feed
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
			}
		});

		return cat_unread;
	},
	collapseCat: function(id) {
		if (!this.model.hasCats()) return;

		var tree = this;

		var node = tree._itemNodesMap['CAT:' + id][0];
		var item = tree.model.store._itemsByIdentity['CAT:' + id];

		if (node && item) {
			var hidden = tree.model.store.getValue(item, 'hidden');

			if (hidden)
				tree._expandNode(node);
			else
				tree._collapseNode(node);

			tree.model.store.setValue(item, 'hidden', !hidden);
		}
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
	getVisibleUnreadFeeds: function() {
		var items = this.model.store._arrayOfAllItems;
		var rv = [];

		for (var i = 0; i < items.length; i++) {
			var id = String(items[i].id);
			var box = this._itemNodesMap[id];

			if (box) {
				var row = box[0].rowNode;
				var cat = false;

				try {
					cat = box[0].rowNode.parentNode.parentNode;
				} catch (e) { }

				if (row) {
					if (Element.visible(row) && (!cat || Element.visible(cat))) {
						var feed_id = String(items[i].bare_id);
						var is_cat = !id.match('FEED:');
						var unread = this.model.getFeedUnread(feed_id, is_cat);

						if (unread > 0)
							rv.push([feed_id, is_cat]);

					}
				}
			}
		}

		return rv;
	},
	getNextFeed: function (feed, is_cat) {
		if (is_cat) {
			treeItem = this.model.store._itemsByIdentity['CAT:' + feed];
		} else {
			treeItem = this.model.store._itemsByIdentity['FEED:' + feed];
		}

		items = this.model.store._arrayOfAllItems;
		var item = items[0];

		for (var i = 0; i < items.length; i++) {
			if (items[i] == treeItem) {

				for (var j = i+1; j < items.length; j++) {
					var id = String(items[j].id);
					var box = this._itemNodesMap[id];

					if (box) {
						var row = box[0].rowNode;
						var cat = box[0].rowNode.parentNode.parentNode;

						if (Element.visible(cat) && Element.visible(row)) {
							item = items[j];
							break;
						}
					}
				}
				break;
			}
		}

		if (item) {
			return [this.model.store.getValue(item, 'bare_id'),
					 	!this.model.store.getValue(item, 'id').match('FEED:')];
		} else {
			return false;
		}
	},
	getPreviousFeed: function (feed, is_cat) {
		if (is_cat) {
			treeItem = this.model.store._itemsByIdentity['CAT:' + feed];
		} else {
			treeItem = this.model.store._itemsByIdentity['FEED:' + feed];
		}

		items = this.model.store._arrayOfAllItems;
		var item = items[0];

		for (var i = 0; i < items.length; i++) {
			if (items[i] == treeItem) {

				for (var j = i-1; j > 0; j--) {
					var id = String(items[j].id);
					var box = this._itemNodesMap[id];

					if (box) {
						var row = box[0].rowNode;
						var cat = box[0].rowNode.parentNode.parentNode;

						if (Element.visible(cat) && Element.visible(row)) {
							item = items[j];
							break;
						}
					}

				}
				break;
			}
		}

		if (item) {
			return [this.model.store.getValue(item, 'bare_id'),
					 	!this.model.store.getValue(item, 'id').match('FEED:')];
		} else {
			return false;
		}

	},
	getFeedCategory: function(feed) {
		try {
			return this.getNodesByItem(this.model.store.
					_itemsByIdentity["FEED:" + feed])[0].
					getParent().item.bare_id[0];

		} catch (e) {
			return false;
		}
	},
});
