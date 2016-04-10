<?php

require_once('kiss.php');

/**
* Class for redirecting anidb links to kissanime.
*/
class AniDBApplication {
	/** The default title download URL. */
	const TITLE_URL_DEFAULT = 'http://anidb.net/api/anime-titles.dat.gz';
	
	/** The default php script timeout in seconds */
	const TIMEOUT_LIMIT = 100;

	/** The PDO connection link */
	private $connection;

	function __construct() {
		try {
			$this->connection = new \PDO(
				'mysql:host=localhost;dbname=anidb;charset=utf8',
				'username',
				'password',
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
				$t = $type[intval($title[1])];
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
	* Gets the mp4 url from KissAnime.to based an anime, episode and resolution.
	* 
	* @param $anime_id The AniDB ID of the requested anime.
	* @param $episode_num The episode number.
	* @param $resolution The resolution as string to get the video url in, for example: 1080p or 720p.
	*/
	public function getVideoUrl($anime_id, $episode_num, $resolution) {
		$handle = $this->connection->prepare(
			'SELECT value FROM urlcache '.
			'WHERE anime_id = ? AND episode_num = ? AND resolution IS NOT NULL AND DATE_ADD(last_updated, INTERVAL 7 DAY) >= NOW()'
		);
		
		$handle->bindValue(1, $anime_id);
		$handle->bindValue(2, $episode_num);
		$handle->execute();
		
		$result = $handle->fetchAll(\PDO::FETCH_OBJ);
		
		if (count($result) >= 1) {
			return $result[0]->value;
		}
		
		$handle = $this->connection->prepare(
			'SELECT value FROM urlcache '.
			'WHERE anime_id = ? AND episode_num = ? AND resolution IS NULL'
		);
		
		$handle->bindValue(1, $anime_id);
		$handle->bindValue(2, $episode_num);
		$handle->execute();
		
		$result = $handle->fetchAll(\PDO::FETCH_OBJ);
		if (count($result) >= 1) {
			// Load url => get video urls
		}
		
		$handle = $this->connection->prepare(
			'SELECT value FROM urlcache '.
			'WHERE anime_id = ? AND episode_num IS NULL AND resolution IS NULL'
		);
		
		$handle->bindValue(1, $anime_id);
		$handle->execute();
		
		$result = $handle->fetchAll(\PDO::FETCH_OBJ);
		if (count($result) >= 1) {
			// Load url => get episode urls => get video urls
		}
		else {
			$animeurl = $this->getAnimeUrl($anime_id);
			
			if ($animeurl == null) {
				return null;
			}
			
			die ($animeurl);
			// TODO: save url
			
			// Search for title => load url => get episode urls => get video urls
		}
	}
	
	private function getAnimeUrl($anime_id) {
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
		
		return $matches['url'];
	}
	
	/**
	* Runs the application to return a video url.
	*/
	public static function run() {
		$anidb = new AniDBApplication();
		//$anidb->tryUpdateTitles();
		
		//KissAnime::call($anidb, '/');
		
		if (!isset($_GET['anime_id']) || !isset($_GET['episode_num']) || !isset($_GET['resolution'])) {
			die ('you dun derped');
			// TODO: 404? invalid operation?
			return;
		}
		
		echo $anidb->getVideoUrl(intval($_GET['anime_id']), intval($_GET['episode_num']), $_GET['resolution']);
	}
}

?>