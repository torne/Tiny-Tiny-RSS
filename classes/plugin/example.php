<?
	class Plugin_Example extends Plugin {
		function initialize() {
			$this->add_listener('article_before');
		}

		function article_before(&$line) {
			$line["title"] = "EXAMPLE/REPLACED:" . $line["title"];
		}
	}
?>
