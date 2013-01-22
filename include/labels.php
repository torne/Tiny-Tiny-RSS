<?php
	function label_find_id($link, $label, $owner_uid) {
		$result = db_query($link,
			"SELECT id FROM ttrss_labels2 WHERE caption = '$label'
				AND owner_uid = '$owner_uid' LIMIT 1");

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "id");
		} else {
			return 0;
		}
	}

	function get_article_labels($link, $id, $owner_uid = false) {
		$rv = array();

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$result = db_query($link, "SELECT label_cache FROM
			ttrss_user_entries WHERE ref_id = '$id' AND owner_uid = " .
			$owner_uid);

		if (db_num_rows($result) > 0) {
			$label_cache = db_fetch_result($result, 0, "label_cache");

			if ($label_cache) {
				$label_cache = json_decode($label_cache, true);

				if ($label_cache["no-labels"] == 1)
					return $rv;
				else
					return $label_cache;
			}
		}

		$result = db_query($link,
			"SELECT DISTINCT label_id,caption,fg_color,bg_color
				FROM ttrss_labels2, ttrss_user_labels2
			WHERE id = label_id
				AND article_id = '$id'
				AND owner_uid = ". $owner_uid . "
			ORDER BY caption");

		while ($line = db_fetch_assoc($result)) {
			$rk = array($line["label_id"], $line["caption"], $line["fg_color"],
				$line["bg_color"]);
			array_push($rv, $rk);
		}

		if (count($rv) > 0)
			label_update_cache($link, $owner_uid, $id, $rv);
		else
			label_update_cache($link, $owner_uid, $id, array("no-labels" => 1));

		return $rv;
	}


	function label_find_caption($link, $label, $owner_uid) {
		$result = db_query($link,
			"SELECT caption FROM ttrss_labels2 WHERE id = '$label'
				AND owner_uid = '$owner_uid' LIMIT 1");

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "caption");
		} else {
			return "";
		}
	}

	function get_all_labels($link, $owner_uid) {
		$rv = array();

		$result = db_query($link, "SELECT fg_color, bg_color, caption FROM ttrss_labels2 WHERE owner_uid = " . $owner_uid);

		while ($line = db_fetch_assoc($result)) {
			array_push($rv, $line);
		}

		return $rv;
	}

	function label_update_cache($link, $owner_uid, $id, $labels = false, $force = false) {

		if ($force)
			label_clear_cache($link, $id);

		if (!$labels)
			$labels = get_article_labels($link, $id);

		$labels = db_escape_string(json_encode($labels));

		db_query($link, "UPDATE ttrss_user_entries SET
			label_cache = '$labels' WHERE ref_id = '$id' AND  owner_uid = '$owner_uid'");

	}

	function label_clear_cache($link, $id) {

		db_query($link, "UPDATE ttrss_user_entries SET
			label_cache = '' WHERE ref_id = '$id'");

	}

	function label_remove_article($link, $id, $label, $owner_uid) {

		$label_id = label_find_id($link, $label, $owner_uid);

		if (!$label_id) return;

		$result = db_query($link,
			"DELETE FROM ttrss_user_labels2
			WHERE
				label_id = '$label_id' AND
				article_id = '$id'");

		label_clear_cache($link, $id);
	}

	function label_add_article($link, $id, $label, $owner_uid) {

		$label_id = label_find_id($link, $label, $owner_uid);

		if (!$label_id) return;

		$result = db_query($link,
			"SELECT
				article_id FROM ttrss_labels2, ttrss_user_labels2
			WHERE
				label_id = id AND
				label_id = '$label_id' AND
				article_id = '$id' AND owner_uid = '$owner_uid'
			LIMIT 1");

		if (db_num_rows($result) == 0) {
			db_query($link, "INSERT INTO ttrss_user_labels2
				(label_id, article_id) VALUES ('$label_id', '$id')");
		}

		label_clear_cache($link, $id);

	}

	function label_remove($link, $id, $owner_uid) {
		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		db_query($link, "BEGIN");

		$result = db_query($link, "SELECT caption FROM ttrss_labels2
			WHERE id = '$id'");

		$caption = db_fetch_result($result, 0, "caption");

		$result = db_query($link, "DELETE FROM ttrss_labels2 WHERE id = '$id'
			AND owner_uid = " . $owner_uid);

		if (db_affected_rows($link, $result) != 0 && $caption) {

			/* Remove access key for the label */

			$ext_id = -11 - $id;

			db_query($link, "DELETE FROM ttrss_access_keys WHERE
				feed_id = '$ext_id' AND owner_uid = $owner_uid");

			/* Disable filters that reference label being removed */

			db_query($link, "UPDATE ttrss_filters SET
				enabled = false WHERE action_param = '$caption'
					AND action_id = 7
					AND owner_uid = " . $owner_uid);

			/* Remove cached data */

			db_query($link, "UPDATE ttrss_user_entries SET label_cache = ''
				WHERE label_cache LIKE '%$caption%' AND owner_uid = " . $owner_uid);

		}

		db_query($link, "COMMIT");
	}

	function label_create($link, $caption, $fg_color = '', $bg_color = '', $owner_uid) {

		if (!$owner_uid) $owner_uid = $_SESSION['uid'];

		db_query($link, "BEGIN");

		$result = false;

		$result = db_query($link, "SELECT id FROM ttrss_labels2
			WHERE caption = '$caption' AND owner_uid = $owner_uid");

		if (db_num_rows($result) == 0) {
			$result = db_query($link,
				"INSERT INTO ttrss_labels2 (caption,owner_uid,fg_color,bg_color)
					VALUES ('$caption', '$owner_uid', '$fg_color', '$bg_color')");

			$result = db_affected_rows($link, $result) != 0;
		}

		db_query($link, "COMMIT");

		return $result;
	}

?>
