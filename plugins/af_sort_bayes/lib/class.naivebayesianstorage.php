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
			$rs = $this->con->query('SELECT * FROM ttrss_plugin_af_sort_bayes_categories');

			while ($this->con->fetch_assoc($rs)) {
				$categories[$rs['category_id']] = array('probability' => $rs['probability'],
					'word_count'  => $rs['word_count']
				);

				
			}

			return $categories;
		}

		/** see if the word is an already learnt word.
		 @return bool
		 @param string word
		 */
		function wordExists($word) {
			$rs = $this->con->query("SELECT * FROM ttrss_plugin_af_sort_bayes_wordfreqs WHERE word='" . $this->con->escape_string($word) . "'");

			return $this->con->num_rows($rs) != 0;
		}

		/** get details of a word in a category.
		 @return array ('count' => count)
		 @param  string word
		 @param  string category id
		 */
		function getWord($word, $category_id) {
			$details = array();

			$rs = $this->con->query("SELECT * FROM ttrss_plugin_af_sort_bayes_wordfreqs WHERE word='" . $this->con->escape_string($word) . "' AND category_id='" . $this->con->escape_string($category_id) . "'");

			if ($this->con->num_rows($rs) == 0 ) {
				$details['count'] = 0;
			}
			else {
				$details['count'] = $rs['count'];
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
				return $this->con->execute("INSERT INTO ttrss_plugin_af_sort_bayes_wordfreqs (word, category_id, count) VALUES ('" . $this->con->escape_string($word) . "', '" . $this->con->escape_string($category_id) . "', '" . $this->con->escape_string((int) $count) . "')");
			}
			else {
				return $this->con->execute("UPDATE ttrss_plugin_af_sort_bayes_wordfreqs SET count = count + " . (int) $count . " WHERE category_id = '" . $this->con->escape_string($category_id) . "' AND word = '" . $this->con->escape_string($word) . "'");
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
				return $this->con->execute("DELETE FROM ttrss_plugin_af_sort_bayes_wordfreqs WHERE word='" . $this->con->escape_string($word) . "' AND category_id='" . $this->con->escape_string($category_id) . "'");
			}
			else {
				return $this->con->execute("UPDATE ttrss_plugin_af_sort_bayes_wordfreqs SET count = count - " . (int) $count . " WHERE category_id = '" . $this->con->escape_string($category_id) . "' AND word = '" . $this->con->escape_string($word) . "'");
			}
		}

		/** update the probabilities of the categories and word count.
		 This function must be run after a set of training

		 @return bool sucess
		 */
		function updateProbabilities() {
			// first update the word count of each category
			$rs = $this->con->query("SELECT category_id, SUM(count) AS total FROM ttrss_plugin_af_sort_bayes_wordfreqs WHERE 1 GROUP BY category_id");
			$total_words = 0;

			while ($this->con->fetch_assoc($rs)) {
				$total_words += $rs['total'];
				
			}

			$rs->moveStart();

			if ($total_words == 0) {
				$this->con->execute("UPDATE ttrss_plugin_af_sort_bayes_categories SET word_count=0, probability=0 WHERE 1");

				return true;
			}

			while ($this->con->fetch_assoc($rs)) {
				$proba = $rs['total'] / $total_words;
				$this->con->execute("UPDATE ttrss_plugin_af_sort_bayes_categories SET word_count=" . (int) $rs['total'] . ", probability=" . $proba . " WHERE category_id = '" . $rs['category_id'] . "'");
				
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

			return $this->con->execute("INSERT INTO ttrss_plugin_af_sort_bayes_references (id, category_id, content) VALUES ('" . $this->con->escape_string($doc_id) . "', '" . $this->con->escape_string($category_id) . "', '" . $this->con->escape_string($content) . "')");
		}

		/** get a reference from the database.

		 @return array  reference( category_id => ...., content => ....)
		 @param  string id
		 */
		function getReference($doc_id) {
			$ref = array();
			$rs = $this->con->query("SELECT * FROM ttrss_plugin_af_sort_bayes_references WHERE id='" . $this->con->escape_string($doc_id) . "'");

			if ($this->con->num_rows($rs) == 0 ) {
				return $ref;
			}

			$ref['category_id'] = $rs['category_id'];
			$ref['content'] = $rs['content'];
			$ref['id'] = $rs['id'];

			return $ref;
		}

		/** remove a reference from the database

		 @return bool sucess
		 @param  string reference id
		 */
		function removeReference($doc_id) {

			return $this->con->execute("DELETE FROM ttrss_plugin_af_sort_bayes_references WHERE id='" . $this->con->escape_string($doc_id) . "'");
		}

	}
