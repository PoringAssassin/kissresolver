<?php

require_once('jsfuck.php');

/**
* Data conainer for additional challenge data.
*/
class ChallengeData {
	public $challengePath;
	public $hash;
	public $pass;
	public $script;
}

/**
* Helper class for kissanime's challenge.
*/
class KissChallenge {
	/** The regex for getting the action url */
	const CHALLENGEFORMACTIONREGEX = '#<form id="challenge-form" action="(?P<action>[^"]+)" method="(?P<method>[^"]+)">#i';
	
	/** The regex for getting the jschl_vc value */
	const CHALLENGEFORMHASHREGEX = '#<input type="hidden" name="jschl_vc" value="(?P<value>[^"]+)"/>#i';
	
	/** The regex for getting the pass value */
	const CHALLENGEFORMPASSREGEX = '#<input type="hidden" name="pass" value="(?P<value>[^"]+)"/>#i';
	
	/** The regex for getting the script content */
	const CHALLENGEFORMJAVASCRIPTREGEX = '#setTimeout\(function\(\)\{(?P<script>.*?)\}, 4000\);#si';

	/** The saved data from the challenge */
	protected $data;

	function __construct($response, $baseurl) {
		$this->data = new ChallengeData();
		
		$response = preg_replace('/[\r\n]{2,}/', '', $response);
		
		$matches = null;
		preg_match(self::CHALLENGEFORMACTIONREGEX, $response, $matches);
		$this->data->challengePath = $matches['action'];
		
		preg_match(self::CHALLENGEFORMHASHREGEX, $response, $matches);
		$this->data->hash = $matches['value'];
		
		preg_match(self::CHALLENGEFORMPASSREGEX, $response, $matches);
		$this->data->pass = $matches['value'];
		
		preg_match(self::CHALLENGEFORMJAVASCRIPTREGEX, $response, $matches);
		$this->data->script = trim($matches['script']);
	}

	/**
	* Gets the path to post the answer to the challenge.
	*/
	public function getResponsePath() {
		return $this->data->challengePath . '?jschl_vc=' . 
				urlencode($this->data->hash) . '&pass=' .
				urlencode($this->data->pass) . '&jschl_answer=' .
				self::calculateAnswer($this->data->script);
	}

	/**
	* Calculates the magic number based on the supplied javascript.
	*
	* @param $script The javascript.
	*/
	protected static function calculateAnswer($script) {
		$urlLength = strlen('kissanime.to'); // le magic.
		
		$matches = null;
		preg_match('#var [a-z|,]+, (?<name>[a-z]+)=\{"(?<property>[a-z]+)":(?<code>.*?)\};#i', $script, $matches);
		$basenum = JSFuck::unfuckCode($matches['code']);
		
		preg_match_all('#' . $matches['name'] . '\.' . $matches['property'] . '(?<op>[+|\-|*|/])=(?<code>[^;]+);#i', $script, $matches);
		
		for ($i = 0; $i < count($matches[0]); $i++) {
			switch ($matches['op'][$i]) {
				case '+': $basenum += JSFuck::unfuckCode($matches['code'][$i]); break;
				case '-': $basenum -= JSFuck::unfuckCode($matches['code'][$i]); break;
				case '*': $basenum *= JSFuck::unfuckCode($matches['code'][$i]); break;
				case '/': $basenum /= JSFuck::unfuckCode($matches['code'][$i]); break;
			}
		}

		return $basenum + $urlLength;
	}
}

?>