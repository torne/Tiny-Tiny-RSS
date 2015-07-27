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
		} else {
			treeItem = this.store._itemsByIdentity['FEED:' + feed];
		}

		items = this.store._arrayOfAllItems;

		for (var i = 0; i < items.length; i++) {
			if (items[i] == treeItem) {

				for (var j = i+1; j < items.length; j++) {
					var unread = this.store.getValue(items[j], 'unread');
					var id = this.store.getValue(items[j], 'id');

					if (unread > 0 && ((is_cat && id.match("CAT:")) || (!is_cat && id.match("FEED:")))) {
						if( !is_cat || ! (this.store.hasAttribute(items[j], 'parent_id') && this.store.getValue(items[j], 'parent_id') == feed) ) return items[j];
					}
				}

				for (var j = 0; j < i; j++) {
					var unread = this.store.getValue(items[j], 'unread');
					var id = this.store.getValue(items[j], 'id');

					if (unread > 0 && ((is_cat && id.match("CAT:")) || (!is_cat && id.match("FEED:")))) {
						if( !is_cat || ! (this.store.hasAttribute(items[j], 'parent_id') && this.store.getValue(items[j], 'parent_id') == feed) ) return items[j];
					}
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

		if (args.item.icon && args.item.icon[0])
			tnode.iconNode.src = args.item.icon[0];

		var id = args.item.id[0];
		var bare_id = parseInt(id.substr(id.indexOf(':')+1));

		if (bare_id < _label_base_index) {
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

		if (id.match("CAT:")) {
			loading = dojo.doc.createElement('img');
			loading.className = 'loadingNode';
			loading.src = 'images/blank_icon.gif';
			dojo.place(loading, tnode.labelNode, 'after');
			tnode.loadingNode = loading;
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

		ctr = dojo.doc.createElement('span');
		ctr.className = 'counterNode';
		ctr.innerHTML = args.item.unread > 0 ? args.item.unread : args.item.auxcounter;

		//args.item.unread > 0 ? ctr.addClassName("unread") : ctr.removeClassName("unread");

		args.item.unread > 0 || args.item.auxcounter > 0 ? Element.show(ctr) : Element.hide(ctr);

		args.item.unread == 0 && args.item.auxcounter > 0 ? ctr.addClassName("aux") : ctr.removeClassName("aux");

		dojo.place(ctr, tnode.rowNode, 'first');
		tnode.counterNode = ctr;

		//tnode.labelNode.innerHTML = args.label;
		return tnode;
	},
	postCreate: function() {
		this.connect(this.model, "onChange", "updateCounter");
		this.connect(this, "_expandNode", function() {
			this.hideRead(getInitParam("hide_read_feeds"), getInitParam("hide_read_shows_special"));
		});

		this.inherited(arguments);
	},
	updateCounter: function (item) {
		var tree = this;

		//console.log("updateCounter: " + item.id[0] + " " + item.unread + " " + tree);

		var node = tree._itemNodesMap[item.id];

		if (node) {
			node = node[0];

			if (node.counterNode) {
				ctr = node.counterNode;
				ctr.innerHTML = item.unread > 0 ? item.unread : item.auxcounter;
				item.unread > 0 || item.auxcounter > 0 ?
					Effect.Appear(ctr, {duration : 0.3,
					queue: { position: 'end', scope: 'CAPPEAR-' + item.id, limit: 1 }}) :
						Element.hide(ctr);

				item.unread == 0 && item.auxcounter > 0 ? ctr.addClassName("aux") : ctr.removeClassName("aux");

			}
		}

	},
	getTooltip: function (item) {
		if (item.updated)
			return item.updated;
		else
			return "";
	},
	getIconClass: function (item, opened) {
		return (!item || this.model.mayHaveChildren(item)) ? (opened ? "dijitFolderOpened" : "dijitFolderClosed") : "feedIcon";
	},
	getLabelClass: function (item, opened) {
		return (item.unread == 0) ? "dijitTreeLabel" : "dijitTreeLabel Unread";
	},
	getRowClass: function (item, opened) {
		var rc = (!item.error || item.error == '') ? "dijitTreeRow" :
			"dijitTreeRow Error";

		if (item.unread > 0) rc += " Unread";

		return rc;
	},
	getLabel: function(item) {
		var name = String(item.name);

		/* Horrible */
		name = name.replace(/&quot;/g, "\"");
		name = name.replace(/&amp;/g, "&");
		name = name.replace(/&mdash;/g, "-");
		name = name.replace(/&lt;/g, "<");
		name = name.replace(/&gt;/g, ">");

		/* var label;

		if (item.unread > 0) {
			label = name + " (" + item.unread + ")";
		} else {
			label = name;
		} */

		return name;
	},
	expandParentNodes: function(feed, is_cat, list) {
		try {
			for (var i = 0; i < list.length; i++) {
				var id = String(list[i].id);
				var item = this._itemNodesMap[id];

				if (item) {
					item = item[0];
					this._expandNode(item);
				}
			}
		} catch (e) {
			exception_error("expandParentNodes", e);
		}
	},
	findNodeParentsAndExpandThem: function(feed, is_cat, root, parents) {
		// expands all parents of specified feed to properly mark it as active
		// my fav thing about frameworks is doing everything myself
		try {
			var test_id = is_cat ? 'CAT:' + feed : 'FEED:' + feed;

			if (!root) {
				if (!this.model || !this.model.store) return false;

				var items = this.model.store._arrayOfTopLevelItems;

				for (var i = 0; i < items.length; i++) {
					if (String(items[i].id) == test_id) {
						this.expandParentNodes(feed, is_cat, parents);
					} else {
						this.findNodeParentsAndExpandThem(feed, is_cat, items[i], []);
					}
				}
			} else {
				if (root.items) {
					parents.push(root);

					for (var i = 0; i < root.items.length; i++) {
						if (String(root.items[i].id) == test_id) {
							this.expandParentNodes(feed, is_cat, parents);
						} else {
							this.findNodeParentsAndExpandThem(feed, is_cat, root.items[i], parents.slice(0));
						}
					}
				} else {
					if (String(root.id) == test_id) {
						this.expandParentNodes(feed, is_cat, parents.slice(0));
					}
				}
			}
		} catch (e) {
			exception_error("findNodeParentsAndExpandThem", e);
		}
	},
	selectFeed: function(feed, is_cat) {
		this.findNodeParentsAndExpandThem(feed, is_cat, false, false);

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
			if (treeNode.loadingNode) {
				treeNode.loadingNode.src = src;
				return true;
			} else {
				treeNode.expandoNode.src = src;
				return true;
			}
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

					if (hide && cat_unread == 0 && check_unread == 0 && (id != "CAT:-1" || !show_special)) {
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
					if (hide && unread == 0 && (bare_id > 0 || bare_id < _label_base_index || !show_special)) {
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
			if (!node.isExpanded)
				tree._expandNode(node);
			else
				tree._collapseNode(node);

		}
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
		var item = items[0] == treeItem ? items[items.length-1] : items[0];

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
