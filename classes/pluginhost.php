<?php
class PluginHost {
	private $link;
	private $hooks = array();
	private $plugins = array();
	private $handlers = array();
	private $commands = array();

	const HOOK_ARTICLE_BUTTON = 1;
	const HOOK_ARTICLE_FILTER = 2;
	const HOOK_PREFS_TAB = 3;
	const HOOK_PREFS_SECTION = 4;
	const HOOK_PREFS_TABS = 5;

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

	function run_hooks($type, $method, $args) {
		foreach ($this->get_hooks($type) as $hook) {
			$hook->$method($args);
		}
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
			$class_file = strtolower(basename($class));
			$file = dirname(__FILE__)."/../plugins/$class_file/$class_file.php";

			if (file_exists($file)) require_once $file;

			if (class_exists($class) && is_subclass_of($class, "Plugin")) {
				$plugin = new $class($this);

				$this->register_plugin($class, $plugin);
			}
		}
	}

	function add_handler($handler, $method, $sender) {
		$handler = str_replace("-", "_", strtolower($handler));
		$method = strtolower($method);

		if (!is_array($this->handlers[$handler])) {
			$this->handlers[$handler] = array();
		}

		$this->handlers[$handler][$method] = $sender;
	}

	function del_handler($handler, $method) {
		$handler = str_replace("-", "_", strtolower($handler));
		$method = strtolower($method);

		unset($this->handlers[$handler][$method]);
	}

	function lookup_handler($handler, $method) {
		$handler = str_replace("-", "_", strtolower($handler));
		$method = strtolower($method);

		if (is_array($this->handlers[$handler])) {
			if (isset($this->handlers[$handler]["*"])) {
				return $this->handlers[$handler]["*"];
			} else {
				return $this->handlers[$handler][$method];
			}
		}

		return false;
	}

	function add_command($command, $description, $sender) {
		$command = "-" . str_replace("-", "_", strtolower($command));

		$this->commands[$command] = array("description" => $description,
			"class" => $sender);
	}

	function del_command($command) {
		$command = "-" . strtolower($command);

		unset($this->commands[$command]);
	}

	function lookup_command($command) {
		$command = "-" . strtolower($command);

		if (is_array($this->commands[$command])) {
			return $this->commands[$command]["class"];
		} else {
			return false;
		}

		return false;
	}

	function get_commands() {
		return $this->commands;
	}

	function run_commands($args) {
		foreach ($this->get_commands() as $command => $data) {
			if (in_array($command, $args)) {
				$command = str_replace("-", "", $command);
				$data["class"]->$command($args);
			}
		}
	}

}
?>
