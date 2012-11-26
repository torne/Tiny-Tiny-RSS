<?php
class Filter {
	protected $link;

	function __construct($link) {
		$this->link = $link;
	}

	function filter_article($article) {
		return $article;
	}

}
?>
