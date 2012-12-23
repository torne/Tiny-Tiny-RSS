<?php
class PluginHost {
	private $link;
	private $hooks = array();
	private $plugins = array();

	const HOOK_ARTICLE_BUTTON = 1;
	const HOOK_ARTICLE_FILTER = 2;

	function __construct($link) {
		$this->link = $link;
	}

	private function register_plugin($name, $plugin) {
		//array_push($this->plugins, $plugin);
		$this->plugins[$name] = $plugin;
	}

	function get_link() {
		return $this->link;
	}

	function get_plugins() {
		return $this->plugins;
	}

	function get_plugin($name) {
		return $this->plugins[$name];
	}

	function add_hook($type, $sender) {
		if (!is_array($this->hooks[$type])) {
			$this->hooks[$type] = array();
		}

		array_push($this->hooks[$type], $sender);
	}

	function del_hook($type, $sender) {
		if (is_array($this->hooks[$type])) {
			$key = array_Search($this->hooks[$type], $sender);
			if ($key !== FALSE) {
				unset($this->hooks[$type][$key]);
			}
		}
	}

	function get_hooks($type) {
		return $this->hooks[$type];
	}

	function load($classlist) {
		$plugins = explode(",", $classlist);

		foreach ($plugins as $class) {
			$class = trim($class);
			$class_file = str_replace("_", "/", strtolower(basename($class)));
			$file = dirname(__FILE__)."/../plugins/$class_file/$class_file.php";

			if (file_exists($file)) require_once $file;

			if (class_exists($class) && is_subclass_of($class, "Plugin")) {
				$plugin = new $class($this);

				$this->register_plugin($class, $plugin);
			}
		}
	}

}
?>
