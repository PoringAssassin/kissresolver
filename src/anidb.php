<?php

require_once('kiss.php');
require_once('common.php');

/**
* Class for redirecting anidb links to kissanime.
*/
class AniDBApplication {
	/** The default title download URL. */
	const TITLE_URL_DEFAULT = 'http://anidb.net/api/anime-titles.dat.gz';
	
	/** The default php script timeout in seconds */
	const TIMEOUT_LIMIT = 200;

	/** The PDO connection link */
	private $connection;

	function __construct($host, $db, $user, $pass) {
		try {
			$this->connection = new \PDO(
				'mysql:host=' . $host . ';dbname=' . $db . ';charset=utf8',
				$user,
				$pass,
				array(
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::ATTR_PERSISTENT => false
				)
			);
		}
		catch (\PDOException $ex) {
			die($ex->getMessage());
		}
	}
	
	/**
	* Gets an int/string value from the config by name.
	* 
	* @param $name The name of the config value.
	*/
	public function getConfig($name) {
		$handle = $this->connection->prepare('SELECT val_int, val_str FROM config WHERE name = ?');
		$handle->bindValue(1, $name);
		$handle->execute();
		
		$result = $handle->fetchAll(\PDO::FETCH_OBJ);
		
		if (count($result) < 1) {
			return null;
		}
		else if ($result[0]->val_int != null) {
			return $result[0]->val_int;
		}
		else {
			return $result[0]->val_str;
		}
	}
	
	/**
	* Sets an int/string value to the config by name.
	* Only integers get put in the int field.
	* Everything else is converted to string.
	* 
	* @param $name The name of the config value.
	* @param $value The value to set.
	*/
	public function setConfig($name, $value) {
		$int_val = NULL;
		$str_val = NULL;
		
		if (gettype($value) == 'NULL' || gettype($value) == 'unknown type') {
			// Do nothing, NULL is fine.
		}
		else if (gettype($value) == 'integer') {
			$int_val = $value;
		}
		else {
			$str_val = $value . '';
		}
	
		$handle = $this->connection->prepare('REPLACE INTO config (name, val_int, val_str) VALUES(?, ?, ?)');
		$handle->bindValue(1, $name);
		$handle->bindValue(2, $int_val, PDO::PARAM_INT);
		$handle->bindValue(3, $str_val);
		
		$handle->execute();
	}
	
	/**
	* Updates the anime titles from anidb in the database if needed.
	*/
	public function tryUpdateTitles() {
		$now = new DateTime();
		$nextUpdate = new DateTime();
		$nextUpdate->setTimestamp($this->getConfig('last_title_update'));
		$nextUpdate->add(new DateInterval('P1D'));
		
		if ($nextUpdate > $now) {
			return;
		}
	
		set_time_limit(self::TIMEOUT_LIMIT);
	
		$contents = file_get_contents(self::TITLE_URL_DEFAULT);
		
		if ($contents == false) {
			return;
		}
		
		$contents = gzdecode($contents);
		
		if ($contents == false) {
			return;
		}
		
		$this->connection->beginTransaction();
		
		try {
			$handle = $this->connection->prepare(
				'INSERT INTO '.
				'title (lang, type, value, anime_id) '.
				'(SELECT ?, ?, ?, ? FROM title '.
				'WHERE NOT EXISTS (SELECT 1 FROM title WHERE lang = ? AND type = ? AND value = ? AND anime_id = ?) LIMIT 1)'
			);
			
			$type = array('undefined', 'primary', 'synonym', 'short', 'official');
			
			$fp = fopen("php://memory", 'r+');
			fputs($fp, $contents);
			rewind($fp);
			
			while($line = fgets($fp)) {
				if ($line[0] == '#')
					continue;
				
				$title = explode('|', $line);
				
				if (count($title) < 4)
					continue;
				
				$l = $title[2];
				$t = 'synonym';
				if (!array_key_exists(intval($title[1]), $type)){
					die('Unable to find type ' . title[1]);
				}
				else {
					$t = $type[intval($title[1])];
				}
				
				$v = trim($title[3]);
				$i = intval($title[0]);
				
				$handle->bindValue(1, $l);
				$handle->bindValue(2, $t);
				$handle->bindValue(3, $v);
				$handle->bindValue(4, $i, PDO::PARAM_INT);
				$handle->bindValue(5, $l);
				$handle->bindValue(6, $t);
				$handle->bindValue(7, $v);
				$handle->bindValue(8, $i, PDO::PARAM_INT);
				
				$handle->execute();
			}
			fclose($fp);
			
			$this->connection->commit();
			
			$now = new DateTime();
			$this->setConfig('last_title_update', $now->getTimestamp());
		}
		catch (\PDOException $ex) {
			$this->connection->rollBack();
			print($ex);
		}
	}
	
	/**
	* Gets the AniDB ID of the specified anime title.
	* 
	* @param $title The title to search for.
	*/
	public function getAnimeIDFromTitle($title) {
		$handle = $this->connection->prepare(
			'SELECT anime_id FROM title '.
			"WHERE value LIKE CONCAT('%', ?, '%')"
		);
		
		$handle->bindValue(1, $title);
		$handle->execute();
		
		$result = $handle->fetchAll(\PDO::FETCH_OBJ);
		$count = count($result);
		if ($count == 0) {
			return null;
		}
		else {
			return intval($result[0]->anime_id);
		}
	}
	
	/**
	* Gets the mp4 url from KissAnime.to based an anime, episode and resolution.
	* 
	* @param $anime_id The AniDB ID of the requested anime.
	* @param $episode_num The episode number.
	* @param $resolution The resolution as string to get the video url in, for example: 1080 or 720.
	*/
	public function getVideoUrl($anime_id, $episode_num, $resolution) {
		$handle = $this->connection->prepare(
			'SELECT value, resolution FROM urlcache ' .
			'WHERE anime_id = ? AND episode_num = ? AND resolution > -1 AND DATE_ADD(last_updated, INTERVAL 1 HOUR) >= NOW() ' .
			'ORDER BY resolution DESC'
		);
		
		$handle->bindValue(1, $anime_id);
		$handle->bindValue(2, $episode_num);
		$handle->execute();
		
		$result = $handle->fetchAll(\PDO::FETCH_OBJ);
		$count = count($result);
		
		if ($count >= 1) {
			for ($i = 0; $i < $count; $i++) {
				if ($result[$i]->resolution <= $resolution) {
					return $result[$i]->value;
				}
			}
			
			return $result[$count - 1]->value;
		}
		
		$handle = $this->connection->prepare(
			'SELECT value FROM urlcache '.
			'WHERE anime_id = ? AND episode_num = ? AND resolution = -1'
		);
		
		$handle->bindValue(1, $anime_id);
		$handle->bindValue(2, $episode_num);
		$handle->execute();
		
		$result = $handle->fetchAll(\PDO::FETCH_OBJ);
		if (count($result) >= 1) {
			return $this->getVideoFromEpisodePage($result[0]->value, $anime_id, $episode_num, $resolution);
		}
		
		$handle = $this->connection->prepare(
			'SELECT value FROM urlcache '.
			'WHERE anime_id = ? AND episode_num -1 AND resolution = -1'
		);
		
		$handle->bindValue(1, $anime_id);
		$handle->execute();
		
		$result = $handle->fetchAll(\PDO::FETCH_OBJ);
		if (count($result) >= 1) {
			return $this->getVideoUrlFromAnimePage($result[0]->value, $anime_id, $episode_num, $resolution);
		}
		else {
			return $this->getVideoUrlFromSearch($anime_id, $episode_num, $resolution);
		}
	}
	
	/**
	* Puts an url in the database cache.
	*
	* @param $value The url to cache.
	* @param $anime_id The anime associated with the video url.
	* @param $episode_num The episode number associated with the video url.
	* @param $resolution The resolution associated with the video url.
	*/
	private function cacheUrl($value, $anime_id, $episode_num, $resolution) {
		$handle = $this->connection->prepare('REPLACE INTO urlcache (anime_id, episode_num, resolution, value, last_updated) VALUES(?, ?, ?, ?, NOW())');

		$handle->bindValue(1, $anime_id, PDO::PARAM_INT);
		$handle->bindValue(2, $episode_num == null ? -1 : $episode_num, PDO::PARAM_INT);
		$handle->bindValue(3, $resolution == null ? -1 : $resolution, PDO::PARAM_INT);
		$handle->bindValue(4, $value);
		
		$handle->execute();
	}
	
	/**
	* Gets the video url from kissanime's search page.
	*
	* @param $anime_id The AniDB anime ID of the requested anime.
	* @param $episode_num The number of the requested episode.
	* @param $resolution The requested resolution. Gets the highest available.
	*/
	private function getVideoUrlFromSearch($anime_id, $episode_num, $resolution) {
		$handle = $this->connection->prepare(
			'SELECT value FROM title '.
			"WHERE anime_id = ? AND type = 'primary' LIMIT 1"
		);
	
		$handle->bindValue(1, $anime_id);
		$handle->execute();
		
		$result = $handle->fetchAll(\PDO::FETCH_OBJ);
		if (count($result) < 1) {
			return null;
		}
	
		$response = KissAnime::call($this, '/Search/SearchSuggest', 'POST', array(
			'type' => 'Anime',
			'keyword' => trim($result[0]->value)
		));
		
		if (!preg_match('#<a href="(?<url>[^"]+)">(?<name>[^<]+)</a>#si', $response, $matches)) {
			return null;	
		}
		
		$this->cacheUrl($matches['url'], $anime_id, null, null);
		
		return $this->getVideoUrlFromAnimePage($matches['url'], $anime_id, $episode_num, $resolution);
	}
	
	/**
	* Gets the video url based on the (cached) url of an kiss anime page.
	*
	* @param $url The url (relative or absolute) of the anime page.
	* @param $anime_id The AniDB anime ID of the requested anime.
	* @param $episode_num The number of the requested episode.
	* @param $resolution The requested resolution. Gets the highest available.
	*/
	private function getVideoUrlFromAnimePage($url, $anime_id, $episode_num, $resolution) {
		$response = KissAnime::call($this, $url);
		
		if (!preg_match_all('#<a +href="(?<path>[^"]+)"[^>]*>[^<]+Episode (?<number>[0-9]+)\s*</a>\s*</td>\s*<td>\s*(?<month>[0-9]+)/(?<day>[0-9]+)/(?<year>[0-9]+)#si', $response, $matches)) {
			return null;	
		}
		
		$episode_path = null;
		
		for ($i = 0; $i < count($matches[0]); $i++) {
			$currentnum = intval($matches['number'][$i]);
			$path = $matches['path'][$i];
			$this->cacheUrl($path, $anime_id, $currentnum, null);
			
			if ($currentnum == $episode_num) {
				$episode_path = $path;
			}
		}
		
		if ($episode_path == null) {
			return null;
		}
		
		return $this->getVideoFromEpisodePage($episode_path, $anime_id, $episode_num, $resolution);
	}
	
	/**
	* Gets the video url based on the (cached) url of an kiss episode page.
	*
	* @param $url The url (relative or absolute) of the episode page.
	* @param $anime_id The AniDB anime ID of the requested anime.
	* @param $episode_num The number of the requested episode.
	* @param $resolution The requested resolution. Gets the highest available.
	*/
	private function getVideoFromEpisodePage($url, $anime_id, $episode_num, $resolution) {
		$response = KissAnime::call($this, $url);
		
		if (!preg_match_all('#<option value="(?<url>[a-z|0-9|=|/]+)"[^>]*>(?<quality>[0-9]+)p</option>#si', $response, $matches)) {
			return null;	
		}
		
		$resolutions = array();
		
		for ($i = 0; $i < count($matches[0]); $i++) {
			$currentres = intval($matches['quality'][$i]);
			$currenturl = base64_decode($matches['url'][$i]);
			
			$this->cacheUrl($currenturl, $anime_id, $episode_num, $currentres);
			
			$resolutions[$currentres] = $currenturl;
		}
		
		if (array_key_exists($resolution, $resolutions)) {
			return $resolutions[$resolution];
		}
		else {
			krsort($resolutions, SORT_NUMERIC);
			foreach ($resolutions as $res => $vidurl) {
				if ($res <= $resolution) {
					return $vidurl;
				}
			}
			
			return end($resolutions);
		}
	}
	
	/**
	* Runs the application to return a video url.
	* 
	* @param $host The database server host.
	* @param $db The database name.
	* @param $user The username for the database.
	* @param $pass The password for the database.
	*/
	public static function run($host, $db, $user, $pass) {
		$anidb = new AniDBApplication($host, $db, $user, $pass);
		$anidb->tryUpdateTitles();
		
		if ((!isset($_GET['anime_id']) && !isset($_GET['title'])) || !isset($_GET['episode_num']) || !isset($_GET['resolution'])) {
			http_response_code(400);
			return;
		}
		
		$animeid = isset($_GET['anime_id']) ? intval($_GET['anime_id']) : $anidb->getAnimeIDFromTitle($_GET['title']);
		
		if ($animeid == null) {
			http_response_code(404);
			return;
		}
		
		$url = $anidb->getVideoUrl($animeid, intval($_GET['episode_num']), intval($_GET['resolution']));
		if ($url == null) {
			http_response_code(404);
			return;
		}
		
		header('location: ' . $url);
	}
}

?>