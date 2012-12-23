<?php
class PluginHandler extends Handler_Protected {
	function csrf_ignore($method) {
		return true;
	}

	function catchall($method) {
		global $pluginhost;

		$plugin = $pluginhost->get_plugin($_REQUEST["plugin"]);

		if (method_exists($plugin, $method)) {
			$plugin->$method();
		}
	}
}

?>
