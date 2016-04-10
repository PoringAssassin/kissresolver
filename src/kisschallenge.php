<?php

require_once('jsfuck.php');

class ChallengeData {
	public $challengePath;
	public $hash;
	public $pass;
	public $script;
}

class KissChallenge {
	const CHALLENGEFORMACTIONREGEX = '#<form id="challenge-form" action="(?P<action>[^"]+)" method="(?P<method>[^"]+)">#i';
	const CHALLENGEFORMHASHREGEX = '#<input type="hidden" name="jschl_vc" value="(?P<value>[^"]+)"/>#i';
	const CHALLENGEFORMPASSREGEX = '#<input type="hidden" name="pass" value="(?P<value>[^"]+)"/>#i';
	const CHALLENGEFORMJAVASCRIPTREGEX = '#setTimeout\(function\(\)\{(?P<script>.*?)\}, 4000\);#si';

	protected $baseurl;
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
		
		$this->baseurl = $baseurl;
	}

	public function getResponsePath() {
		$host = $this->baseurl;
		
		return $this->data->challengePath . '?jschl_vc=' . 
				urlencode($this->data->hash) . '&pass=' .
				urlencode($this->data->pass) . '&jschl_answer=' .
				self::calculateAnswer($this->data->script, $host);
	}

	protected static function calculateAnswer($script, $baseUrl) {
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