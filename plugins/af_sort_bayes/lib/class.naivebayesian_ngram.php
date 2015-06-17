<?php

	class NaiveBayesianNgram extends NaiveBayesian {
		var $N = 2;

		/**
		 * add Parameter for ngram
		 *
		 * @param NaiveBayesianStorage $nbs
		 * @param ngram's N $n
		 * @return boolean
		 */
		function __construct($nbs, $n = 2) {
			parent::__construct($nbs);

			$this->N = $n;

			return true;
		}

		/**
		 * override method for ngram
		 *
		 * @param string $string
		 * @return multiple
		 */
		function _getTokens($string) {
			$tokens = array();

			if (mb_strlen($string)) {
				for ($i = 0; $i < mb_strlen($string) - $this->N; $i++) {
					$wd = mb_substr($string, $i, $this->N);

					if (mb_strlen($wd) == $this->N) {
						if (!array_key_exists($wd, $tokens)) {
							$tokens[$wd] = 0;
						}

						$tokens[$wd]++;
					}
				}
			}

			if (count($tokens)) {
				// remove empty value
				$tokens = array_filter($tokens);
			}

			return $tokens;
		}

	}
