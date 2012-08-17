<?php
class Plugins {
	protected $link;
	protected $plugins;
	protected $listeners;

	function __construct($link) {
		$this->link = $link;
		$this->listeners = array();
		$this->load_plugins();
	}

	function load_plugins() {
		if (defined('_ENABLE_PLUGINS')) {
			$plugins = explode(",", _ENABLE_PLUGINS);

			foreach ($plugins as $p) {
				$plugin_class = "plugin_$p";
				if (class_exists($plugin_class)) {
					$plugin = new $plugin_class($this->link, $this);
				}
			}
		}
	}

	function add_listener($hook_name, $plugin) {
		if (!is_array($this->listeners[$hook_name]))
			$this->listeners[$hook_name] = array();

		array_push($this->listeners[$hook_name], $plugin);
	}

	function hook($hook_name, &$params) {
		if (is_array($this->listeners[$hook_name])) {
			foreach ($this->listeners[$hook_name] as $p) {
				if (method_exists($p, $hook_name)) {
					$p->$hook_name($params);
				}
			}
		}
	}

}
?>
