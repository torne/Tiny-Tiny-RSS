<?php
	/*
	 ***** BEGIN LICENSE BLOCK *****
	 This file is part of PHP Naive Bayesian Filter.

	 The Initial Developer of the Original Code is
	 Loic d'Anterroches [loic_at_xhtml.net].
	 Portions created by the Initial Developer are Copyright (C) 2003
	 the Initial Developer. All Rights Reserved.

	 Contributor(s):
	 See the source

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

	class NaiveBayesian {
		/** min token length for it to be taken into consideration */
		var $min_token_length = 3;
		/** max token length for it to be taken into consideration */
		var $max_token_length = 15;
		/** list of token to ignore
		 @see getIgnoreList()
		 */
		var $ignore_list = array();
		/** storage object
		 @see class NaiveBayesianStorage
		 */
		var $nbs = null;

		function NaiveBayesian($nbs) {
			$this->nbs = $nbs;

			return true;
		}

		/** categorize a document.
		 Get list of categories in which the document can be categorized
		 with a score for each category.

		 @return array keys = category ids, values = scores
		 @param string document
		 */
		function categorize($document) {
			$scores = array();
			$categories = $this->nbs->getCategories();
			$tokens = $this->_getTokens($document);

			// calculate the score in each category
			$total_words = 0;
			$ncat = 0;

			while (list($category, $data) = each($categories)) {
				$total_words += $data['word_count'];
				$ncat++;
			}

			reset($categories);

			while (list($category, $data) = each($categories)) {
				$scores[$category] = $data['probability'];
				// small probability for a word not in the category
				// maybe putting 1.0 as a 'no effect' word can also be good

				if ($data['word_count'] > 0)
					$small_proba = 1.0 / ($data['word_count'] * 2);
				else
					$small_proba = 0;

				reset($tokens);

				while (list($token, $count) = each($tokens)) {

					if ($this->nbs->wordExists($token)) {
						$word = $this->nbs->getWord($token, $category);

						if ($word['count']) {
							$proba = $word['count'] / $data['word_count'];
						}
						else {
							$proba = $small_proba;
						}

						$scores[$category] *= pow($proba, $count) * pow($total_words / $ncat, $count);
						// pow($total_words/$ncat, $count) is here to avoid underflow.

					}
				}
			}

			return $this->_rescale($scores);
		}

		/** training against a document.
		 Set a document as being in a specific category. The document becomes a reference
		 and is saved in the table of references. After a set of training is done
		 the updateProbabilities() function must be run.

		 @see updateProbabilities()
		 @see untrain()
		 @return bool success
		 @param string document id, must be unique
		 @param string category_id the category id in which the document should be
		 @param string content of the document
		 */
		function train($doc_id, $category_id, $content) {
			$ret = false;


			// if this doc_id already trained, no trained
			if (!$this->nbs->getReference($doc_id, false)) {

				$tokens = $this->_getTokens($content);

				while (list($token, $count) = each($tokens)) {
					$this->nbs->updateWord($token, $count, $category_id);
				}

				$this->nbs->saveReference($doc_id, $category_id, $content);

				$ret = true;
			}
			else {
				$ret = false;
			}

			return $ret;
		}

		/** untraining of a document.
		 To remove just one document from the references.

		 @see updateProbabilities()
		 @see untrain()
		 @return bool success
		 @param string document id, must be unique
		 */
		function untrain($doc_id) {
			$ref = $this->nbs->getReference($doc_id);

			if (isset($ref['content'])) {

				$tokens = $this->_getTokens($ref['content']);

				while (list($token, $count) = each($tokens)) {
					$this->nbs->removeWord($token, $count, $ref['category_id']);
				}

				$this->nbs->removeReference($doc_id);

				return true;
			} else {
				return false;
			}
		}

		/** rescale the results between 0 and 1.

		 @author Ken Williams, ken@mathforum.org
		 @see categorize()
		 @return array normalized scores (keys => category, values => scores)
		 @param array scores (keys => category, values => scores)
		 */
		function _rescale($scores) {
			// Scale everything back to a reasonable area in
			// logspace (near zero), un-loggify, and normalize
			$total = 0.0;
			$max = 0.0;
			reset($scores);

			while (list($cat, $score) = each($scores)) {
				if ($score >= $max)
					$max = $score;
			}

			reset($scores);
			while (list($cat, $score) = each($scores)) {
				$scores[$cat] = (float) exp($score - $max);
				$total += (float) pow($scores[$cat], 2);
			}

			$total = (float) sqrt($total);

			reset($scores);
			while (list($cat, $score) = each($scores)) {
				$scores[$cat] = (float) $scores[$cat] / $total;
			}
			reset($scores);

			return $scores;
		}

		/** update the probabilities of the categories and word count.
		 This function must be run after a set of training

		 @see train()
		 @see untrain()
		 @return bool sucess
		 */
		function updateProbabilities() {
			// this function is really only database manipulation
			// that is why all is done in the NaiveBayesianStorage
			return $this->nbs->updateProbabilities();
		}

		/** Get the list of token to ignore.
		 @return array ignore list
		 */
		function getIgnoreList() {
			return array('the', 'that', 'you', 'for', 'and');
		}

		/** get the tokens from a string

		 @author James Seng. [http://james.seng.cc/] (based on his perl version)

		 @return array tokens
		 @param  string the string to get the tokens from
		 */
		function _getTokens($string) {
			$rawtokens = array();
			$tokens = array();
			//$string = $this->_cleanString($string);

			if (count(0 >= $this->ignore_list)) {
				$this->ignore_list = $this->getIgnoreList();
			}

			$rawtokens = preg_split("/[\(\),:\.;\t\r\n ]/", $string, -1, PREG_SPLIT_NO_EMPTY);

			// remove some tokens
			while (list(, $token) = each($rawtokens)) {
				$token = trim($token);
				if (!(('' == $token) || (mb_strpos($token, "&") !== FALSE) || (mb_strlen($token) < $this->min_token_length) || (mb_strlen($token) > $this->max_token_length) || (preg_match('/^[0-9]+$/', $token)) || (in_array($token, $this->ignore_list)))) {
					$tokens[$token]++;
				}
			}

			return $tokens;
		}

		/** clean a string from the diacritics

		 @author Antoine Bajolet [phpdig_at_toiletoine.net]
		 @author SPIP [http://uzine.net/spip/]

		 @return string clean string
		 @param  string string with accents
		 */
		function _cleanString($string) {
			$diac = /* A */ chr(192) . chr(193) . chr(194) . chr(195) . chr(196) . chr(197) .
				/* a */ chr(224) . chr(225) . chr(226) . chr(227) . chr(228) . chr(229) .
				/* O */ chr(210) . chr(211) . chr(212) . chr(213) . chr(214) . chr(216) .
				/* o */ chr(242) . chr(243) . chr(244) . chr(245) . chr(246) . chr(248) .
				/* E */ chr(200) . chr(201) . chr(202) . chr(203) .
				/* e */ chr(232) . chr(233) . chr(234) . chr(235) .
				/* Cc */ chr(199) . chr(231) .
				/* I */ chr(204) . chr(205) . chr(206) . chr(207) .
				/* i */ chr(236) . chr(237) . chr(238) . chr(239) .
				/* U */ chr(217) . chr(218) . chr(219) . chr(220) .
				/* u */ chr(249) . chr(250) . chr(251) . chr(252) .
				/* yNn */ chr(255) . chr(209) . chr(241);

			return strtolower(strtr($string, $diac, 'AAAAAAaaaaaaOOOOOOooooooEEEEeeeeCcIIIIiiiiUUUUuuuuyNn'));
		}

	}
