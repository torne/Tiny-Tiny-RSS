<?php
class NSFW extends Plugin {

	private $link;
	private $host;

	function about() {
		return array(1.0,
			"Hide article content if tags contain \"nsfw\"",
			"fox",
			false);
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_hook($host::HOOK_RENDER_ARTICLE, $this);
		$host->add_hook($host::HOOK_RENDER_ARTICLE_CDM, $this);

	}

	function get_js() {
		return file_get_contents(dirname(__FILE__) . "/init.js");
	}

	function hook_render_article($article) {

		if (array_search("nsfw", $article["tags"]) !== FALSE) {
			$article["content"] = "<div class='nswf wrapper'><button onclick=\"nsfwShow(this)\">".__("Not work safe (click to toggle)")."</button>
				<div class='nswf content' style='display : none'>".$article["content"]."</div></div>";
		}

		return $article;
	}

	function hook_render_article_cdm($article) {
		if (array_search("nsfw", $article["tags"]) !== FALSE) {
			$article["content"] = "<div class='nswf wrapper'><button onclick=\"nsfwShow(this)\">".__("Not work safe (click to toggle)")."</button>
				<div class='nswf content' style='display : none'>".$article["content"]."</div></div>";
		}

		return $article;
	}

}
?>
