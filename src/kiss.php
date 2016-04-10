<?php

require_once('httpful.phar');
require_once('common.php');
require_once('kisschallenge.php');

/**
* Class to scrape data from kissanime.to.
*/
class KissAnime {
	const BASEURL = "https://kissanime.to/";
	
	protected static $cookies = null;
	
	public static function call($anidb, $path, $method = "GET", $formdata = null) {
		$url = startsWith($path, self::BASEURL) ? $path : self::BASEURL . ltrim($path, '/');
		
		echo 'called with cookie: ' . self::getCookie($anidb) . "\r\n";
		$response = null;
		
		if ($method == 'POST' && $formdata != null) {	
			$response = \Httpful\Request::post($url)
					->method($method)
					->sendsType('application/x-www-form-urlencoded; charset=UTF-8')
					->withCookie(self::getCookie($anidb))
					->followRedirects(false)
					->body(http_build_query($formdata))
					->send();
			print_r($response);
		}
		else {
			$response = \Httpful\Request::get($url)->withCookie(self::getCookie($anidb))->followRedirects(false)->expectsHtml()->send();
		}

		if (array_key_exists('set-cookie', $response->headers->toArray())) {
			self::setCookie($anidb, $response->headers->toArray()['set-cookie']);
		}
		
		if (strpos($response->raw_body, 'challenge-form') !== false) {
			$challenge = new KissChallenge($response, self::BASEURL);
			
			sleep(4);
			
			self::call($anidb, $challenge->getResponsePath());
			
			// repeat current call
			return self::call($anidb, $path, $method, $formdata);
		}
		else if ($response->code == 301 || $response->code == 302) {
			if ($response->meta_data['redirect_time'] > 10) {
				die('Too many redirects');
			}
			
			self::call($anidb, $response->meta_data['redirect_url'], $method, $formdata);
		}
		else {
			return $response->raw_body;
		}
	
	/*
		if (formdata != null && formdata.Count > 0)
		{
			byte[] data = Encoding.UTF8.GetBytes(string.Join("&", formdata.Select(kvp => string.Format(
				"{0}={1}",
				Uri.EscapeUriString(kvp.Key),
				Uri.EscapeDataString(kvp.Value.ToString())
			))));
			req.ContentType = "application/x-www-form-urlencoded";
			req.ContentLength = data.Length;
			using (Stream requestStream = req.GetRequestStream())
			{
				requestStream.Write(data, 0, data.Length);
			}
		}*/
	}
	
	public static function setCookie($anidb, $setCookie) {
		$tmp = self::getCookie($anidb); // init da cookies.
	
		$cookie = explode(';', $setCookie);
		$cookie = $cookie[0];
		
		$cookie = explode('=', $cookie, 2);
		
		self::$cookies[trim($cookie[0])] = trim($cookie[1]);
		
		$anidb->setConfig('kisscookie', self::getCookie($anidb));
	}
	
	public static function getCookie($anidb) {
		if (self::$cookies == null) {
			$kisscookie = $anidb->getConfig('kisscookie');
			if ($kisscookie == null) {
				$kisscookie = array();
			}
			else {
				$parts = explode(';', $kisscookie);
				$kisscookie = array();
				for ($i = 0; $i < count($parts); $i++) {
					$cookie = explode('=', $parts[$i], 2);
					$kisscookie[trim($cookie[0])] = trim($cookie[1]);
				}
			}
			
			self::$cookies = $kisscookie;
		}
	
		$cookie = array();
		foreach (self::$cookies as $key => $value) {
			$cookie[] = $key . '=' . $value;
		}
		
		return implode('; ', $cookie);
	}
}

?>