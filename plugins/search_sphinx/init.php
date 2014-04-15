<?php

class Search_Sphinx extends Plugin {
	function about() {
		return array(1.0,
			"Delegate searching for articles to Sphinx",
			"hoelzro",
			true);
	}

	function init($host) {
		$host->add_hook($host::HOOK_SEARCH, $this);

		require_once __DIR__ . "/sphinxapi.php";
	}

	function hook_search($search) {
		$offset = 0;
		$limit  = 500;

		$sphinxClient = new SphinxClient();

		$sphinxpair = explode(":", SPHINX_SERVER, 2);

		$sphinxClient->SetServer($sphinxpair[0], (int)$sphinxpair[1]);
		$sphinxClient->SetConnectTimeout(1);

		$sphinxClient->SetFieldWeights(array('title' => 70, 'content' => 30,
			'feed_title' => 20));

		$sphinxClient->SetMatchMode(SPH_MATCH_EXTENDED2);
		$sphinxClient->SetRankingMode(SPH_RANK_PROXIMITY_BM25);
		$sphinxClient->SetLimits($offset, $limit, 1000);
		$sphinxClient->SetArrayResult(false);
		$sphinxClient->SetFilter('owner_uid', array($_SESSION['uid']));

		$result = $sphinxClient->Query($search, SPHINX_INDEX);

		$ids = array();

		if (is_array($result['matches'])) {
			foreach (array_keys($result['matches']) as $int_id) {
				$ref_id = $result['matches'][$int_id]['attrs']['ref_id'];
				array_push($ids, $ref_id);
			}
		}

		$ids = join(",", $ids);

		if ($ids)
			return array("ref_id IN ($ids)", array());
		else
			return array("ref_id = -1", array());
	}

	function api_version() {
		return 2;
	}
}
?>
