<?php
	/*
	 ***** BEGIN LICENSE BLOCK *****
	 This file is part of PHP Naive Bayesian Filter.

	 The Initial Developer of the Original Code is
	 Loic d'Anterroches [loic_at_xhtml.net].
	 Portions created by the Initial Developer are Copyright (C) 2003
	 the Initial Developer. All Rights Reserved.

	 Contributor(s):

	 PHP Naive Bayesian Filter is free software; you can redistribute it
	 and/or modify it under the terms of the GNU General Public License as
	 published by the Free Software Foundation; either version 2 of
	 the License, or (at your option) any later version.

	 PHP Naive Bayesian Filter is distributed in the hope that it will
	 be useful, but WITHOUT ANY WARRANTY; without even the implied
	 warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
	 See the GNU General Public License for more details.

	 You should have received a copy of the GNU General Public License
	 along with Foobar; if not, write to the Free Software
	 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

	 Alternatively, the contents of this file may be used under the terms of
	 the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
	 in which case the provisions of the LGPL are applicable instead
	 of those above.

	 ***** END LICENSE BLOCK *****
	 */

	/** Access to the storage of the data for the filter.

	 To avoid dependency with respect to any database, this class handle all the
	 access to the data storage. You can provide your own class as long as
	 all the methods are available. The current one rely on a MySQL database.

	 methods:
	 - array getCategories()
	 - bool  wordExists(string $word)
	 - array getWord(string $word, string $categoryid)

	 */
	class NaiveBayesianStorage {
		var $con = null;
		var $owner_uid = null;
		var $max_document_length = 3000; // classifier can't rescale output for very long strings apparently

		function NaiveBayesianStorage($owner_uid) {
			$this->con = Db::get();
			$this->owner_uid = $owner_uid;

			return true;
		}

		/** get the list of categories with basic data.

		 @return array key = category ids, values = array(keys = 'probability', 'word_count')
		 */
		function getCategories() {
			$categories = array();
			$rs = $this->con->query('SELECT * FROM ttrss_plugin_af_sort_bayes_categories WHERE owner_uid = ' . $this->owner_uid);

			while ($line = $this->con->fetch_assoc($rs)) {
				$categories[$line['id']] = array('probability' => $line['probability'],
					'category' => $line['category'],
					'word_count' => $line['word_count']
				);
			}

			return $categories;
		}

		function getCategoryByName($category) {
			$rs = $this->con->query("SELECT id FROM ttrss_plugin_af_sort_bayes_categories WHERE category = '" .
				$this->con->escape_string($category) . "' AND owner_uid = " . $this->owner_uid);

			if ($this->con->num_rows($rs) != 0) {
				return $this->con->fetch_result($rs, 0, "id");
			}

			return false;
		}

		function getCategoryById($category_id) {
			$rs = $this->con->query("SELECT category FROM ttrss_plugin_af_sort_bayes_categories WHERE id = '" .
				(int)$category_id . "' AND owner_uid = " . $this->owner_uid);

			if ($this->con->num_rows($rs) != 0) {
				return $this->con->fetch_result($rs, 0, "category");
			}

			return false;
		}

		/** see if the word is an already learnt word.
		 @return bool
		 @param string word
		 */
		function wordExists($word) {
			$rs = $this->con->query("SELECT * FROM ttrss_plugin_af_sort_bayes_wordfreqs WHERE word='" . $this->con->escape_string($word) . "' AND
				owner_uid = " . $this->owner_uid);

			return $this->con->num_rows($rs) != 0;
		}

		/** get details of a word in a category.
		 @return array ('count' => count)
		 @param  string word
		 @param  string category id
		 */
		function getWord($word, $category_id) {
			$details = array();

			$rs = $this->con->query("SELECT * FROM ttrss_plugin_af_sort_bayes_wordfreqs WHERE word='" .
				$this->con->escape_string($word) . "' AND category_id=" . (int)$category_id);

			if ($this->con->num_rows($rs) == 0 ) {
				$details['count'] = 0;
			} else {
				$details['count'] = $this->con->fetch_result($rs, 0, "count");
			}

			return $details;
		}

		/** update a word in a category.
		 If the word is new in this category it is added, else only the count is updated.

		 @return bool success
		 @param string word
		 @param int    count
		 @paran string category id
		 */
		function updateWord($word, $count, $category_id) {
			$oldword = $this->getWord($word, $category_id);

			if (0 == $oldword['count']) {
				return $this->con->query("INSERT INTO ttrss_plugin_af_sort_bayes_wordfreqs (word, category_id, count, owner_uid)
					VALUES ('" . $this->con->escape_string($word) . "', '" .
					(int)$category_id . "', '" .
					(int)$count . "', '".
					$this->owner_uid . "')");
			}
			else {
				return $this->con->query("UPDATE ttrss_plugin_af_sort_bayes_wordfreqs SET count = count + " . (int) $count . " WHERE category_id = '" . $this->con->escape_string($category_id) . "' AND word = '" . $this->con->escape_string($word) . "'");
			}
		}

		/** remove a word from a category.

		 @return bool success
		 @param string word
		 @param int  count
		 @param string category id
		 */
		function removeWord($word, $count, $category_id) {
			$oldword = $this->getWord($word, $category_id);

			if (0 != $oldword['count'] && 0 >= ($oldword['count'] - $count)) {
				return $this->con->query("DELETE FROM ttrss_plugin_af_sort_bayes_wordfreqs WHERE word='" .
					$this->con->escape_string($word) . "' AND category_id='" .
					$this->con->escape_string($category_id) . "'");
			}
			else {
				return $this->con->query("UPDATE ttrss_plugin_af_sort_bayes_wordfreqs SET count = count - " .
					(int) $count . " WHERE category_id = '" . $this->con->escape_string($category_id) . "'
					AND word = '" . $this->con->escape_string($word) . "'");
			}
		}

		/** update the probabilities of the categories and word count.
		 This function must be run after a set of training

		 @return bool sucess
		 */
		function updateProbabilities() {
			// first update the word count of each category
			$rs = $this->con->query("SELECT SUM(count) AS total FROM ttrss_plugin_af_sort_bayes_wordfreqs WHERE owner_uid = ".$this->owner_uid);

			$total_words = $this->con->fetch_result($rs, 0, "total");

			if ($total_words == 0) {
				$this->con->query("UPDATE ttrss_plugin_af_sort_bayes_categories SET word_count=0, probability=0 WHERE owner_uid = " . $this->owner_uid);
				return true;
			}

			$rs = $this->con->query("SELECT tc.id AS category_id, SUM(count) AS total FROM ttrss_plugin_af_sort_bayes_categories AS tc
				LEFT JOIN ttrss_plugin_af_sort_bayes_wordfreqs AS tw ON (tc.id = tw.category_id) WHERE tc.owner_uid = ".$this->owner_uid." GROUP BY tc.id");

			while ($line = $this->con->fetch_assoc($rs)) {

				$proba = (int)$line['total'] / $total_words;
				$this->con->query("UPDATE ttrss_plugin_af_sort_bayes_categories SET word_count=" . (int) $line['total'] .
					", probability=" . $proba . " WHERE id = '" . $line['category_id'] . "'");
			}

			return true;
		}

		/** save a reference in the database.

		 @return bool success
		 @param  string reference if, must be unique
		 @param  string category id
		 @param  string content of the reference
		 */
		function saveReference($doc_id, $category_id, $content) {
			return $this->con->query("INSERT INTO ttrss_plugin_af_sort_bayes_references (document_id, category_id, owner_uid) VALUES
				('" . $this->con->escape_string($doc_id) . "', '" .
					(int)$category_id . "', " .
					(int)$this->owner_uid . ")");
		}

		/** get a reference from the database.

		 @return array  reference( category_id => ...., content => ....)
		 @param  string id
		 */
		function getReference($doc_id, $include_content = true)
		{

			$ref = array();
			$rs = $this->con->query("SELECT * FROM ttrss_plugin_af_sort_bayes_references WHERE document_id='" .
				$this->con->escape_string($doc_id) . "' AND owner_uid = " . $this->owner_uid);

			if ($this->con->num_rows($rs) == 0) {
				return $ref;
			}

			$ref['category_id'] = $this->con->fetch_result($rs, 0, 'category_id');
			$ref['id'] = $this->con->fetch_result($rs, 0, 'id');
			$ref['document_id'] = $this->con->fetch_result($rs, 0, 'document_id');

			if ($include_content) {
				$rs = $this->con->query("SELECT content, title FROM ttrss_entries WHERE guid = '" .
					$this->con->escape_string($ref['document_id']) . "'");

				if ($this->con->num_rows($rs) != 0) {
					$ref['content'] = mb_substr(mb_strtolower($this->con->fetch_result($rs, 0, 'title') . ' ' . strip_tags($this->con->fetch_result($rs, 0, 'content'))), 0,
					$this->max_document_length);
				}
			}

			return $ref;
		}

		/** remove a reference from the database

		 @return bool sucess
		 @param  string reference id
		 */
		function removeReference($doc_id) {

			return $this->con->query("DELETE FROM ttrss_plugin_af_sort_bayes_references WHERE document_id='" . $this->con->escape_string($doc_id) . "' AND owner_uid = " . $this->owner_uid);
		}

	}
